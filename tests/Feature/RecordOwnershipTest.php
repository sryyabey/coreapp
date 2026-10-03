<?php

namespace Tests\Feature;

use App\Models\App;
use App\Models\AppUser;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function authenticate(): Device
    {
        $membership = AppUser::factory()->create();
        $device = Device::factory()->create(['app_id' => $membership->app_id, 'user_id' => $membership->user_id]);
        $token = $device->user->createToken('phone', ['app:'.$device->app_id]);
        $token->accessToken->forceFill(['device_id' => $device->id])->save();
        $this->withToken($token->plainTextToken);

        return $device;
    }

    public function test_only_current_user_and_app_devices_are_listed(): void
    {
        $current = $this->authenticate();
        $own = Device::factory()->create(['app_id' => $current->app_id, 'user_id' => $current->user_id]);
        Device::factory()->create(['app_id' => $current->app_id]);
        Device::factory()->create(['user_id' => $current->user_id]);
        $response = $this->getJson('/api/v1/apps/'.$current->app->slug.'/devices?user_id=999&app_id=999')->assertOk()->assertJsonCount(2, 'data');
        $this->assertEqualsCanonicalizing([$current->id, $own->id], array_column($response->json('data'), 'id'));
        $this->getJson('/api/v1/apps/'.$current->app->slug.'/devices/'.$current->id)->assertOk()->assertJsonPath('data.is_current', true);
    }

    public function test_foreign_records_cannot_be_read_or_revoked(): void
    {
        $current = $this->authenticate();
        $foreignUser = Device::factory()->create(['app_id' => $current->app_id]);
        $foreignApp = Device::factory()->create(['user_id' => $current->user_id]);
        foreach ([$foreignUser, $foreignApp] as $foreign) {
            $url = '/api/v1/apps/'.$current->app->slug.'/devices/'.$foreign->id;
            $this->getJson($url)->assertNotFound();
            $this->deleteJson($url)->assertNotFound();
            $this->assertNull($foreign->fresh()->revoked_at);
        }
    }

    public function test_own_session_can_be_revoked_without_affecting_current_session(): void
    {
        $current = $this->authenticate();
        $other = Device::factory()->create(['app_id' => $current->app_id, 'user_id' => $current->user_id]);
        $token = $other->user->createToken('other', ['app:'.$other->app_id]);
        $token->accessToken->forceFill(['device_id' => $other->id])->save();
        $this->deleteJson('/api/v1/apps/'.$current->app->slug.'/devices/'.$other->id)->assertNoContent();
        $this->assertNotNull($other->fresh()->revoked_at);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->getJson('/api/v1/apps/'.$current->app->slug.'/auth/me')->assertOk();
    }

    public function test_missing_membership_blocks_valid_device_and_has_no_activity_side_effect(): void
    {
        $current = $this->authenticate();
        AppUser::query()->delete();
        $this->getJson('/api/v1/apps/'.$current->app->slug.'/devices')->assertForbidden();
        $this->assertNull($current->fresh()->last_seen_at);
    }

    public function test_body_cannot_choose_record_owner_or_application(): void
    {
        $app = App::factory()->create();
        $this->postJson('/api/v1/apps/'.$app->slug.'/auth/register', [
            'name' => 'User', 'email' => 'new@example.com', 'password' => 'password123', 'password_confirmation' => 'password123',
            'device_name' => 'phone', 'device_id' => fake()->uuid(), 'platform' => 'ios', 'app_id' => 1, 'user_id' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors(['app_id', 'user_id']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_app_token_cannot_use_another_app_device_endpoint(): void
    {
        $current = $this->authenticate();
        $other = App::factory()->create();
        $this->getJson('/api/v1/apps/'.$other->slug.'/devices')->assertForbidden();
    }
}
