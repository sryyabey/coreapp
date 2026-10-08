<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GoogleIdTokenVerifier
{
    /** @return array<string, mixed> */
    public function verify(string $token, string $appSlug): array
    {
        $clientId = config('services.google_login.client_ids.'.$appSlug);
        abort_unless(is_string($clientId) && $clientId !== '', 503, 'Google girişi henüz yapılandırılmadı.');
        $parts = explode('.', $token);
        abort_unless(count($parts) === 3, 401, 'Geçersiz Google kimliği.');
        $header = json_decode($this->decode($parts[0]), true);
        $claims = json_decode($this->decode($parts[1]), true);
        $signature = $this->decode($parts[2]);
        abort_unless(is_array($header) && is_array($claims) && ($header['alg'] ?? '') === 'RS256' && is_string($header['kid'] ?? null), 401, 'Geçersiz Google kimliği.');
        $certs = $this->certificates();
        if (! isset($certs[$header['kid']])) {
            Cache::forget('google-login-certificates');
            $certs = $this->certificates();
        }
        $cert = $certs[$header['kid']] ?? null;
        abort_unless(is_string($cert) && openssl_verify($parts[0].'.'.$parts[1], $signature, $cert, OPENSSL_ALGO_SHA256) === 1, 401, 'Google imzası geçersiz.');
        abort_unless(
            in_array($claims['iss'] ?? '', ['https://accounts.google.com', 'accounts.google.com'], true)
            && ($claims['aud'] ?? null) === $clientId
            && is_int($claims['exp'] ?? null) && $claims['exp'] > time()
            && is_int($claims['iat'] ?? null) && $claims['iat'] <= time() + 60
            && (! isset($claims['nbf']) || (is_int($claims['nbf']) && $claims['nbf'] <= time() + 60))
            && is_string($claims['sub'] ?? null) && $claims['sub'] !== '' && strlen($claims['sub']) <= 255
            && ($claims['email_verified'] ?? false) === true
            && is_string($claims['email'] ?? null) && strlen($claims['email']) <= 255 && filter_var($claims['email'], FILTER_VALIDATE_EMAIL)
            && (! isset($claims['name']) || is_string($claims['name']))
            && (! isset($claims['hd']) || is_string($claims['hd'])),
            401, 'Google kimliği doğrulanamadı.'
        );

        return $claims;
    }

    private function decode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        abort_unless(is_string($decoded), 401, 'Geçersiz Google kimliği.');

        return $decoded;
    }

    /** @return array<string, string> */
    private function certificates(): array
    {
        $cached = Cache::get('google-login-certificates');
        if (is_array($cached)) {
            return $cached;
        }
        try {
            $response = Http::connectTimeout(5)->timeout(10)->get('https://www.googleapis.com/oauth2/v1/certs');
        } catch (ConnectionException) {
            abort(503, 'Google doğrulama servisine ulaşılamadı.');
        }
        $certs = $response->json();
        abort_unless($response->successful() && is_array($certs) && $certs !== [], 503, 'Google doğrulama anahtarları alınamadı.');
        preg_match('/max-age=(\d+)/', $response->header('Cache-Control') ?? '', $matches);
        Cache::put('google-login-certificates', $certs, min((int) ($matches[1] ?? 300), 3600));

        return $certs;
    }
}
