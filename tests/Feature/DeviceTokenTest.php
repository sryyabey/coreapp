<?php

namespace Tests\Feature;

use App\Models\App;
use App\Models\AppUser;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class DeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    private function login(App $app, User $user, string $deviceId): string
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/v1/apps/'.$app->slug.'/auth/login', [
            'email' => $user->email, 'password' => 'password', 'device_name' => 'Phone',
            'device_id' => $deviceId, 'platform' => 'ios',
        ])->assertOk()->json('data.token');
    }

    public function test_relogin_rotates_only_current_app_device_token(): void
    {
        $user = User::factory()->create();
        $app = App::factory()->create();
        $other = App::factory()->create();
        $id = fake()->uuid();
        $first = $this->login($app, $user, $id);
        $secondDevice = $this->login($app, $user, fake()->uuid());
        $otherApp = $this->login($other, $user, $id);
        $replacement = $this->login($app, $user, $id);
        $this->assertNull(PersonalAccessToken::findToken($first));
        $this->assertNotNull(PersonalAccessToken::findToken($secondDevice));
        $this->assertNotNull(PersonalAccessToken::findToken($otherApp));
        $this->assertNotNull(PersonalAccessToken::findToken($replacement)->device_id);
        $this->assertDatabaseCount('devices', 3);
        $this->app['auth']->forgetGuards();
        $this->withToken($first)->getJson('/api/v1/apps/'.$app->slug.'/auth/me')->assertUnauthorized();
    }

    public function test_revoked_or_mismatched_device_blocks_token(): void
    {
        $membership = AppUser::factory()->create();
        $device = Device::factory()->create(['app_id' => $membership->app_id, 'user_id' => $membership->user_id, 'revoked_at' => now()]);
        $token = $membership->user->createToken('phone', ['app:'.$membership->app_id]);
        $token->accessToken->forceFill(['device_id' => $device->id])->save();
        $this->withToken($token->plainTextToken)->getJson('/api/v1/apps/'.$membership->app->slug.'/auth/me')->assertForbidden();
        $device->update(['revoked_at' => null, 'user_id' => User::factory()->create()->id]);
        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)->getJson('/api/v1/apps/'.$membership->app->slug.'/auth/me')->assertForbidden();
    }

    public function test_logout_revokes_device_and_keeps_other_device_signed_in(): void
    {
        $user = User::factory()->create();
        $app = App::factory()->create();
        $first = $this->login($app, $user, fake()->uuid());
        $second = $this->login($app, $user, fake()->uuid());
        $deviceId = PersonalAccessToken::findToken($first)->device_id;
        $this->app['auth']->forgetGuards();
        $this->withToken($first)->postJson('/api/v1/apps/'.$app->slug.'/auth/logout')->assertNoContent();
        $this->assertNotNull(Device::findOrFail($deviceId)->revoked_at);
        $this->app['auth']->forgetGuards();
        $this->withToken($second)->getJson('/api/v1/apps/'.$app->slug.'/auth/me')->assertOk();
    }

    public function test_device_identity_is_required(): void
    {
        $app = App::factory()->create();
        $this->postJson('/api/v1/apps/'.$app->slug.'/auth/login', ['email' => 'user@example.com', 'password' => 'password', 'device_name' => 'phone'])
            ->assertUnprocessable()->assertJsonValidationErrors(['device_id', 'platform']);
    }
}
