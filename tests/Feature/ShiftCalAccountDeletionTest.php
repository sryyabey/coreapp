<?php

namespace Tests\Feature;

use App\Models\App;
use App\Models\AppUser;
use App\Models\Device;
use App\Models\ShiftCal\CloudBackup;
use App\Models\ShiftCal\Event;
use App\Models\ShiftCal\ShiftTemplate;
use App\Models\ShiftCal\WageSetting;
use App\Models\User;
use App\Services\AppleAuthorizationRevoker;
use App\Services\AppleIdTokenVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ShiftCalAccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private string $url = '/api/v1/apps/shiftcal/shiftcal/account';

    private function signIn(AppUser $membership): string
    {
        $this->app['auth']->forgetGuards();
        $device = Device::factory()->create(['app_id' => $membership->app_id, 'user_id' => $membership->user_id]);
        $token = $membership->user->createToken('phone', ['app:'.$membership->app_id]);
        $token->accessToken->forceFill(['device_id' => $device->id])->save();
        $this->withToken($token->plainTextToken);

        return $token->plainTextToken;
    }

    private function membership(): AppUser
    {
        return AppUser::factory()->create(['app_id' => App::factory()->create(['slug' => 'shiftcal'])->id]);
    }

    private function challenge(): array
    {
        return $this->postJson($this->url.'/deletion-challenge')->assertOk()->json('data');
    }

    public function test_deletion_removes_shiftcal_data_and_all_devices_but_preserves_other_apps_and_users(): void
    {
        $membership = $this->membership();
        $user = $membership->user;
        $otherMembership = AppUser::factory()->create(['user_id' => $user->id]);
        $otherToken = $this->signIn($otherMembership);
        $otherEvent = Event::factory()->create(['app_id' => $otherMembership->app_id, 'user_id' => $user->id]);
        $partner = AppUser::factory()->create(['app_id' => $membership->app_id]);
        $partnerEvent = Event::factory()->create(['app_id' => $membership->app_id, 'user_id' => $partner->user_id]);
        $token2 = $this->signIn($membership);
        $token = $this->signIn($membership);
        foreach ([Event::class, ShiftTemplate::class, WageSetting::class, CloudBackup::class] as $model) {
            $model::factory()->create(['app_id' => $membership->app_id, 'user_id' => $user->id]);
        }
        DB::table('shiftcal_template_applications')->insert(['id' => fake()->uuid(), 'app_id' => $membership->app_id, 'user_id' => $user->id, 'request_hash' => str_repeat('a', 64), 'receipt' => '{}', 'state' => 'applied']);
        $connection = fake()->uuid();
        foreach ([[$user->id, $partner->user_id], [$partner->user_id, $user->id]] as [$a, $b]) {
            DB::table('shiftcal_partner_links')->insert(['app_id' => $membership->app_id, 'user_id' => $a, 'partner_user_id' => $b, 'connection_id' => $connection]);
        }
        DB::table('shiftcal_shared_plans')->insert(['id' => fake()->uuid(), 'app_id' => $membership->app_id, 'proposer_id' => $user->id, 'recipient_id' => $partner->user_id, 'connection_id' => $connection, 'client_request_id' => fake()->uuid(), 'title' => 'Private plan', 'starts_at' => now(), 'ends_at' => now()->addHour(), 'timezone' => 'Europe/Istanbul']);
        DB::table('shiftcal_push_outbox')->insert(['id' => fake()->uuid(), 'app_id' => $membership->app_id, 'user_id' => $partner->user_id, 'event_key' => 'private-plan', 'type' => 'proposal', 'connection_id' => $connection, 'due_at' => now(), 'expires_at' => now()->addHour()]);
        $ticketId = fake()->uuid();
        DB::table('support_tickets')->insert(['id' => $ticketId, 'app_id' => $membership->app_id, 'user_id' => $user->id, 'client_request_id' => fake()->uuid(), 'subject' => 'Private support', 'locale' => 'tr']);
        DB::table('support_messages')->insert(['support_ticket_id' => $ticketId, 'sender_id' => $user->id, 'sender_type' => 'user', 'client_request_id' => fake()->uuid(), 'body' => 'Private text']);
        $challenge = $this->challenge();
        $this->assertFalse($challenge['requires_apple']);
        $this->deleteJson($this->url, ['confirmed' => true, 'challenge_id' => $challenge['challenge_id']])->assertNoContent();
        foreach (['shiftcal_shift_templates', 'shiftcal_wage_settings', 'shiftcal_cloud_backups', 'shiftcal_template_applications', 'shiftcal_partner_links', 'shiftcal_shared_plans', 'shiftcal_push_outbox', 'support_tickets', 'support_messages'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertNotNull(User::find($user->id));
        $this->assertNotNull(Event::find($otherEvent->id));
        $this->assertNotNull(Event::find($partnerEvent->id));
        $this->assertNull(AppUser::find($membership->id));
        $this->assertNotNull(AppUser::find($otherMembership->id));
        $this->assertNull(PersonalAccessToken::findToken($token));
        $this->assertNull(PersonalAccessToken::findToken($token2));
        $this->assertNotNull(PersonalAccessToken::findToken($otherToken));
    }

    public function test_last_membership_deletion_removes_unused_central_identity(): void
    {
        $membership = $this->membership();
        $this->signIn($membership);
        $challenge = $this->challenge();
        $this->deleteJson($this->url, ['confirmed' => true, 'challenge_id' => $challenge['challenge_id']])->assertNoContent();
        $this->assertNull(User::find($membership->user_id));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_confirmation_and_matching_device_challenge_are_required(): void
    {
        $membership = $this->membership();
        $this->signIn($membership);
        $challenge = $this->challenge();
        $this->deleteJson($this->url, ['confirmed' => false, 'challenge_id' => $challenge['challenge_id']])->assertUnprocessable();
        $this->signIn($membership);
        $this->deleteJson($this->url, ['confirmed' => true, 'challenge_id' => $challenge['challenge_id']])->assertUnauthorized();
        $this->assertNotNull(AppUser::find($membership->id));
    }

    public function test_other_user_and_expired_challenges_cannot_delete_an_account(): void
    {
        $membership = $this->membership();
        $other = AppUser::factory()->create(['app_id' => $membership->app_id]);
        $token = $this->signIn($membership);
        $challenge = $this->challenge();
        $this->signIn($other);
        $this->deleteJson($this->url, ['confirmed' => true, 'challenge_id' => $challenge['challenge_id']])->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($token);
        $this->travel(6)->minutes();
        $this->deleteJson($this->url, ['confirmed' => true, 'challenge_id' => $challenge['challenge_id']])->assertUnauthorized();
        $this->assertNotNull(AppUser::find($membership->id));
    }

    public function test_apple_revocation_must_succeed_before_deletion(): void
    {
        $membership = $this->membership();
        $membership->user->forceFill(['apple_id' => 'apple-sub'])->save();
        $this->signIn($membership);
        $this->mock(AppleAuthorizationRevoker::class, function ($mock): void {
            $mock->shouldReceive('assertConfigured')->once();
            $mock->shouldReceive('revoke')->once()->andThrow(new HttpException(503, 'Apple unavailable'));
        });
        $this->mock(AppleIdTokenVerifier::class, fn ($mock) => $mock->shouldReceive('verify')->once()->andReturn(['sub' => 'apple-sub']));
        $challenge = $this->challenge();
        $this->assertTrue($challenge['requires_apple']);
        $this->deleteJson($this->url, ['confirmed' => true, 'challenge_id' => $challenge['challenge_id'], 'id_token' => 'identity', 'authorization_code' => 'code'])->assertServiceUnavailable();
        $this->assertNotNull(AppUser::find($membership->id));
        $this->assertDatabaseCount('devices', 1);
    }

    public function test_apple_identity_must_match_and_success_revokes_before_deleting(): void
    {
        $membership = $this->membership();
        $membership->user->forceFill(['apple_id' => 'apple-sub'])->save();
        $this->signIn($membership);
        $this->mock(AppleAuthorizationRevoker::class, function ($mock): void {
            $mock->shouldReceive('assertConfigured')->once();
            $mock->shouldReceive('revoke')->once()->withArgs(fn ($code, $slug, $subject, $nonce): bool => $code === 'code' && $slug === 'shiftcal' && $subject === 'apple-sub' && strlen($nonce) === 64);
        });
        $this->mock(AppleIdTokenVerifier::class, fn ($mock) => $mock->shouldReceive('verify')->twice()->andReturn(['sub' => 'wrong-sub'], ['sub' => 'apple-sub']));
        $challenge = $this->challenge();
        $body = ['confirmed' => true, 'challenge_id' => $challenge['challenge_id'], 'id_token' => 'identity', 'authorization_code' => 'code'];
        $this->deleteJson($this->url, $body)->assertUnauthorized();
        $this->deleteJson($this->url, $body)->assertNoContent();
        $this->assertNull(User::find($membership->user_id));
    }

    public function test_apple_deletion_requires_server_key_configuration(): void
    {
        $membership = $this->membership();
        $membership->user->forceFill(['apple_id' => 'apple-sub'])->save();
        $this->signIn($membership);
        config(['services.apple_login.private_key_path' => null]);
        $this->postJson($this->url.'/deletion-challenge')->assertServiceUnavailable();
        $this->assertNotNull(AppUser::find($membership->id));
    }

    public function test_anonymous_requests_cannot_delete_accounts(): void
    {
        $this->membership();
        $this->deleteJson($this->url, ['confirmed' => true, 'challenge_id' => fake()->uuid()])->assertUnauthorized();
    }
}
