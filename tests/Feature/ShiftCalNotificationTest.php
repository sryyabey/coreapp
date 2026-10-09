<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\ShiftCal\NotificationController;
use App\Jobs\SendShiftCalPush;
use App\Models\App;
use App\Models\AppUser;
use App\Models\Device;
use App\Services\ShiftCal\FcmSender;
use App\Services\ShiftCal\PushOutbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use Tests\Support\GrantsShiftCalDuo;
use Tests\TestCase;

class ShiftCalNotificationTest extends TestCase
{
    use GrantsShiftCalDuo;
    use RefreshDatabase;

    private AppUser $first;

    private AppUser $second;

    private Device $device;

    private string $prefix = '/api/v1/apps/shiftcal/shiftcal';

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->travelTo(now()->setDate(2026, 10, 6)->startOfDay());
        $app = App::factory()->create(['slug' => 'shiftcal']);
        $this->first = AppUser::factory()->create(['app_id' => $app->id]);
        $this->second = AppUser::factory()->create(['app_id' => $app->id]);
        $this->grantDuo($this->first);
        $this->signIn($this->first);
        $code = $this->postJson($this->prefix.'/partner/invitation')->assertCreated()->json('data.code');
        $this->signIn($this->second);
        $this->postJson($this->prefix.'/partner/accept', ['code' => $code])->assertOk();
        $this->signIn($this->first);
    }

    private function signIn(AppUser $membership): void
    {
        $this->device = Device::factory()->create(['app_id' => $membership->app_id, 'user_id' => $membership->user_id]);
        $token = $this->device->user->createToken('phone', ['app:'.$membership->app_id]);
        $token->accessToken->forceFill(['device_id' => $this->device->id])->save();
        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken);
    }

    private function proposal(): string
    {
        return $this->postJson($this->prefix.'/partner/plans', ['title' => 'Gizli akşam başlığı', 'starts_at' => now()->addHour()->toIso8601String(), 'ends_at' => now()->addHours(2)->toIso8601String(), 'timezone' => 'UTC', 'client_request_id' => (string) Str::uuid()])->assertCreated()->json('data.id');
    }

    public function test_shared_push_is_suppressed_when_duo_expires(): void
    {
        $id = $this->proposal();
        $event = DB::table('shiftcal_push_outbox')->where('plan_id', $id)->first();
        $this->assertTrue(app(PushOutbox::class)->valid($event));
        DB::table('purchases')->update(['expires_at' => now()]);
        $this->assertFalse(app(PushOutbox::class)->valid($event));
    }

    public function test_preferences_are_account_scoped_and_minutes_are_validated(): void
    {
        $this->getJson($this->prefix.'/notification-preferences')->assertOk()->assertJsonPath('data.work_enabled', false)->assertJsonPath('data.work_minutes', 15);
        $prefs = [...NotificationController::DEFAULTS, 'work_enabled' => true, 'work_minutes' => 20];
        $this->patchJson($this->prefix.'/notification-preferences', $prefs)->assertOk()->assertJsonPath('data.work_minutes', 20);
        $this->patchJson($this->prefix.'/notification-preferences', [...$prefs, 'plan_minutes' => 19])->assertUnprocessable();
        $this->patchJson($this->prefix.'/notification-preferences', [...$prefs, 'user_id' => $this->second->user_id])->assertUnprocessable();
        $this->signIn($this->second);
        $this->getJson($this->prefix.'/notification-preferences')->assertJsonPath('data.work_enabled', false);
    }

    public function test_tokens_are_encrypted_bound_to_authenticated_device_and_transferred_between_accounts(): void
    {
        $payload = ['token' => 'secret-fcm-token', 'enabled' => true, 'locale' => 'tr'];
        $this->postJson($this->prefix.'/push-device', [...$payload, 'device_id' => 999])->assertUnprocessable();
        $this->postJson($this->prefix.'/push-device', $payload)->assertOk()->assertJsonMissing(['token' => 'secret-fcm-token']);
        $row = DB::table('shiftcal_push_devices')->first();
        $this->assertSame($this->device->id, $row->device_id);
        $this->assertNotSame('secret-fcm-token', $row->token);
        $this->assertSame('secret-fcm-token', Crypt::decryptString($row->token));
        $this->signIn($this->second);
        $this->postJson($this->prefix.'/push-device', $payload)->assertOk();
        $this->assertDatabaseCount('shiftcal_push_devices', 1);
        $this->assertDatabaseHas('shiftcal_push_devices', ['user_id' => $this->second->user_id]);
        $this->deleteJson($this->prefix.'/push-device')->assertNoContent();
        $this->assertDatabaseCount('shiftcal_push_devices', 0);
    }

    public function test_plan_events_notify_only_the_other_person_and_idempotent_acceptance_does_not_duplicate(): void
    {
        $id = $this->proposal();
        $this->assertDatabaseHas('shiftcal_push_outbox', ['plan_id' => $id, 'user_id' => $this->second->user_id, 'type' => 'offered']);
        $this->signIn($this->second);
        $this->patchJson($this->prefix.'/partner/plans/'.$id, ['action' => 'accept'])->assertOk();
        $this->patchJson($this->prefix.'/partner/plans/'.$id, ['action' => 'accept'])->assertOk();
        $this->assertDatabaseCount('shiftcal_push_outbox', 2);
        $this->assertDatabaseHas('shiftcal_push_outbox', ['plan_id' => $id, 'user_id' => $this->first->user_id, 'type' => 'accepted']);
        $this->getJson($this->prefix.'/partner/plans/'.$id)->assertJsonPath('data.status', 'accepted');
        $this->patchJson($this->prefix.'/partner/plans/'.$id, ['action' => 'cancel'])->assertOk();
        $this->assertDatabaseHas('shiftcal_push_outbox', ['plan_id' => $id, 'user_id' => $this->first->user_id, 'type' => 'cancelled']);
    }

    public function test_reminders_use_each_partners_preference_and_cancelled_or_changed_preferences_are_skipped(): void
    {
        $id = $this->proposal();
        $this->signIn($this->second);
        $this->patchJson($this->prefix.'/partner/plans/'.$id, ['action' => 'accept'])->assertOk();
        $this->patchJson($this->prefix.'/notification-preferences', [...NotificationController::DEFAULTS, 'plan_enabled' => true, 'plan_minutes' => 20])->assertOk();
        $this->travel(40)->minutes();
        $outbox = app(PushOutbox::class);
        $outbox->reminders();
        $outbox->reminders();
        $reminder = DB::table('shiftcal_push_outbox')->where('type', 'reminder')->first();
        $this->assertNotNull($reminder);
        $this->assertSame($this->second->user_id, $reminder->user_id);
        $this->assertSame(1, DB::table('shiftcal_push_outbox')->where('type', 'reminder')->count());
        $this->assertTrue($outbox->valid($reminder));
        $this->patchJson($this->prefix.'/notification-preferences', [...NotificationController::DEFAULTS, 'plan_enabled' => true, 'plan_minutes' => 15])->assertOk();
        $this->assertFalse($outbox->valid($reminder));
        $this->patchJson($this->prefix.'/partner/plans/'.$id, ['action' => 'cancel'])->assertOk();
        $outbox->reminders();
        $this->assertSame(1, DB::table('shiftcal_push_outbox')->where('type', 'reminder')->count());
    }

    public function test_delivery_is_deduplicated_has_private_payload_and_revoked_devices_are_ignored(): void
    {
        $id = $this->proposal();
        $this->signIn($this->second);
        $this->postJson($this->prefix.'/push-device', ['token' => 'token-a', 'enabled' => true, 'locale' => 'tr'])->assertOk();
        $event = DB::table('shiftcal_push_outbox')->where('plan_id', $id)->first();
        $sender = Mockery::mock(FcmSender::class);
        $sender->shouldReceive('configured')->andReturn(true);
        $sender->shouldReceive('send')->once()->withArgs(function (array $message): bool {
            $this->assertStringNotContainsString('Gizli', $message['notification']['body']);
            $this->assertSame((string) $this->second->user_id, $message['data']['account_id']);

            return $message['token'] === 'token-a';
        })->andReturn(['status' => 'sent', 'id' => 'firebase-message']);
        $job = new SendShiftCalPush($event->id);
        $job->handle($sender, app(PushOutbox::class));
        $job->handle($sender, app(PushOutbox::class));
        $this->assertDatabaseHas('shiftcal_push_deliveries', ['status' => 'sent', 'attempts' => 1]);
        DB::table('shiftcal_push_outbox')->where('id', $event->id)->update(['status' => 'pending']);
        DB::table('shiftcal_push_deliveries')->delete();
        $this->device->update(['revoked_at' => now()]);
        $job->handle($sender, app(PushOutbox::class));
        $this->assertDatabaseCount('shiftcal_push_deliveries', 0);
    }

    public function test_disconnection_invalidates_old_events_and_notification_detail_access(): void
    {
        $id = $this->proposal();
        $event = DB::table('shiftcal_push_outbox')->where('plan_id', $id)->first();
        $this->deleteJson($this->prefix.'/partner')->assertNoContent();
        $this->assertFalse(app(PushOutbox::class)->valid($event));
        $this->assertDatabaseHas('shiftcal_push_outbox', ['type' => 'disconnected', 'user_id' => $this->second->user_id]);
        $this->getJson($this->prefix.'/partner/plans/'.$id)->assertNotFound();
    }

    public function test_fcm_http_v1_uses_service_account_oauth_and_marks_unregistered_tokens(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($key, $private);
        $file = tempnam(sys_get_temp_dir(), 'shiftcal-fcm-test-');
        file_put_contents($file, json_encode(['project_id' => 'test-project', 'client_email' => 'test@example.test', 'private_key_id' => 'test-key', 'private_key' => $private]));
        config(['services.shiftcal_fcm.enabled' => true, 'services.shiftcal_fcm.credentials' => $file]);
        Http::preventStrayRequests();
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'oauth-token']),
            'fcm.googleapis.com/*' => Http::response(['error' => ['details' => [['errorCode' => 'UNREGISTERED']]]], 404)]);
        try {
            $result = app(FcmSender::class)->send(['token' => 'invalid-device', 'notification' => ['title' => 'ShiftCal', 'body' => 'Test']]);
            $this->assertSame('invalid_token', $result['status']);
            Http::assertSent(fn ($request): bool => str_contains($request->url(), 'fcm.googleapis.com') && $request->hasHeader('Authorization', 'Bearer oauth-token'));
        } finally {
            unlink($file);
        }
    }
}
