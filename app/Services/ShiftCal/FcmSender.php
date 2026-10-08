<?php

namespace App\Services\ShiftCal;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FcmSender
{
    public function configured(?string $appSlug = null): bool
    {
        $settings = $this->settings($appSlug);

        return ($settings['enabled'] ?? false) && is_file((string) ($settings['credentials'] ?? ''));
    }

    /** @param array<string, mixed> $message @return array{status: string, id?: string} */
    public function send(array $message, ?string $appSlug = null): array
    {
        $credentials = json_decode(file_get_contents($this->settings($appSlug)['credentials']), true, flags: JSON_THROW_ON_ERROR);
        $project = $credentials['project_id'];
        $response = Http::withToken($this->accessToken($credentials))->timeout(15)->post('https://fcm.googleapis.com/v1/projects/'.rawurlencode($project).'/messages:send', ['message' => $message]);
        if ($response->successful()) {
            return ['status' => 'sent', 'id' => $response->json('name')];
        }
        $codes = collect($response->json('error.details', []))->pluck('errorCode');
        if ($codes->contains('UNREGISTERED')) {
            return ['status' => 'invalid_token'];
        }
        if ($response->status() === 401) {
            Cache::forget($this->cacheKey($credentials));
        }
        throw new RuntimeException('FCM_HTTP_'.$response->status());
    }

    /** @return array<string, mixed> */
    private function settings(?string $appSlug): array
    {
        if ($appSlug === null) {
            return config('services.shiftcal_fcm', []);
        }
        $apps = config('support.fcm.apps', []);

        return $apps[$appSlug] ?? ($appSlug === 'shiftcal' ? config('services.shiftcal_fcm', []) : []);
    }

    /** @param array<string, mixed> $credentials */
    private function cacheKey(array $credentials): string
    {
        return 'shiftcal:fcm:oauth:'.hash('sha256', $credentials['client_email'].$credentials['private_key_id']);
    }

    /** @param array<string, mixed> $credentials */
    private function accessToken(array $credentials): string
    {
        return Cache::remember($this->cacheKey($credentials), 3000, function () use ($credentials): string {
            $encode = fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
            $header = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
            $claims = $encode(json_encode(['iss' => $credentials['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging', 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => time(), 'exp' => time() + 3600], JSON_THROW_ON_ERROR));
            $unsigned = $header.'.'.$claims;
            if (! openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('FCM_SIGNING_FAILED');
            }
            $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $unsigned.'.'.$encode($signature)]);
            if (! $response->successful() || ! is_string($response->json('access_token'))) {
                throw new RuntimeException('FCM_AUTH_FAILED');
            }

            return $response->json('access_token');
        });
    }
}
