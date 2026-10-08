<?php

namespace App\Services;

use App\Http\StoreJwt;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class AppleAuthorizationRevoker
{
    public function assertConfigured(string $appSlug): void
    {
        $settings = config('services.apple_login');
        abort_unless(! empty($settings['team_id']) && ! empty($settings['key_id']) && ! empty($settings['private_key_path']) && is_readable($settings['private_key_path']) && ! empty($settings['client_ids'][$appSlug]), 503, 'Apple hesap silme anahtarı sunucuda henüz yapılandırılmadı.');
    }

    public function revoke(string $code, string $appSlug, string $subject, string $nonce): void
    {
        $this->assertConfigured($appSlug);
        $settings = config('services.apple_login');
        $clientId = $settings['client_ids'][$appSlug];
        $secret = StoreJwt::encode(['alg' => 'ES256', 'kid' => $settings['key_id']], [
            'iss' => $settings['team_id'], 'iat' => time(), 'exp' => time() + 300,
            'aud' => 'https://appleid.apple.com', 'sub' => $clientId,
        ], file_get_contents($settings['private_key_path']));
        try {
            $response = Http::asForm()->connectTimeout(5)->timeout(10)->post('https://appleid.apple.com/auth/token', [
                'client_id' => $clientId, 'client_secret' => $secret, 'code' => $code, 'grant_type' => 'authorization_code',
            ]);
            abort_unless($response->successful(), 502, 'Apple yetkisi doğrulanamadı. Tekrar deneyin.');
            $identity = $response->json('id_token');
            abort_unless(is_string($identity), 502, 'Apple doğrulama yanıtı geçersiz.');
            $claims = app(AppleIdTokenVerifier::class)->verify($identity, $appSlug, $nonce);
            abort_unless(hash_equals($subject, $claims['sub']), 401, 'Apple hesabı mevcut hesapla eşleşmiyor.');
            $refreshToken = $response->json('refresh_token');
            abort_unless(is_string($refreshToken) && $refreshToken !== '', 502, 'Apple yetkisi iptal edilemedi.');
            $revoked = Http::asForm()->connectTimeout(5)->timeout(10)->post('https://appleid.apple.com/auth/revoke', [
                'client_id' => $clientId, 'client_secret' => $secret, 'token' => $refreshToken, 'token_type_hint' => 'refresh_token',
            ]);
            abort_unless($revoked->successful(), 502, 'Apple yetkisi iptal edilemedi. Tekrar deneyin.');
        } catch (ConnectionException) {
            abort(503, 'Apple servisine ulaşılamadı. Hesabınız silinmedi.');
        }
    }
}
