<?php

namespace Tests\Feature;

use App\Models\App;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppleLoginTest extends TestCase
{
    use RefreshDatabase;

    private string $url;

    private \OpenSSLAsymmetricKey $key;

    private bool $keyServiceFails = false;

    protected function setUp(): void
    {
        parent::setUp();
        $app = App::factory()->create(['slug' => 'shiftcal']);
        $this->url = '/api/v1/apps/'.$app->slug.'/auth/apple';
        config(['services.apple_login.client_ids.shiftcal' => 'com.shiftcal.app']);
        Cache::forget('apple-login-keys');
        $this->key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $rsa = openssl_pkey_get_details($this->key)['rsa'];
        Http::preventStrayRequests();
        Http::fake(['https://appleid.apple.com/auth/keys' => fn () => $this->keyServiceFails
            ? Http::response([], 500)
            : Http::response(['keys' => [['kid' => 'apple-key', 'kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'n' => $this->encode($rsa['n']), 'e' => $this->encode($rsa['e'])]]])]);
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function device(): array
    {
        return ['device_id' => '11111111-1111-4111-8111-111111111111', 'platform' => 'ios', 'device_name' => 'iPhone'];
    }

    private function challenge(): array
    {
        return $this->postJson($this->url.'/challenge', $this->device())->assertOk()->json('data');
    }

    private function token(string $nonce, array $overrides = [], ?\OpenSSLAsymmetricKey $key = null): string
    {
        $claims = array_replace([
            'iss' => 'https://appleid.apple.com', 'aud' => 'com.shiftcal.app', 'sub' => 'apple-user-123',
            'exp' => time() + 3600, 'iat' => time(), 'nonce' => $nonce,
            'email' => 'user@privaterelay.appleid.com', 'email_verified' => 'true',
        ], $overrides);
        $input = $this->encode(json_encode(['alg' => 'RS256', 'kid' => 'apple-key'])).'.'.$this->encode(json_encode($claims));
        openssl_sign($input, $signature, $key ?? $this->key, OPENSSL_ALGO_SHA256);

        return $input.'.'.$this->encode($signature);
    }

    private function payload(array $challenge, array $claims = []): array
    {
        return [...$this->device(), 'challenge_id' => $challenge['challenge_id'], 'id_token' => $this->token($challenge['nonce'], $claims), 'name' => 'Apple User Name'];
    }

    public function test_private_relay_account_is_created_and_subsequent_login_preserves_name(): void
    {
        $response = $this->postJson($this->url, $this->payload($this->challenge()))->assertOk()
            ->assertJsonPath('data.user.email', 'user@privaterelay.appleid.com')->assertJsonPath('data.user.name', 'Apple User Name');
        $this->assertNotEmpty($response->json('data.token'));
        $payload = $this->payload($this->challenge(), ['email' => null]);
        unset($payload['name']);
        $this->postJson($this->url, $payload)->assertOk()->assertJsonPath('data.user.id', $response->json('data.user.id'))->assertJsonPath('data.user.name', 'Apple User Name');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('devices', 1);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertSame('apple-user-123', User::sole()->apple_id);
    }

    public function test_a_login_challenge_can_only_be_used_once(): void
    {
        $payload = $this->payload($this->challenge());
        $this->postJson($this->url, $payload)->assertOk();
        $this->postJson($this->url, $payload)->assertUnauthorized();
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_challenge_is_bound_to_device_and_expires(): void
    {
        $payload = $this->payload($this->challenge());
        $payload['device_id'] = '22222222-2222-4222-8222-222222222222';
        $this->postJson($this->url, $payload)->assertUnauthorized();
        $payload = $this->payload($this->challenge());
        $this->travel(6)->minutes();
        $this->postJson($this->url, $payload)->assertUnauthorized();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_invalid_claims_and_forged_signatures_are_rejected(): void
    {
        foreach ([['nonce' => 'wrong-nonce'], ['aud' => 'other.app'], ['iss' => 'https://attacker.test'], ['exp' => time() - 1], ['iat' => time() + 300], ['sub' => '']] as $index => $claims) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($index + 1)]);
            $this->postJson($this->url, $this->payload($this->challenge(), $claims))->assertUnauthorized();
        }
        $challenge = $this->challenge();
        $payload = $this->payload($challenge);
        $otherKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $payload['id_token'] = $this->token($challenge['nonce'], [], $otherKey);
        $this->postJson($this->url, $payload)->assertUnauthorized();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_existing_email_is_not_silently_linked_to_an_apple_identity(): void
    {
        $user = User::factory()->create(['email' => 'user@privaterelay.appleid.com']);
        $this->postJson($this->url, $this->payload($this->challenge()))->assertUnprocessable()->assertJsonValidationErrors('id_token');
        $this->assertNull($user->fresh()->apple_id);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_new_account_requires_verified_email_but_not_name(): void
    {
        $this->postJson($this->url, $this->payload($this->challenge(), ['email_verified' => false]))->assertUnprocessable();
        $payload = $this->payload($this->challenge(), ['email_verified' => true]);
        unset($payload['name']);
        $this->postJson($this->url, $payload)->assertOk()->assertJsonPath('data.user.name', 'Apple User');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_inactive_membership_cannot_sign_in(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['apple_id' => 'apple-user-123'])->save();
        $user->appMemberships()->create(['app_id' => App::sole()->id, 'is_active' => false]);
        $this->postJson($this->url, $this->payload($this->challenge()))->assertForbidden();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_missing_configuration_and_key_service_failure_create_no_session(): void
    {
        $payload = $this->payload($this->challenge());
        config(['services.apple_login.client_ids.shiftcal' => null]);
        $this->postJson($this->url, $payload)->assertServiceUnavailable();
        config(['services.apple_login.client_ids.shiftcal' => 'com.shiftcal.app']);
        $this->keyServiceFails = true;
        $this->postJson($this->url, $payload)->assertServiceUnavailable();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_android_requests_are_not_accepted_by_native_apple_login(): void
    {
        $this->postJson($this->url.'/challenge', [...$this->device(), 'platform' => 'android'])->assertUnprocessable();
    }
}
