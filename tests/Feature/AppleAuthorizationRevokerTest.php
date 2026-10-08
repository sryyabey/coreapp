<?php

namespace Tests\Feature;

use App\Services\AppleAuthorizationRevoker;
use App\Services\AppleIdTokenVerifier;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AppleAuthorizationRevokerTest extends TestCase
{
    private string $keyPath;

    protected function setUp(): void
    {
        parent::setUp();
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($key, $pem);
        $this->keyPath = tempnam(sys_get_temp_dir(), 'apple-revoke-');
        file_put_contents($this->keyPath, $pem);
        config(['services.apple_login' => ['team_id' => 'TEAM123', 'key_id' => 'KEY123', 'private_key_path' => $this->keyPath, 'client_ids' => ['shiftcal' => 'com.shiftcal.app']]]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        unlink($this->keyPath);
        parent::tearDown();
    }

    public function test_code_exchange_is_verified_before_refresh_token_revocation(): void
    {
        $this->mock(AppleIdTokenVerifier::class, fn ($mock) => $mock->shouldReceive('verify')->once()->with('apple-identity', 'shiftcal', 'nonce')->andReturn(['sub' => 'subject']));
        Http::fake([
            'https://appleid.apple.com/auth/token' => Http::response(['id_token' => 'apple-identity', 'refresh_token' => 'refresh-token']),
            'https://appleid.apple.com/auth/revoke' => Http::response('', 200),
        ]);
        app(AppleAuthorizationRevoker::class)->revoke('authorization-code', 'shiftcal', 'subject', 'nonce');
        Http::assertSent(function ($request): bool {
            if ($request->url() !== 'https://appleid.apple.com/auth/token') {
                return false;
            }
            [$header, $payload, $signature] = explode('.', $request['client_secret']);
            $decode = fn (string $value): string => base64_decode(strtr($value, '-_', '+/'));
            $this->assertSame(['alg' => 'ES256', 'kid' => 'KEY123'], json_decode($decode($header), true));
            $claims = json_decode($decode($payload), true);
            $this->assertSame('TEAM123', $claims['iss']);
            $this->assertSame('com.shiftcal.app', $claims['sub']);
            $this->assertSame('https://appleid.apple.com', $claims['aud']);
            $this->assertSame(64, strlen($decode($signature)));

            return $request['code'] === 'authorization-code' && $request['grant_type'] === 'authorization_code';
        });
        Http::assertSent(fn ($request): bool => $request->url() === 'https://appleid.apple.com/auth/revoke' && $request['token'] === 'refresh-token' && $request['token_type_hint'] === 'refresh_token');
    }

    public function test_wrong_subject_never_revokes_a_different_apple_account(): void
    {
        $this->mock(AppleIdTokenVerifier::class, fn ($mock) => $mock->shouldReceive('verify')->once()->andReturn(['sub' => 'another-user']));
        Http::fake(['https://appleid.apple.com/auth/token' => Http::response(['id_token' => 'identity', 'refresh_token' => 'refresh'])]);
        try {
            app(AppleAuthorizationRevoker::class)->revoke('code', 'shiftcal', 'subject', 'nonce');
            $this->fail('Expected mismatched identity to fail');
        } catch (HttpException $error) {
            $this->assertSame(401, $error->getStatusCode());
        }
        Http::assertSentCount(1);
    }

    public function test_failed_revocation_is_not_reported_as_success(): void
    {
        $this->mock(AppleIdTokenVerifier::class, fn ($mock) => $mock->shouldReceive('verify')->once()->andReturn(['sub' => 'subject']));
        Http::fake([
            'https://appleid.apple.com/auth/token' => Http::response(['id_token' => 'identity', 'refresh_token' => 'refresh']),
            'https://appleid.apple.com/auth/revoke' => Http::response([], 500),
        ]);
        $this->expectException(HttpException::class);
        app(AppleAuthorizationRevoker::class)->revoke('code', 'shiftcal', 'subject', 'nonce');
    }
}
