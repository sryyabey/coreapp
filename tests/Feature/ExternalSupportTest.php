<?php

namespace Tests\Feature;

use App\Filament\Resources\Apps\Pages\EditApp;
use App\Models\App;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExternalSupportTest extends TestCase
{
    use RefreshDatabase;

    private function integration(): App
    {
        $app = App::factory()->create();
        $app->forceFill(['support_api_key_hash' => hash('sha256', 'test-secret')])->save();
        $this->withToken('test-secret');

        return $app;
    }

    private function url(App $app, string $user = 'customer-1'): string
    {
        return '/api/v1/integrations/'.$app->slug.'/support/users/'.$user.'/tickets';
    }

    private function payload(): array
    {
        return ['name' => 'Dış müşteri', 'email' => 'customer@example.com', 'subject' => 'Yardım', 'body' => 'Bir sorum var', 'locale' => 'tr', 'client_request_id' => (string) Str::uuid()];
    }

    public function test_external_conversation_retry_staff_reply_read_and_reopen(): void
    {
        $app = $this->integration();
        $payload = $this->payload();
        $id = $this->postJson($this->url($app), $payload)->assertCreated()->json('data.id');
        $this->postJson($this->url($app), $payload)->assertOk()->assertJsonPath('data.id', $id);
        $this->postJson($this->url($app), array_replace($payload, ['body' => 'Changed']))->assertConflict();
        $this->assertDatabaseCount('support_tickets', 1);
        $this->assertDatabaseCount('users', 0);
        $ticket = SupportTicket::findOrFail($id);
        $this->assertNull($ticket->user_id);
        $this->assertSame('Dış müşteri', $ticket->customer_name);
        $agent = User::factory()->create();
        $agent->assignRole(Role::findOrCreate('panel_user', 'web'));
        foreach (['ViewAny:SupportTicket', 'View:SupportTicket', 'Update:SupportTicket', 'ViewAllApps:SupportTicket'] as $permission) {
            $agent->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $message = app(SupportService::class)->reply($ticket, $agent, 'Yanıt', (string) Str::uuid(), true);
        $this->assertDatabaseCount('support_push_outbox', 0);
        $this->getJson($this->url($app).'/'.$id)->assertOk()->assertJsonPath('data.messages.1.body', 'Yanıt')->assertJsonPath('data.has_unread_reply', true);
        $this->postJson($this->url($app).'/'.$id.'/read', ['last_message_id' => $message->id])->assertOk()->assertJsonPath('data.has_unread_reply', false);
        $followup = ['body' => 'Devam', 'client_request_id' => (string) Str::uuid()];
        $this->postJson($this->url($app).'/'.$id.'/messages', $followup)->assertOk()->assertJsonPath('data.status', 'open');
        $this->postJson($this->url($app).'/'.$id.'/messages', $followup)->assertOk();
        $this->assertDatabaseCount('support_messages', 3);
        Filament::setCurrentPanel(Filament::getPanel('manage'));
        $this->actingAs($agent, 'web')->get('/manage/support-tickets/'.$id)->assertOk()->assertSee('Dış müşteri')->assertSee('customer@example.com');
    }

    public function test_panel_can_rotate_and_revoke_integration_key(): void
    {
        $app = $this->integration();
        $agent = User::factory()->create();
        $agent->assignRole(Role::findOrCreate('panel_user', 'web'));
        foreach (['ViewAny:App', 'View:App', 'Update:App'] as $permission) {
            $agent->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        Filament::setCurrentPanel(Filament::getPanel('manage'));
        $this->actingAs($agent, 'web');
        Livewire::test(EditApp::class, ['record' => $app->id])
            ->callAction('rotateSupportKey')->assertHasNoActionErrors();
        $this->assertNotSame(hash('sha256', 'test-secret'), $app->fresh()->support_api_key_hash);
        $this->assertArrayNotHasKey('support_api_key_hash', $app->fresh()->toArray());
        $this->getJson($this->url($app))->assertUnauthorized();
        Livewire::test(EditApp::class, ['record' => $app->id])
            ->callAction('revokeSupportKey')->assertHasNoActionErrors();
        $this->assertNull($app->fresh()->support_api_key_hash);
    }

    public function test_keys_users_apps_and_revocation_are_isolated(): void
    {
        $app = $this->integration();
        $id = $this->postJson($this->url($app), $this->payload())->assertCreated()->json('data.id');
        $this->getJson($this->url($app, 'other'))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($this->url($app, 'other').'/'.$id)->assertNotFound();
        $this->postJson($this->url($app, 'other').'/'.$id.'/messages', ['body' => 'Spoof', 'client_request_id' => (string) Str::uuid()])->assertNotFound();
        $other = App::factory()->create();
        $other->forceFill(['support_api_key_hash' => hash('sha256', 'other-secret')])->save();
        $this->getJson($this->url($other))->assertUnauthorized();
        $this->withToken('other-secret')->getJson($this->url($other).'/'.$id)->assertNotFound();
        $this->withToken('test-secret');
        $this->postJson($this->url($app), $this->payload() + ['user_id' => 1, 'sender_type' => 'staff'])->assertUnprocessable();
        $this->postJson($this->url($app), array_replace($this->payload(), ['body' => '   ']))->assertUnprocessable();
        $app->forceFill(['support_api_key_hash' => null])->save();
        $this->getJson($this->url($app))->assertUnauthorized();
    }
}
