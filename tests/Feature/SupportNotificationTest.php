<?php

namespace Tests\Feature;

use App\Jobs\SendSupportReplyPush;
use App\Models\App;
use App\Models\AppUser;
use App\Models\Device;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\ShiftCal\FcmSender;
use App\Services\SupportPush;
use App\Services\SupportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupportNotificationTest extends TestCase
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

    private function agent(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('panel_user', 'web'));
        foreach (['ViewAny:SupportTicket', 'View:SupportTicket', 'Update:SupportTicket'] as $name) {
            $user->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }
        $user->givePermissionTo(Permission::findOrCreate('ViewAllApps:SupportTicket', 'web'));

        return $user;
    }

    private function register(AppUser $membership, string $token, string $locale = 'tr'): void
    {
        $this->signIn($membership);
        $this->postJson('/api/v1/apps/'.$membership->app->slug.'/support/push-device', ['token' => $token, 'enabled' => true, 'locale' => $locale])->assertOk();
    }

    private function ticket(AppUser $membership): SupportTicket
    {
        return SupportTicket::factory()->create(['app_id' => $membership->app_id, 'user_id' => $membership->user_id]);
    }

    public function test_reply_queues_once_and_sends_only_to_the_matching_app_and_user(): void
    {
        $first = App::factory()->create(['slug' => 'shiftcal', 'name' => 'ShiftCal']);
        $second = App::factory()->create(['slug' => 'other-app']);
        $membership = AppUser::factory()->create(['app_id' => $first->id]);
        $otherApp = AppUser::factory()->create(['app_id' => $second->id, 'user_id' => $membership->user_id]);
        $otherUser = AppUser::factory()->create(['app_id' => $first->id]);
        $this->register($membership, 'correct-token');
        $this->register($otherApp, 'other-app-token');
        $this->register($otherUser, 'other-user-token');
        $sender = $this->mock(FcmSender::class);
        $sender->shouldReceive('configured')->with('shiftcal')->andReturnTrue();
        $ticket = $this->ticket($membership);
        $requestId = (string) Str::uuid();
        $agent = $this->agent();
        app(SupportService::class)->reply($ticket, $agent, 'PRIVATE reply body', $requestId);
        app(SupportService::class)->reply($ticket, $agent, 'PRIVATE reply body', $requestId);
        $this->assertDatabaseCount('support_push_outbox', 1);
        Queue::assertPushed(SendSupportReplyPush::class, 1);
        $event = DB::table('support_push_outbox')->first();
        $sender->shouldReceive('send')->once()->withArgs(function (array $message, string $slug) use ($ticket, $membership): bool {
            $this->assertSame('shiftcal', $slug);
            $this->assertSame('correct-token', $message['token']);
            $this->assertSame('Destek talebiniz yanıtlandı.', $message['notification']['body']);
            $this->assertSame('ShiftCal', $message['notification']['title']);
            $this->assertSame($ticket->id, $message['data']['ticket_id']);
            $this->assertSame((string) $membership->user_id, $message['data']['account_id']);
            $this->assertSame('shiftcal', $message['data']['app_slug']);
            $this->assertStringNotContainsString('PRIVATE', json_encode($message));

            return true;
        })->andReturn(['status' => 'sent', 'id' => 'provider-id']);
        (new SendSupportReplyPush($event->id))->handle($sender, app(SupportPush::class));
        (new SendSupportReplyPush($event->id))->handle($sender, app(SupportPush::class));
        $this->assertDatabaseHas('support_push_outbox', ['id' => $event->id, 'status' => 'completed']);
        $this->assertDatabaseCount('support_push_deliveries', 1);
        $this->assertStringNotContainsString('correct-token', DB::table('support_push_devices')->where('user_id', $membership->user_id)->where('app_id', $first->id)->value('token'));
    }

    public function test_other_application_uses_its_own_sender_and_english_text(): void
    {
        $membership = AppUser::factory()->create(['app_id' => App::factory()->create(['slug' => 'other-app', 'name' => 'Other App'])->id]);
        $this->register($membership, 'other-token', 'en');
        $sender = $this->mock(FcmSender::class);
        $sender->shouldReceive('configured')->with('other-app')->andReturnTrue();
        app(SupportService::class)->reply($this->ticket($membership), $this->agent(), 'Answer', (string) Str::uuid());
        $sender->shouldReceive('send')->once()->withArgs(fn (array $message, string $slug): bool => $slug === 'other-app' && $message['token'] === 'other-token' && $message['notification']['title'] === 'Other App' && $message['notification']['body'] === 'Your support request has been answered.')->andReturn(['status' => 'sent']);
        (new SendSupportReplyPush(DB::table('support_push_outbox')->value('id')))->handle($sender, app(SupportPush::class));
    }

    public function test_preferences_read_messages_and_revoked_sessions_prevent_delivery(): void
    {
        $membership = AppUser::factory()->create();
        $this->register($membership, 'token');
        $sender = $this->mock(FcmSender::class);
        $sender->shouldReceive('configured')->andReturnTrue();
        $sender->shouldNotReceive('send');
        $ticket = $this->ticket($membership);
        $message = app(SupportService::class)->reply($ticket, $this->agent(), 'Answer', (string) Str::uuid());
        $event = DB::table('support_push_outbox')->first();
        $push = app(SupportPush::class);
        $this->assertTrue($push->valid($event));
        $base = '/api/v1/apps/'.$membership->app->slug.'/support';
        $this->patchJson($base.'/notification-preferences', ['support_replies' => false])->assertOk()->assertJsonPath('data.support_replies', false);
        $this->assertFalse($push->valid($event));
        $this->patchJson($base.'/notification-preferences', ['support_replies' => true])->assertOk();
        $this->getJson($base.'/notification-preferences')->assertOk()->assertJsonPath('data.support_replies', true);
        $ticket->update(['user_read_at' => $message->created_at]);
        $this->assertFalse($push->valid($event));
        $ticket->update(['user_read_at' => null]);
        DB::table('devices')->where('user_id', $membership->user_id)->update(['revoked_at' => now()]);
        (new SendSupportReplyPush($event->id))->handle($sender, $push);
        $this->assertDatabaseCount('support_push_deliveries', 0);
    }

    public function test_fcm_configuration_selects_the_application_project(): void
    {
        $files = [];
        try {
            foreach (['shiftcal', 'other-app'] as $slug) {
                $file = tempnam(sys_get_temp_dir(), 'support-fcm-');
                $files[] = $file;
                $credentials = ['project_id' => $slug.'-project', 'client_email' => $slug.'@example.test', 'private_key_id' => $slug.'-key'];
                file_put_contents($file, json_encode($credentials));
                Cache::put('shiftcal:fcm:oauth:'.hash('sha256', $credentials['client_email'].$credentials['private_key_id']), 'cached-oauth', 300);
                if ($slug === 'shiftcal') {
                    config(['services.shiftcal_fcm' => ['enabled' => true, 'credentials' => $file]]);
                } else {
                    config(['support.fcm.apps' => [$slug => ['enabled' => true, 'credentials' => $file]]]);
                }
            }
            Http::preventStrayRequests();
            Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'sent'])]);
            $sender = new FcmSender;
            $this->assertTrue($sender->configured('shiftcal'));
            $this->assertTrue($sender->configured('other-app'));
            $this->assertFalse($sender->configured('unconfigured-app'));
            $sender->send(['token' => 'one'], 'shiftcal');
            $sender->send(['token' => 'two'], 'other-app');
            Http::assertSent(fn ($request): bool => str_contains($request->url(), '/projects/shiftcal-project/') && $request['message']['token'] === 'one');
            Http::assertSent(fn ($request): bool => str_contains($request->url(), '/projects/other-app-project/') && $request['message']['token'] === 'two');
        } finally {
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }

    public function test_generic_registration_rejects_owner_override_and_wrong_app_token(): void
    {
        $membership = AppUser::factory()->create();
        $this->signIn($membership);
        $base = '/api/v1/apps/'.$membership->app->slug.'/support';
        $this->postJson($base.'/push-device', ['token' => 'token', 'locale' => 'tr', 'enabled' => true, 'app_id' => 1, 'user_id' => 1, 'device_id' => 1])->assertUnprocessable();
        $other = App::factory()->create();
        $this->getJson('/api/v1/apps/'.$other->slug.'/support/notification-preferences')->assertForbidden();
        $this->assertDatabaseCount('support_push_devices', 0);
    }

    public function test_transient_failure_retries_and_invalid_tokens_are_disabled(): void
    {
        $membership = AppUser::factory()->create();
        $this->register($membership, 'invalid');
        $sender = $this->mock(FcmSender::class);
        $sender->shouldReceive('configured')->andReturnTrue();
        app(SupportService::class)->reply($this->ticket($membership), $this->agent(), 'Answer', (string) Str::uuid());
        $event = DB::table('support_push_outbox')->first();
        $job = new SendSupportReplyPush($event->id);
        $sender->shouldReceive('send')->once()->andThrow(new \RuntimeException('provider unavailable'));
        try {
            $job->handle($sender, app(SupportPush::class));
            $this->fail('Expected retryable failure');
        } catch (\RuntimeException $error) {
            $this->assertSame('provider unavailable', $error->getMessage());
        }
        $this->assertDatabaseHas('support_push_outbox', ['id' => $event->id, 'status' => 'pending']);
        $sender->shouldReceive('send')->once()->andReturn(['status' => 'invalid_token']);
        $job->handle($sender, app(SupportPush::class));
        $this->assertDatabaseHas('support_push_devices', ['token_hash' => hash('sha256', 'invalid'), 'enabled' => false]);
    }
}
