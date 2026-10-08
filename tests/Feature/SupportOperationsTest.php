<?php

namespace Tests\Feature;

use App\Filament\Resources\SupportAgentApps\Pages\ManageSupportAgentApps;
use App\Filament\Resources\SupportReplyTemplates\Pages\ManageSupportReplyTemplates;
use App\Filament\Resources\SupportTickets\Pages\ListSupportTickets;
use App\Filament\Resources\SupportTickets\Pages\ViewSupportTicket;
use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Models\App;
use App\Models\AppUser;
use App\Models\Device;
use App\Models\SupportAgentApp;
use App\Models\SupportMessage;
use App\Models\SupportReplyTemplate;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportService;
use Database\Seeders\SupportSetupSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupportOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Filament::setCurrentPanel(Filament::getPanel('manage'));
    }

    private function agent(?App $app = null, bool $central = false, bool $readonly = false): User
    {
        $agent = User::factory()->create();
        $agent->assignRole(Role::findOrCreate('panel_user', 'web'));
        $permissions = ['ViewAny:SupportTicket', 'View:SupportTicket'];
        if (! $readonly) {
            $permissions[] = 'Update:SupportTicket';
        }
        if ($central) {
            $permissions[] = 'ViewAllApps:SupportTicket';
        }
        foreach ($permissions as $name) {
            $agent->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }
        if ($app) {
            SupportAgentApp::create(['app_id' => $app->id, 'user_id' => $agent->id]);
        }

        return $agent;
    }

    private function ticket(App $app, ?User $user = null): SupportTicket
    {
        $membership = AppUser::factory()->create(['app_id' => $app->id, ...($user ? ['user_id' => $user->id] : [])]);

        return SupportTicket::factory()->create(['app_id' => $app->id, 'user_id' => $membership->user_id]);
    }

    private function mobile(App $app, User $user): void
    {
        $this->app['auth']->forgetGuards();
        $device = Device::factory()->create(['app_id' => $app->id, 'user_id' => $user->id]);
        $token = $user->createToken('phone', ['app:'.$app->id]);
        $token->accessToken->forceFill(['device_id' => $device->id])->save();
        $this->withToken($token->plainTextToken);
    }

    public function test_application_scopes_apply_to_lists_counts_options_related_threads_and_direct_urls(): void
    {
        $first = App::factory()->create();
        $second = App::factory()->create();
        $own = $this->ticket($first);
        $other = $this->ticket($second, $own->user);
        $agent = $this->agent($first);
        $this->actingAs($agent, 'web');
        Livewire::test(ListSupportTickets::class)->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$other]);
        $this->assertSame([$first->id => $first->name], SupportTicketResource::appOptions());
        $this->assertSame('1', (new ListSupportTickets)->getTabs()['all']->getBadge());
        $this->get('/manage/support-tickets/'.$other->id)->assertNotFound();
        $page = Livewire::test(ViewSupportTicket::class, ['record' => $own->id]);
        $this->assertCount(0, $page->instance()->relatedTickets());
        SupportAgentApp::where('user_id', $agent->id)->delete();
        $page->call('refreshTicket')->assertForbidden();
        $this->get('/manage/support-tickets/'.$own->id)->assertNotFound();
    }

    public function test_central_agent_can_see_multiple_apps_for_the_same_user(): void
    {
        $first = App::factory()->create();
        $second = App::factory()->create();
        $own = $this->ticket($first);
        $other = $this->ticket($second, $own->user);
        $this->actingAs($this->agent(central: true), 'web');
        Livewire::test(ListSupportTickets::class)->assertCanSeeTableRecords([$own, $other]);
        $page = Livewire::test(ViewSupportTicket::class, ['record' => $own->id]);
        $this->assertSame($other->id, $page->instance()->relatedTickets()->sole()->id);
    }

    public function test_assignment_requires_app_access_and_cannot_silently_steal_a_ticket(): void
    {
        $app = App::factory()->create();
        $otherApp = App::factory()->create();
        $ticket = $this->ticket($app);
        $agent = $this->agent($app);
        $other = $this->agent($otherApp);
        $service = app(SupportService::class);
        try {
            $service->updateDetails($ticket, $agent, ['priority' => 'high', 'category' => 'bug', 'assigned_to' => $other->id, 'due_at' => null]);
            $this->fail('Expected invalid application assignee');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('assigned_to', $error->errors());
        }
        $service->take($ticket, $agent);
        $this->assertSame($agent->id, $ticket->fresh()->assigned_to);
        $another = $this->agent($app);
        $this->expectException(ValidationException::class);
        $service->take($ticket, $another);
    }

    public function test_internal_notes_stay_private_and_do_not_create_pushes(): void
    {
        $app = App::factory()->create();
        $ticket = $this->ticket($app);
        $agent = $this->agent($app);
        $id = (string) Str::uuid();
        app(SupportService::class)->note($ticket, $agent, 'INTERNAL-only sensitive diagnostic', $id);
        app(SupportService::class)->note($ticket, $agent, 'INTERNAL-only sensitive diagnostic', $id);
        $this->assertDatabaseCount('support_activities', 1);
        $this->assertDatabaseCount('support_messages', 0);
        $this->assertDatabaseCount('support_push_outbox', 0);
        $this->mobile($app, $ticket->user);
        $this->getJson('/api/v1/apps/'.$app->slug.'/support/tickets/'.$ticket->id)->assertOk()->assertJsonCount(0, 'data.messages')->assertDontSee('INTERNAL-only');
        $this->getJson('/api/v1/apps/'.$app->slug.'/support/tickets')->assertOk()->assertDontSee('INTERNAL-only');
    }

    public function test_reply_and_close_audits_then_customer_reply_reopens_without_losing_assignment(): void
    {
        $app = App::factory()->create();
        $ticket = $this->ticket($app);
        $agent = $this->agent($app);
        $service = app(SupportService::class);
        $service->updateDetails($ticket, $agent, ['priority' => 'urgent', 'category' => 'billing', 'assigned_to' => $agent->id, 'due_at' => now()->addDay()]);
        $this->actingAs($agent, 'web');
        Livewire::test(ViewSupportTicket::class, ['record' => $ticket->id])
            ->callAction('reply', data: ['body' => 'Solved', 'close_after_reply' => true])->assertHasNoActionErrors();
        $this->assertSame('closed', $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->closed_at);
        $this->assertDatabaseHas('support_activities', ['support_ticket_id' => $ticket->id, 'type' => 'reply', 'actor_id' => $agent->id]);
        $this->mobile($app, $ticket->user);
        $this->postJson('/api/v1/apps/'.$app->slug.'/support/tickets/'.$ticket->id.'/messages', ['body' => 'One more question', 'client_request_id' => (string) Str::uuid()])->assertOk()->assertJsonPath('data.status', 'open');
        $fresh = $ticket->fresh();
        $this->assertNull($fresh->closed_at);
        $this->assertSame($agent->id, $fresh->assigned_to);
        $this->assertSame('urgent', $fresh->priority);
        $this->assertSame('billing', $fresh->category);
        $this->assertNotNull($fresh->last_customer_message_at);
        $this->assertDatabaseHas('support_activities', ['support_ticket_id' => $ticket->id, 'type' => 'customer_message']);
    }

    public function test_stale_reply_form_preserves_draft_and_does_not_send(): void
    {
        $app = App::factory()->create();
        $ticket = $this->ticket($app);
        $agent = $this->agent($app);
        $this->actingAs($agent, 'web');
        $page = Livewire::test(ViewSupportTicket::class, ['record' => $ticket->id])->mountAction('reply');
        app(SupportService::class)->note($ticket, $agent, 'Another staff update', (string) Str::uuid());
        $page->setActionData(['body' => 'Keep my draft'])->callMountedAction()->assertHasActionErrors(['body'])->assertActionDataSet(['body' => 'Keep my draft']);
        $this->assertDatabaseCount('support_messages', 0);
        $this->assertDatabaseCount('support_push_outbox', 0);
        $page->mountAction('refreshDraft')->assertActionDataSet(['body' => 'Keep my draft']);
        $page->callMountedAction()->assertHasNoActionErrors();
        $this->assertDatabaseHas('support_messages', ['support_ticket_id' => $ticket->id, 'body' => 'Keep my draft']);
    }

    public function test_template_picker_only_uses_current_app_active_templates_and_ticket_language(): void
    {
        $app = App::factory()->create();
        $otherApp = App::factory()->create();
        $ticket = $this->ticket($app);
        $global = SupportReplyTemplate::factory()->create();
        $local = SupportReplyTemplate::factory()->create(['app_id' => $app->id]);
        $other = SupportReplyTemplate::factory()->create(['app_id' => $otherApp->id]);
        $english = SupportReplyTemplate::factory()->create(['locale' => 'en']);
        $inactive = SupportReplyTemplate::factory()->create(['is_active' => false]);
        $this->actingAs($this->agent($app), 'web');
        $page = Livewire::test(ViewSupportTicket::class, ['record' => $ticket->id]);
        $options = $page->instance()->templateOptions();
        $this->assertArrayHasKey($global->id, $options);
        $this->assertArrayHasKey($local->id, $options);
        foreach ([$other, $english, $inactive] as $hidden) {
            $this->assertArrayNotHasKey($hidden->id, $options);
        }
    }

    public function test_bulk_operations_are_atomic_and_authorize_each_app(): void
    {
        $first = App::factory()->create();
        $second = App::factory()->create();
        $one = $this->ticket($first);
        $two = $this->ticket($second);
        $this->actingAs($this->agent($first), 'web');
        try {
            SupportTicketResource::bulk(new Collection([$one, $two]), 'priority', ['priority' => 'urgent']);
            $this->fail('Expected cross-app denial');
        } catch (AuthorizationException) {
            $this->assertSame('normal', $one->fresh()->priority);
            $this->assertSame('normal', $two->fresh()->priority);
        }
        $this->assertDatabaseCount('support_activities', 0);
        $this->actingAs($this->agent(central: true), 'web');
        Livewire::test(ListSupportTickets::class)->callTableBulkAction('priority', [$one, $two], data: ['priority' => 'high'])->assertHasNoActionErrors();
        $this->assertSame('high', $one->fresh()->priority);
        $this->assertSame('high', $two->fresh()->priority);
    }

    public function test_history_and_messages_are_bounded_and_old_messages_can_be_loaded(): void
    {
        $app = App::factory()->create();
        $ticket = $this->ticket($app);
        for ($i = 1; $i <= 45; $i++) {
            SupportMessage::factory()->create(['support_ticket_id' => $ticket->id, 'sender_id' => $ticket->user_id, 'body' => 'message-'.$i]);
        }
        $this->actingAs($this->agent($app), 'web');
        $page = Livewire::test(ViewSupportTicket::class, ['record' => $ticket->id]);
        $this->assertCount(40, $page->instance()->conversation());
        $this->assertSame('message-6', $page->instance()->conversation()->first()->body);
        $page->call('moreMessages');
        $this->assertCount(45, $page->instance()->conversation());
        $this->assertSame('message-1', $page->instance()->conversation()->first()->body);
    }

    public function test_work_queues_exclude_closed_tickets_and_apply_priority_filters(): void
    {
        $app = App::factory()->create();
        $agent = $this->agent($app);
        $overdue = $this->ticket($app);
        $overdue->update(['assigned_to' => $agent->id, 'due_at' => now()->subHour(), 'priority' => 'urgent']);
        $closed = $this->ticket($app);
        $closed->update(['status' => 'closed', 'due_at' => now()->subDay()]);
        $unassigned = $this->ticket($app);
        $this->actingAs($agent, 'web');
        Livewire::test(ListSupportTickets::class)->set('activeTab', 'overdue')->assertCanSeeTableRecords([$overdue])->assertCanNotSeeTableRecords([$closed, $unassigned]);
        Livewire::test(ListSupportTickets::class)->set('activeTab', 'mine')->assertCanSeeTableRecords([$overdue])->assertCanNotSeeTableRecords([$closed, $unassigned]);
        Livewire::test(ListSupportTickets::class)->set('activeTab', 'unassigned')->assertCanSeeTableRecords([$unassigned])->assertCanNotSeeTableRecords([$closed, $overdue]);
        Livewire::test(ListSupportTickets::class)->filterTable('priority', 'urgent')->assertCanSeeTableRecords([$overdue])->assertCanNotSeeTableRecords([$closed, $unassigned]);
    }

    public function test_conflicting_bulk_take_rolls_back_other_assignments(): void
    {
        $app = App::factory()->create();
        $agent = $this->agent($app);
        $other = $this->agent($app);
        $one = $this->ticket($app);
        $two = $this->ticket($app);
        $two->update(['assigned_to' => $other->id]);
        $this->actingAs($agent, 'web');
        Livewire::test(ListSupportTickets::class)->callTableBulkAction('take', [$one, $two])->assertNotified('Toplu işlem uygulanmadı');
        $this->assertNull($one->fresh()->assigned_to);
        $this->assertSame($other->id, $two->fresh()->assigned_to);
        $this->assertDatabaseCount('support_activities', 0);
    }

    public function test_readonly_agents_cannot_mutate_or_manage_access_and_templates(): void
    {
        $app = App::factory()->create();
        $ticket = $this->ticket($app);
        $reader = $this->agent($app, readonly: true);
        $this->actingAs($reader, 'web');
        Livewire::test(ViewSupportTicket::class, ['record' => $ticket->id])->assertActionHidden('reply')->assertActionHidden('note');
        $this->get('/manage/support-agent-apps')->assertForbidden();
        $this->get('/manage/support-reply-templates')->assertForbidden();
        $this->expectException(AuthorizationException::class);
        app(SupportService::class)->note($ticket, $reader, 'Unauthorized', (string) Str::uuid());
    }

    public function test_manager_can_grant_app_access_and_manage_templates_without_duplicate_grants(): void
    {
        $app = App::factory()->create();
        $agent = $this->agent();
        $manager = $this->agent(central: true);
        foreach (['ManageAccess:SupportTicket', 'ManageTemplates:SupportTicket'] as $name) {
            $manager->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }
        $this->actingAs($manager, 'web');
        Livewire::test(ManageSupportAgentApps::class)->callAction('create', data: ['app_id' => $app->id, 'user_id' => $agent->id])->assertHasNoActionErrors();
        $this->assertDatabaseHas('support_agent_apps', ['app_id' => $app->id, 'user_id' => $agent->id]);
        Livewire::test(ManageSupportAgentApps::class)->callAction('create', data: ['app_id' => $app->id, 'user_id' => $agent->id])->assertHasActionErrors(['user_id']);
        Livewire::test(ManageSupportReplyTemplates::class)->callAction('create', data: ['app_id' => $app->id, 'name' => 'Local answer', 'body' => 'Answer text', 'locale' => 'en', 'is_active' => true])->assertHasNoActionErrors();
        $this->assertDatabaseHas('support_reply_templates', ['app_id' => $app->id, 'name' => 'Local answer']);
    }

    public function test_setup_is_idempotent_and_keeps_customized_templates(): void
    {
        $role = Role::findOrCreate('super_admin', 'web');
        $this->seed(SupportSetupSeeder::class);
        $this->assertTrue($role->fresh()->hasPermissionTo('ViewAllApps:SupportTicket'));
        $template = SupportReplyTemplate::where('locale', 'tr')->firstOrFail();
        $template->update(['body' => 'Customized answer']);
        $this->seed(SupportSetupSeeder::class);
        $this->assertSame('Customized answer', $template->fresh()->body);
        $this->assertDatabaseCount('support_reply_templates', 2);
    }
}
