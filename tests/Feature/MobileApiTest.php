<?php

namespace Tests\Feature;

use App\Models\App;
use App\Models\AppUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    private function endpoint(App $app, string $path): string
    {
        return '/api/v1/apps/'.$app->slug.'/'.$path;
    }

    public function test_register_me_and_logout_use_scoped_tokens(): void
    {
        $app = App::factory()->create();
        $response = $this->postJson($this->endpoint($app, 'auth/register'), [
            'name' => 'Mobile User', 'email' => 'MOBILE@example.com', 'password' => 'password123',
            'password_confirmation' => 'password123', 'device_id' => '11111111-1111-4111-8111-111111111111', 'platform' => 'ios', 'device_name' => 'iPhone',
        ])->assertCreated()->assertJsonPath('data.user.email', 'mobile@example.com')->assertJsonMissingPath('data.user.password');
        $token = $response->json('data.token');
        $stored = PersonalAccessToken::findToken($token);
        $this->assertSame(['app:'.$app->id], $stored->abilities);
        $this->assertNotNull($stored->expires_at);
        $this->assertFalse(User::sole()->canAccessPanel(Filament::getPanel('manage')));
        $this->assertCount(0, User::sole()->roles);
        $this->withToken($token)->getJson($this->endpoint($app, 'auth/me'))->assertOk()->assertJsonPath('data.membership.is_active', true);
        $this->withToken($token)->postJson($this->endpoint($app, 'auth/logout'))->assertNoContent();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $stored->id]);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson($this->endpoint($app, 'auth/me'))->assertUnauthorized();
    }

    public function test_login_joins_another_app_without_duplicate_membership(): void
    {
        $user = User::factory()->create();
        $app = App::factory()->create();
        for ($i = 0; $i < 2; $i++) {
            $this->postJson($this->endpoint($app, 'auth/login'), ['email' => $user->email, 'password' => 'password', 'device_id' => '11111111-1111-4111-8111-111111111111', 'platform' => 'ios', 'device_name' => 'Android'])->assertOk();
        }
        $this->assertDatabaseCount('app_users', 1);
        $this->assertNotNull(AppUser::sole()->last_seen_at);
    }

    public function test_other_app_and_wildcard_tokens_are_rejected(): void
    {
        $membership = AppUser::factory()->create();
        $other = App::factory()->create();
        AppUser::factory()->create(['user_id' => $membership->user_id, 'app_id' => $other->id]);
        $token = $membership->user->createToken('phone', ['app:'.$membership->app_id])->plainTextToken;
        $this->withToken($token)->getJson($this->endpoint($other, 'auth/me'))->assertForbidden();
        $this->app['auth']->forgetGuards();
        $wildcard = $membership->user->createToken('general')->plainTextToken;
        $this->withToken($wildcard)->getJson($this->endpoint($membership->app, 'auth/me'))->assertForbidden();
    }

    public function test_inactive_membership_blocks_login_and_existing_token(): void
    {
        $membership = AppUser::factory()->create(['is_active' => false]);
        $token = $membership->user->createToken('phone', ['app:'.$membership->app_id])->plainTextToken;
        $this->withToken($token)->getJson($this->endpoint($membership->app, 'auth/me'))->assertForbidden();
        $this->postJson($this->endpoint($membership->app, 'auth/login'), ['email' => $membership->user->email, 'password' => 'password', 'device_id' => '11111111-1111-4111-8111-111111111111', 'platform' => 'ios', 'device_name' => 'phone'])->assertForbidden();
    }

    public function test_expired_token_and_missing_auth_are_rejected(): void
    {
        $membership = AppUser::factory()->create();
        $url = $this->endpoint($membership->app, 'auth/me');
        $this->getJson($url)->assertUnauthorized();
        $token = $membership->user->createToken('phone', ['app:'.$membership->app_id], now()->subMinute())->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson($url)->assertUnauthorized();
    }

    public function test_unknown_and_inactive_apps_are_closed(): void
    {
        $app = App::factory()->create(['is_active' => false]);
        $this->getJson($this->endpoint($app, 'meta'))->assertNotFound();
        $this->getJson('/api/v1/apps/unknown/meta')->assertNotFound();
    }

    public function test_bad_credentials_and_rate_limit(): void
    {
        $app = App::factory()->create();
        $payload = ['email' => 'missing@example.com', 'password' => 'bad', 'device_id' => '11111111-1111-4111-8111-111111111111', 'platform' => 'ios', 'device_name' => 'phone'];
        for ($i = 0; $i < 5; $i++) {
            $this->postJson($this->endpoint($app, 'auth/login'), $payload)->assertUnprocessable()->assertJsonValidationErrors('email');
        }
        $this->postJson($this->endpoint($app, 'auth/login'), $payload)->assertStatus(429);
    }

    public function test_registration_validates_and_meta_exposes_only_public_fields(): void
    {
        $app = App::factory()->create();
        $this->postJson($this->endpoint($app, 'auth/register'), ['email' => 'bad'])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password', 'device_name']);
        $this->getJson($this->endpoint($app, 'meta'))->assertOk()->assertJsonPath('data.slug', $app->slug)->assertJsonMissingPath('data.created_at');
    }
}
