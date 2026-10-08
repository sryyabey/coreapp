<?php

namespace Tests\Feature;

use App\Models\App;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private bool $keyServiceFails = false;

    private string $url;

    private \OpenSSLAsymmetricKey $key;

    protected function setUp(): void
    {
        parent::setUp();
        $app = App::factory()->create(['slug' => 'shiftcal']);
        $this->url = '/api/v1/apps/'.$app->slug.'/auth/google';
        config(['services.google_login.client_ids.shiftcal' => 'web-client.apps.googleusercontent.com']);
        Cache::forget('google-login-certificates');
        $this->key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        Http::preventStrayRequests();
        Http::fake(['https://www.googleapis.com/oauth2/v1/certs' => fn () => $this->keyServiceFails ? Http::response([], 500) : Http::response(['test-key' => openssl_pkey_get_details($this->key)['key']], 200, ['Cache-Control' => 'max-age=3600'])]);
    }

    private function token(array $overrides = [], ?\OpenSSLAsymmetricKey $key = null): string
    {
        $encode = fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $claims = array_replace([
            'iss' => 'https://accounts.google.com', 'aud' => 'web-client.apps.googleusercontent.com',
            'sub' => 'google-user-123', 'exp' => time() + 3600, 'iat' => time(),
            'email' => 'google@gmail.com', 'email_verified' => true, 'name' => 'Google User',
        ], $overrides);
        $input = $encode(json_encode(['alg' => 'RS256', 'kid' => 'test-key'])).'.'.$encode(json_encode($claims));
        openssl_sign($input, $signature, $key ?? $this->key, OPENSSL_ALGO_SHA256);

        return $input.'.'.$encode($signature);
    }

    private function payload(string $token): array
    {
        return ['id_token' => $token, 'device_id' => '11111111-1111-4111-8111-111111111111', 'platform' => 'android', 'device_name' => 'Android'];
    }

    public function test_google_creates_a_verified_user_and_reuses_the_device_session(): void
    {
        $payload = $this->payload($this->token());
        $response = $this->postJson($this->url, $payload)->assertOk()->assertJsonPath('data.user.email', 'google@gmail.com');
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertNotNull(User::sole()->email_verified_at);
        $this->postJson($this->url, $payload)->assertOk()->assertJsonPath('data.user.id', $response->json('data.user.id'));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('devices', 1);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_authoritative_email_links_existing_user_without_changing_password(): void
    {
        $user = User::factory()->create(['email' => 'google@gmail.com']);
        $password = $user->password;
        $this->postJson($this->url, $this->payload($this->token()))->assertOk();
        $this->assertSame($password, $user->fresh()->password);
        $this->assertSame('google-user-123', $user->fresh()->google_id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_existing_third_party_email_is_not_automatically_linked(): void
    {
        User::factory()->create(['email' => 'someone@example.com']);
        $this->postJson($this->url, $this->payload($this->token(['email' => 'someone@example.com'])))
            ->assertUnprocessable()->assertJsonValidationErrors('id_token');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_google_subject_remains_the_identity_after_email_changes(): void
    {
        $this->postJson($this->url, $this->payload($this->token()))->assertOk();
        $id = User::sole()->id;
        $this->postJson($this->url, $this->payload($this->token(['email' => 'changed@gmail.com'])))
            ->assertOk()->assertJsonPath('data.user.id', $id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_invalid_claims_and_forged_signatures_cannot_create_sessions(): void
    {
        foreach ([['aud' => 'other-client'], ['iss' => 'https://attacker.test'], ['exp' => time() - 1], ['iat' => time() + 300], ['email_verified' => false], ['sub' => ''], ['email' => 'invalid']] as $index => $claims) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($index + 1)]);
            $this->postJson($this->url, $this->payload($this->token($claims)))->assertUnauthorized();
        }
        $otherKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->postJson($this->url, $this->payload($this->token([], $otherKey)))->assertUnauthorized();
        $this->postJson($this->url, $this->payload('not-a-token'))->assertUnauthorized();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_inactive_membership_and_missing_config_are_rejected(): void
    {
        $user = User::factory()->create(['email' => 'google@gmail.com']);
        $user->appMemberships()->create(['app_id' => App::sole()->id, 'is_active' => false]);
        $this->postJson($this->url, $this->payload($this->token()))->assertForbidden();
        $this->assertNull($user->fresh()->google_id);
        config(['services.google_login.client_ids.shiftcal' => null]);
        $this->postJson($this->url, $this->payload($this->token()))->assertServiceUnavailable();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_google_key_service_failure_is_reported_without_a_session(): void
    {
        $this->keyServiceFails = true;
        $this->postJson($this->url, $this->payload($this->token()))->assertServiceUnavailable();
        $this->assertDatabaseCount('users', 0);
    }
}
