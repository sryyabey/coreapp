<?php

namespace Tests\Feature;

use App\Filament\Resources\SupportTickets\Pages\ViewSupportTicket;
use App\Models\App;
use App\Models\AppUser;
use App\Models\Device;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportService;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function signIn(AppUser $membership): void
    {
        $this->app['auth']->forgetGuards();
        $device = Device::factory()->create(['app_id' => $membership->app_id, 'user_id' => $membership->user_id]);
        $token = $membership->user->createToken('phone', ['app:'.$membership->app_id]);
        $token->accessToken->forceFill(['device_id' => $device->id])->save();
        $this->withToken($token->plainTextToken);
    }

    private function url(App $app, string $suffix = ''): string
    {
        return '/api/v1/apps/'.$app->slug.'/support/tickets'.$suffix;
    }

    private function payload(): array
    {
        return ['subject' => 'Takvim sorusu', 'body' => 'Nasıl plan yapabilirim?', 'locale' => 'tr', 'client_request_id' => (string) Str::uuid()];
    }

    private function agent(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('panel_user', 'web'));
        foreach (['ViewAny:SupportTicket', 'View:SupportTicket', 'Update:SupportTicket'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    public function test_first_request_returns_the_persisted_status_and_message(): void
    {
        $app = App::factory()->create();
        $this->signIn(AppUser::factory()->create(['app_id' => $app->id]));
        $payload = $this->payload();
        $response = $this->postJson($this->url($app), $payload)->assertCreated();
        $response->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.messages.0.body', $payload['body'])
            ->assertJsonPath('data.messages.0.sender_type', 'user');
        $id = $response->json('data.id');
        $this->assertSame($response->json('data.status'), SupportTicket::findOrFail($id)->status);
        $this->postJson($this->url($app), $payload)->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.status', 'open');
        $this->assertDatabaseCount('support_tickets', 1);
        $this->assertDatabaseCount('support_messages', 1);
    }

    public function test_same_user_has_separate_inboxes_and_tokens_for_each_app(): void
    {
        $first = App::factory()->create(['slug' => 'shiftcal']);
        $second = App::factory()->create(['slug' => 'future-app']);
        $membership = AppUser::factory()->create(['app_id' => $first->id]);
        $otherMembership = AppUser::factory()->create(['app_id' => $second->id, 'user_id' => $membership->user_id]);
        $this->signIn($membership);
        $id = $this->postJson($this->url($first), $this->payload())->assertCreated()->json('data.id');
        $this->getJson($this->url($second))->assertForbidden();
        $this->signIn($otherMembership);
        $this->getJson($this->url($second))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($this->url($second, '/'.$id))->assertNotFound();
        $this->postJson($this->url($second, '/'.$id.'/messages'), ['body' => 'Wrong app', 'client_request_id' => (string) Str::uuid()])->assertNotFound();
        $this->postJson($this->url($second), $this->payload())->assertCreated()->assertJsonPath('data.app_slug', 'future-app');
        $this->assertDatabaseCount('support_tickets', 2);
    }

    public function test_other_users_and_spoofed_ownership_are_rejected(): void
    {
        $app = App::factory()->create();
        $this->getJson($this->url($app))->assertUnauthorized();
        $first = AppUser::factory()->create(['app_id' => $app->id]);
        $this->signIn($first);
        $this->postJson($this->url($app), $this->payload() + ['app_id' => $app->id, 'user_id' => $first->user_id, 'sender_type' => 'staff'])->assertUnprocessable();
        $this->postJson($this->url($app), array_replace($this->payload(), ['body' => '   ']))->assertUnprocessable();
        $id = $this->postJson($this->url($app), $this->payload())->assertCreated()->json('data.id');
        $this->signIn(AppUser::factory()->create(['app_id' => $app->id]));
        $this->getJson($this->url($app, '/'.$id))->assertNotFound();
        $this->postJson($this->url($app, '/'.$id.'/read'), ['last_message_id' => 1])->assertNotFound();
        $this->getJson($this->url($app))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_staff_reply_read_receipt_reopen_and_retry_are_app_scoped(): void
    {
        $app = App::factory()->create();
        $membership = AppUser::factory()->create(['app_id' => $app->id]);
        $this->signIn($membership);
        $payload = $this->payload();
        $id = $this->postJson($this->url($app), $payload)->assertCreated()->json('data.id');
        $this->postJson($this->url($app), $payload)->assertOk()->assertJsonPath('data.id', $id);
        $this->postJson($this->url($app), array_replace($payload, ['body' => 'Changed']))->assertConflict();
        $ticket = SupportTicket::findOrFail($id);
        $agent = $this->agent();
        $requestId = (string) Str::uuid();
        $message = app(SupportService::class)->reply($ticket, $agent, 'Şablonları kullanabilirsin.', $requestId);
        app(SupportService::class)->reply($ticket, $agent, 'Şablonları kullanabilirsin.', $requestId);
        $this->assertDatabaseCount('support_messages', 2);
        $this->getJson($this->url($app))->assertOk()->assertJsonPath('data.0.has_unread_reply', true)->assertJsonPath('data.0.status', 'answered');
        $this->getJson($this->url($app, '/'.$id))->assertOk()->assertJsonPath('data.messages.1.body', 'Şablonları kullanabilirsin.');
        $this->postJson($this->url($app, '/'.$id.'/read'), ['last_message_id' => $message->id])->assertOk()->assertJsonPath('data.has_unread_reply', false);
        $this->travel(1)->seconds();
        app(SupportService::class)->reply($ticket, $agent, 'Yeni yanıt', (string) Str::uuid());
        $this->postJson($this->url($app, '/'.$id.'/read'), ['last_message_id' => $message->id])->assertOk()->assertJsonPath('data.has_unread_reply', true);
        app(SupportService::class)->close($ticket, $agent);
        $followup = ['body' => 'Teşekkürler, bir sorum daha var.', 'client_request_id' => (string) Str::uuid()];
        $this->postJson($this->url($app, '/'.$id.'/messages'), $followup)->assertOk()->assertJsonPath('data.status', 'open');
        $this->postJson($this->url($app, '/'.$id.'/messages'), $followup)->assertOk();
        $this->assertDatabaseCount('support_messages', 4);
    }

    public function test_unprivileged_users_cannot_reply_or_open_panel(): void
    {
        $ticket = SupportTicket::factory()->create();
        $this->actingAs($ticket->user, 'web')->get('/manage/support-tickets')->assertForbidden();
        $this->expectException(AuthorizationException::class);
        app(SupportService::class)->reply($ticket, $ticket->user, 'Spoof', (string) Str::uuid());
    }

    public function test_ticket_owner_is_immutable(): void
    {
        $ticket = SupportTicket::factory()->create();
        $this->expectException(ValidationException::class);
        $ticket->forceFill(['app_id' => App::factory()->create()->id])->save();
    }

    public function test_inactive_membership_blocks_replies(): void
    {
        $ticket = SupportTicket::factory()->create();
        AppUser::where('app_id', $ticket->app_id)->where('user_id', $ticket->user_id)->update(['is_active' => false]);
        $this->expectException(ValidationException::class);
        app(SupportService::class)->reply($ticket, $this->agent(), 'Reply', (string) Str::uuid());
    }

    public function test_panel_renders_context_and_reply_action_delivers_to_exact_ticket(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('manage'));
        $ticket = SupportTicket::factory()->create();
        $agent = $this->agent();
        $this->actingAs($agent, 'web');
        $this->get('/manage/support-tickets')->assertOk();
        $this->get('/manage/support-tickets/'.$ticket->id)->assertOk()->assertSee($ticket->app->name)->assertSee($ticket->user->email);
        Livewire::test(ViewSupportTicket::class, ['record' => $ticket->id])
            ->callAction('reply', data: ['body' => 'Panelden yanıt'])->assertHasNoActionErrors();
        $this->assertDatabaseHas('support_messages', ['support_ticket_id' => $ticket->id, 'sender_type' => 'staff', 'body' => 'Panelden yanıt']);
    }
}
