<?php

namespace App\Http;

use App\Models\AppUser;
use App\Models\StoreApp;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StoreVerifier
{
    public function verify(StoreApp $store, AppUser $membership, array $input): array
    {
        try {
            return $store->platform === 'ios' ? $this->apple($store, $membership, $input) : $this->google($store, $membership, $input);
        } catch (ConnectionException $exception) {
            abort(503, 'Mağazaya şu anda ulaşılamıyor.');
        }
    }

    private function credentials(StoreApp $store, string $provider): array
    {
        return config('billing.apps.'.$store->app->slug.'.'.$provider, config('billing.'.$provider));
    }

    private function key(string $path): string
    {
        abort_unless($path !== '' && is_readable($path), 503, 'Mağaza erişim bilgileri henüz yapılandırılmadı.');

        return file_get_contents($path);
    }

    private function json(Response $response): array
    {
        if (! $response->successful()) {
            Log::warning('billing.store_request_failed', [
                'http_status' => $response->status(),
                'error_code' => $response->json('errorCode') ?? $response->json('error.code'),
                'error_status' => $response->json('error.status'),
                'error_reason' => $response->json('error.errors.0.reason') ?? $response->json('error.details.0.reason'),
            ]);
        }
        if (in_array($response->status(), [400, 404, 410], true)) {
            throw ValidationException::withMessages(['purchase' => 'Satın alma mağazada doğrulanamadı.']);
        }
        abort_unless($response->successful(), 503, 'Mağaza doğrulaması tamamlanamadı.');
        $body = $response->json();
        abort_unless(is_array($body), 502, 'Geçersiz mağaza yanıtı.');

        return $body;
    }

    private function owner(?string $actual, string $expected): void
    {
        abort_unless(is_string($actual) && hash_equals(strtolower($expected), strtolower($actual)), 403, 'Satın alma bu kullanıcıya ait değil.');
    }

    private function apple(StoreApp $store, AppUser $membership, array $input): array
    {
        $credentials = $this->credentials($store, 'apple');
        abort_unless(! empty($credentials['issuer_id']) && ! empty($credentials['key_id']), 503, 'Apple erişim bilgileri henüz yapılandırılmadı.');
        $jwt = StoreJwt::encode(['alg' => 'ES256', 'kid' => $credentials['key_id'], 'typ' => 'JWT'], [
            'iss' => $credentials['issuer_id'], 'iat' => time(), 'exp' => time() + 300, 'aud' => 'appstoreconnect-v1', 'bid' => $store->identifier,
        ], $this->key($credentials['private_key_path'] ?? ''));
        $sandbox = ($input['environment'] ?? 'production') === 'sandbox';
        BillingEnvironment::assertAllowed($store, $sandbox ? 'sandbox' : 'production');
        $base = $sandbox ? 'https://api.storekit-sandbox.itunes.apple.com' : 'https://api.storekit.itunes.apple.com';
        $client = Http::withToken($jwt)->acceptJson()->connectTimeout(5)->timeout(15);
        $info = $this->json($client->get($base.'/inApps/v1/transactions/'.rawurlencode($input['transaction_id'])));
        $transaction = StoreJwt::appleResponsePayload($info['signedTransactionInfo'] ?? '');
        $this->owner($transaction['appAccountToken'] ?? null, $membership->billing_account_id);
        abort_unless(($transaction['bundleId'] ?? null) === $store->identifier && ($transaction['environment'] ?? null) === ($sandbox ? 'Sandbox' : 'Production'), 422, 'Apple uygulama veya ortam eşleşmesi geçersiz.');
        $original = (string) ($transaction['originalTransactionId'] ?? '');
        abort_if($original === '', 502, 'Apple işlem kimliği eksik.');
        $statuses = $this->json($client->get($base.'/inApps/v1/subscriptions/'.rawurlencode($original)));
        $latest = null;
        foreach ($statuses['data'] ?? [] as $group) {
            foreach ($group['lastTransactions'] ?? [] as $item) {
                if ((string) ($item['originalTransactionId'] ?? '') === $original) {
                    $latest = $item;
                }
            }
        }
        abort_unless($latest, 422, 'Apple aboneliği bulunamadı.');
        $tx = StoreJwt::appleResponsePayload($latest['signedTransactionInfo'] ?? '');
        $this->owner($tx['appAccountToken'] ?? null, $membership->billing_account_id);
        abort_unless(($tx['bundleId'] ?? null) === $store->identifier && ($tx['environment'] ?? null) === ($sandbox ? 'Sandbox' : 'Production') && ($tx['type'] ?? null) === 'Auto-Renewable Subscription', 422, 'Apple abonelik eşleşmesi geçersiz.');
        $expiry = CarbonImmutable::createFromTimestampMs($tx['expiresDate'] ?? 0);
        $renewal = ! empty($latest['signedRenewalInfo']) ? StoreJwt::appleResponsePayload($latest['signedRenewalInfo']) : [];
        $status = [1 => 'active', 2 => 'expired', 3 => 'billing_retry', 4 => 'grace', 5 => 'revoked'][$latest['status'] ?? 0] ?? 'unknown';
        if ($status === 'grace') {
            $renewal = StoreJwt::appleResponsePayload($latest['signedRenewalInfo'] ?? '');
            $expiry = CarbonImmutable::createFromTimestampMs($renewal['gracePeriodExpiresDate'] ?? 0);
        }
        if ($status === 'active' && ! empty($latest['signedRenewalInfo'])) {
            $renewal = StoreJwt::appleResponsePayload($latest['signedRenewalInfo']);
            if (($renewal['autoRenewStatus'] ?? null) === 0) {
                $status = 'canceled';
            }
        }
        if (isset($tx['revocationDate'])) {
            $status = 'revoked';
        }

        return ['identity' => $original, 'product_id' => $tx['productId'] ?? '', 'base_plan_id' => '', 'environment' => $sandbox ? 'sandbox' : 'production', 'status' => $status, 'expires_at' => $expiry, 'active' => in_array($status, ['active', 'grace', 'canceled'], true) && $expiry->isFuture(), 'proof' => $input['transaction_id'], 'is_trial' => ($tx['offerType'] ?? null) === 1 && ($tx['offerDiscountType'] ?? null) === 'FREE_TRIAL', 'auto_renews' => isset($renewal['autoRenewStatus']) ? $renewal['autoRenewStatus'] === 1 : null];
    }

    private function google(StoreApp $store, AppUser $membership, array $input): array
    {
        $credentials = $this->credentials($store, 'google');
        $account = json_decode($this->key($credentials['service_account_path'] ?? ''), true);
        abort_unless(! empty($account['client_email']) && ! empty($account['private_key']), 503, 'Google erişim bilgileri geçersiz.');
        $assertion = StoreJwt::encode(['alg' => 'RS256', 'typ' => 'JWT'], ['iss' => $account['client_email'], 'scope' => 'https://www.googleapis.com/auth/androidpublisher', 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => time(), 'exp' => time() + 3600], $account['private_key']);
        $oauth = $this->json(Http::asForm()->connectTimeout(5)->timeout(15)->post('https://oauth2.googleapis.com/token', ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $assertion]));
        abort_unless(! empty($oauth['access_token']), 503, 'Google erişim tokenı alınamadı.');
        $client = Http::withToken($oauth['access_token'])->acceptJson()->connectTimeout(5)->timeout(15);
        $path = 'https://androidpublisher.googleapis.com/androidpublisher/v3/applications/'.rawurlencode($store->identifier);
        $token = $input['purchase_token'];
        $body = $this->json($client->get($path.'/purchases/subscriptionsv2/tokens/'.rawurlencode($token)));
        $this->owner($body['externalAccountIdentifiers']['obfuscatedExternalAccountId'] ?? null, hash('sha256', $membership->billing_account_id));
        $sandbox = array_key_exists('testPurchase', $body);
        BillingEnvironment::assertAllowed($store, $sandbox ? 'sandbox' : 'production');
        abort_if(isset($input['environment']) && $input['environment'] !== ($sandbox ? 'sandbox' : 'production'), 422, 'İstenen ortam mağaza yanıtıyla eşleşmiyor.');
        $items = array_values(array_filter($body['lineItems'] ?? [], fn (array $item): bool => isset($item['expiryTime'], $item['productId']) && empty($item['deferredItemReplacement'])));
        usort($items, fn (array $a, array $b): int => CarbonImmutable::parse($b['expiryTime'])->getTimestamp() <=> CarbonImmutable::parse($a['expiryTime'])->getTimestamp());
        $item = $items[0] ?? null;
        abort_unless($item, 422, 'Doğrulanabilir abonelik kalemi bulunamadı.');
        $expiry = CarbonImmutable::parse($item['expiryTime']);
        $status = match ($body['subscriptionState'] ?? '') {
            'SUBSCRIPTION_STATE_ACTIVE' => 'active', 'SUBSCRIPTION_STATE_IN_GRACE_PERIOD' => 'grace',
            'SUBSCRIPTION_STATE_CANCELED' => 'canceled', 'SUBSCRIPTION_STATE_EXPIRED' => 'expired',
            'SUBSCRIPTION_STATE_PENDING' => 'pending', 'SUBSCRIPTION_STATE_PAUSED' => 'paused', 'SUBSCRIPTION_STATE_ON_HOLD' => 'on_hold', default => 'unknown',
        };

        return ['identity' => hash('sha256', $token), 'product_id' => $item['productId'], 'base_plan_id' => $item['offerDetails']['basePlanId'] ?? '', 'environment' => $sandbox ? 'sandbox' : 'production', 'status' => $status, 'expires_at' => $expiry, 'active' => in_array($status, ['active', 'grace', 'canceled'], true) && $expiry->isFuture(), 'proof' => $token,
            'is_trial' => ($item['offerPhase']['freeTrial'] ?? null) !== null,
            'auto_renews' => isset($item['autoRenewingPlan']['autoRenewEnabled']) ? (bool) $item['autoRenewingPlan']['autoRenewEnabled'] : null,
            'acknowledgement_pending' => ($body['acknowledgementState'] ?? '') === 'ACKNOWLEDGEMENT_STATE_PENDING', 'access_token' => $oauth['access_token']];
    }

    public function acknowledge(StoreApp $store, array $verified): void
    {
        if ($store->platform !== 'android' || empty($verified['acknowledgement_pending']) || ! $verified['active']) {
            return;
        }
        $url = 'https://androidpublisher.googleapis.com/androidpublisher/v3/applications/'.rawurlencode($store->identifier).'/purchases/subscriptions/'.rawurlencode($verified['product_id']).'/tokens/'.rawurlencode($verified['proof']).':acknowledge';
        try {
            $response = Http::withToken($verified['access_token'])->connectTimeout(5)->timeout(15)->withBody('{}', 'application/json')->post($url);
            abort_unless($response->successful(), 503, 'Google satın alma onayı tamamlanamadı.');
        } catch (ConnectionException $exception) {
            abort(503, 'Google satın alma onayı tamamlanamadı.');
        }
    }
}
