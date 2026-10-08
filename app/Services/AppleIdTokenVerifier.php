<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AppleIdTokenVerifier
{
    /** @return array<string, mixed> */
    public function verify(string $token, string $appSlug, string $nonce): array
    {
        $clientId = config('services.apple_login.client_ids.'.$appSlug);
        abort_unless(is_string($clientId) && $clientId !== '', 503, 'Apple girişi henüz yapılandırılmadı.');
        $parts = explode('.', $token);
        abort_unless(count($parts) === 3, 401, 'Geçersiz Apple kimliği.');
        $header = json_decode($this->decode($parts[0]), true);
        $claims = json_decode($this->decode($parts[1]), true);
        abort_unless(is_array($header) && is_array($claims) && ($header['alg'] ?? '') === 'RS256' && is_string($header['kid'] ?? null), 401, 'Geçersiz Apple kimliği.');
        $keys = $this->keys();
        $key = collect($keys)->firstWhere('kid', $header['kid']);
        if (! $key) {
            Cache::forget('apple-login-keys');
            $key = collect($this->keys())->firstWhere('kid', $header['kid']);
        }
        abort_unless(is_array($key) && ($key['kty'] ?? '') === 'RSA' && ($key['alg'] ?? '') === 'RS256' && ($key['use'] ?? '') === 'sig' && is_string($key['n'] ?? null) && is_string($key['e'] ?? null), 401, 'Apple imza anahtarı geçersiz.');
        $rsa = $this->der(0x30, $this->integer($this->decode($key['n'])).$this->integer($this->decode($key['e'])));
        $algorithm = hex2bin('300d06092a864886f70d0101010500');
        $publicKey = $this->der(0x30, $algorithm.$this->der(0x03, "\0".$rsa));
        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($publicKey), 64, "\n")."-----END PUBLIC KEY-----\n";
        abort_unless(openssl_verify($parts[0].'.'.$parts[1], $this->decode($parts[2]), $pem, OPENSSL_ALGO_SHA256) === 1, 401, 'Apple imzası geçersiz.');
        abort_unless(
            ($claims['iss'] ?? '') === 'https://appleid.apple.com'
            && ($claims['aud'] ?? null) === $clientId
            && is_int($claims['exp'] ?? null) && $claims['exp'] > time()
            && is_int($claims['iat'] ?? null) && $claims['iat'] <= time() + 60
            && (! isset($claims['nbf']) || (is_int($claims['nbf']) && $claims['nbf'] <= time() + 60))
            && is_string($claims['sub'] ?? null) && $claims['sub'] !== '' && strlen($claims['sub']) <= 255
            && is_string($claims['nonce'] ?? null) && hash_equals($nonce, $claims['nonce'])
            && (! isset($claims['email']) || (is_string($claims['email']) && strlen($claims['email']) <= 255 && filter_var($claims['email'], FILTER_VALIDATE_EMAIL))),
            401, 'Apple kimliği doğrulanamadı.'
        );

        return $claims;
    }

    private function decode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        abort_unless(is_string($decoded), 401, 'Geçersiz Apple kimliği.');

        return $decoded;
    }

    private function integer(string $bytes): string
    {
        $bytes = ltrim($bytes, "\0");
        abort_unless($bytes !== '', 401, 'Apple imza anahtarı geçersiz.');
        if (ord($bytes[0]) & 0x80) {
            $bytes = "\0".$bytes;
        }

        return $this->der(0x02, $bytes);
    }

    private function der(int $tag, string $value): string
    {
        $length = strlen($value);
        if ($length < 128) {
            return chr($tag).chr($length).$value;
        }
        $encodedLength = ltrim(pack('N', $length), "\0");

        return chr($tag).chr(0x80 | strlen($encodedLength)).$encodedLength.$value;
    }

    /** @return array<int, array<string, mixed>> */
    private function keys(): array
    {
        $cached = Cache::get('apple-login-keys');
        if (is_array($cached)) {
            return $cached;
        }
        try {
            $response = Http::connectTimeout(5)->timeout(10)->get('https://appleid.apple.com/auth/keys');
        } catch (ConnectionException) {
            abort(503, 'Apple doğrulama servisine ulaşılamadı.');
        }
        $keys = $response->json('keys');
        abort_unless($response->successful() && is_array($keys) && $keys !== [], 503, 'Apple doğrulama anahtarları alınamadı.');
        Cache::put('apple-login-keys', $keys, 3600);

        return $keys;
    }
}
