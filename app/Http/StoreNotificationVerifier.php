<?php

namespace App\Http;

use App\Models\StoreApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class StoreNotificationVerifier
{
    private function parts(string $jwt): array
    {
        $parts = explode('.', $jwt);
        abort_unless(count($parts) === 3, 401, 'Geçersiz bildirim imzası.');
        $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/'), true) ?: '', true);
        $body = json_decode(base64_decode(strtr($parts[1], '-_', '+/'), true) ?: '', true);
        $signature = base64_decode(strtr($parts[2], '-_', '+/'), true);
        abort_unless(is_array($header) && is_array($body) && is_string($signature), 401, 'Geçersiz bildirim imzası.');

        return [$header, $body, $signature, $parts[0].'.'.$parts[1]];
    }

    public function appleJws(string $jwt): array
    {
        [$header, $body, $signature, $input] = $this->parts($jwt);
        $roots = config('billing.apple.root_ca_paths', []);
        abort_unless(count($roots) > 0 && collect($roots)->every(fn (string $path): bool => is_readable($path)), 503, 'Apple kök sertifikaları yapılandırılmadı.');
        abort_unless(($header['alg'] ?? '') === 'ES256' && count($header['x5c'] ?? []) === 3 && strlen($signature) === 64, 401, 'Geçersiz Apple imzası.');
        $certs = array_map(fn (string $cert): string => "-----BEGIN CERTIFICATE-----\n".chunk_split($cert, 64, "\n")."-----END CERTIFICATE-----\n", $header['x5c']);
        $leaf = openssl_x509_read($certs[0]);
        $intermediate = openssl_x509_read($certs[1]);
        abort_unless($leaf && $intermediate, 401, 'Geçersiz Apple sertifikası.');
        $leafInfo = openssl_x509_parse($leaf);
        $intermediateInfo = openssl_x509_parse($intermediate);
        abort_unless(isset($leafInfo['extensions']['1.2.840.113635.100.6.11.1'], $intermediateInfo['extensions']['1.2.840.113635.100.6.2.1']), 401, 'Apple imzalama sertifikası gerekli.');
        $untrusted = tmpfile();
        abort_unless($untrusted, 503, 'Sertifika doğrulaması yapılamadı.');
        try {
            fwrite($untrusted, $certs[1]);
            abort_unless(openssl_x509_checkpurpose($leaf, X509_PURPOSE_ANY, $roots, stream_get_meta_data($untrusted)['uri']) === true, 401, 'Apple sertifika zinciri geçersiz.');
        } finally {
            fclose($untrusted);
        }
        $integer = function (string $value): string {
            $value = ltrim($value, "\0");
            if ($value === '' || (ord($value[0]) & 0x80)) {
                $value = "\0".$value;
            }

            return "\x02".chr(strlen($value)).$value;
        };
        $der = $integer(substr($signature, 0, 32)).$integer(substr($signature, 32));
        abort_unless(openssl_verify($input, "\x30".chr(strlen($der)).$der, $leaf, OPENSSL_ALGO_SHA256) === 1, 401, 'Apple bildirim imzası geçersiz.');

        return $body;
    }

    public function apple(Request $request, StoreApp $store): array
    {
        $request->validate(['signedPayload' => ['required', 'string', 'max:100000']]);
        $body = $this->appleJws($request->input('signedPayload'));
        $data = $body['data'] ?? $body['summary'] ?? [];
        $environment = $data['environment'] ?? '';
        abort_unless(($data['bundleId'] ?? '') === $store->identifier && in_array($environment, ['Production', 'Sandbox'], true), 422, 'Bildirim uygulaması eşleşmiyor.');
        BillingEnvironment::assertAllowed($store, strtolower($environment));
        abort_if($environment === 'Production' && (! $store->apple_app_id || (string) ($data['appAppleId'] ?? '') !== $store->apple_app_id), 422, 'Apple App ID eşleşmiyor.');
        $transaction = isset($data['signedTransactionInfo']) ? $this->appleJws($data['signedTransactionInfo']) : [];
        if ($transaction) {
            abort_unless(($transaction['bundleId'] ?? '') === $store->identifier && ($transaction['environment'] ?? '') === $environment, 422, 'İşlem uygulaması eşleşmiyor.');
        }
        $id = $body['notificationUUID'] ?? null;
        abort_unless(is_string($id) && strlen($id) <= 100, 422, 'Bildirim kimliği gerekli.');

        return ['event_id' => $id, 'type' => $body['notificationType'] ?? 'UNKNOWN', 'environment' => strtolower($environment), 'identity' => (string) ($transaction['originalTransactionId'] ?? ''), 'proof' => (string) ($transaction['transactionId'] ?? ''), 'test' => ($body['notificationType'] ?? '') === 'TEST'];
    }

    public function google(Request $request, StoreApp $store): array
    {
        $settings = config('billing.apps.'.$store->app->slug.'.google', config('billing.google'));
        abort_unless(! empty($settings['push_audience']) && ! empty($settings['push_service_account_email']) && ! empty($settings['push_subscription']), 503, 'Google Pub/Sub doğrulaması yapılandırılmadı.');
        [$header, $claims, $signature, $input] = $this->parts($request->bearerToken() ?? '');
        abort_unless(($header['alg'] ?? '') === 'RS256' && is_string($header['kid'] ?? null), 401, 'Geçersiz Google imzası.');
        $certs = Cache::remember('google-pubsub-certs', 1800, function (): array {
            $response = Http::connectTimeout(5)->timeout(10)->get('https://www.googleapis.com/oauth2/v1/certs');
            abort_unless($response->successful() && is_array($response->json()), 503, 'Google imza anahtarları alınamadı.');

            return $response->json();
        });
        $cert = $certs[$header['kid']] ?? null;
        abort_unless($cert && openssl_verify($input, $signature, $cert, OPENSSL_ALGO_SHA256) === 1, 401, 'Google bildirim imzası geçersiz.');
        abort_unless(in_array($claims['iss'] ?? '', ['https://accounts.google.com', 'accounts.google.com'], true) && ($claims['aud'] ?? '') === $settings['push_audience'] && ($claims['email'] ?? '') === $settings['push_service_account_email'] && ($claims['email_verified'] ?? false) === true && ($claims['exp'] ?? 0) > time() && ($claims['iat'] ?? PHP_INT_MAX) <= time() + 60, 401, 'Google bildirim kimliği geçersiz.');
        $request->validate(['message.messageId' => ['required', 'string', 'max:100'], 'message.data' => ['required', 'string', 'max:100000'], 'subscription' => ['required', 'string']]);
        abort_unless($request->input('subscription') === $settings['push_subscription'], 401, 'Google abonelik kaynağı geçersiz.');
        $body = json_decode(base64_decode($request->input('message.data'), true) ?: '', true);
        abort_unless(is_array($body) && ($body['packageName'] ?? '') === $store->identifier, 422, 'Bildirim uygulaması eşleşmiyor.');
        $notification = $body['subscriptionNotification'] ?? $body['voidedPurchaseNotification'] ?? [];
        $proof = $notification['purchaseToken'] ?? '';
        $test = isset($body['testNotification']);
        abort_unless($test || (is_string($proof) && $proof !== ''), 422, 'Abonelik satın alma tokenı gerekli.');

        return ['event_id' => $request->input('message.messageId'), 'type' => isset($body['voidedPurchaseNotification']) ? 'VOIDED_PURCHASE' : (string) ($notification['notificationType'] ?? 'TEST'), 'environment' => 'unknown', 'identity' => $test ? '' : hash('sha256', $proof), 'proof' => $proof, 'test' => $test];
    }
}
