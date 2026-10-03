<?php

namespace Tests\Feature;

use App\Http\ProcessStoreNotification;
use App\Http\StoreJwt;
use App\Http\StoreNotificationVerifier;
use App\Http\StoreVerifier;
use App\Jobs\ProcessStoreNotificationJob;
use App\Models\AppUser;
use App\Models\Purchase;
use App\Models\StoreApp;
use App\Models\StoreNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StoreNotificationTest extends TestCase
{
    use RefreshDatabase;

    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            unlink($path);
        }
        parent::tearDown();
    }

    public function test_google_authenticates_and_deduplicates_notification(): void
    {
        Queue::fake();
        $store = StoreApp::factory()->create();
        config(['billing.google.push_audience' => 'https://example.com/push', 'billing.google.push_service_account_email' => 'push@example.com', 'billing.google.push_subscription' => 'projects/core/subscriptions/billing']);
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        openssl_pkey_export($key, $pem);
        Http::fake(['https://www.googleapis.com/oauth2/v1/certs' => Http::response(['test-key' => openssl_pkey_get_details($key)['key']])]);
        $jwt = StoreJwt::encode(['alg' => 'RS256', 'kid' => 'test-key'], ['iss' => 'https://accounts.google.com', 'aud' => 'https://example.com/push', 'email' => 'push@example.com', 'email_verified' => true, 'iat' => time(), 'exp' => time() + 300], $pem);
        $body = ['subscription' => 'projects/core/subscriptions/billing', 'message' => ['messageId' => 'message-1', 'data' => base64_encode(json_encode(['packageName' => $store->identifier, 'subscriptionNotification' => ['notificationType' => 2, 'purchaseToken' => 'private-token']]))]];
        $url = '/api/v1/store-notifications/'.$store->app->slug.'/android';
        $this->withToken($jwt)->postJson($url, $body)->assertNoContent();
        $this->withToken($jwt)->postJson($url, $body)->assertNoContent();
        $this->assertDatabaseCount('store_notifications', 1);
        $this->assertNotSame('private-token', StoreNotification::sole()->getRawOriginal('proof'));
        Queue::assertPushed(ProcessStoreNotificationJob::class);
        $invalid = StoreJwt::encode(['alg' => 'RS256', 'kid' => 'test-key'], ['iss' => 'https://accounts.google.com', 'aud' => 'other', 'email' => 'push@example.com', 'email_verified' => true, 'iat' => time(), 'exp' => time() + 300], $pem);
        $this->withToken($invalid)->postJson($url, $body)->assertUnauthorized();
    }

    public function test_missing_verification_settings_fail_closed(): void
    {
        Queue::fake();
        $store = StoreApp::factory()->create();
        $this->postJson('/api/v1/store-notifications/'.$store->app->slug.'/android', [])->assertStatus(503);
        $store = StoreApp::factory()->create(['platform' => 'ios']);
        $this->postJson('/api/v1/store-notifications/'.$store->app->slug.'/ios', ['signedPayload' => 'x.y.z'])->assertUnauthorized();
        $this->assertDatabaseCount('store_notifications', 0);
        Queue::assertNothingPushed();
    }

    private function event(Purchase $purchase): StoreNotification
    {
        return StoreNotification::factory()->create(['store_app_id' => $purchase->store_app_id, 'identity' => $purchase->identity, 'environment' => $purchase->environment]);
    }

    public function test_renewal_cancellation_expiry_and_refund_requery_current_state(): void
    {
        $purchase = Purchase::factory()->create();
        AppUser::factory()->create(['app_id' => $purchase->app_id, 'user_id' => $purchase->user_id]);
        foreach (['active' => true, 'canceled' => true, 'expired' => false, 'revoked' => false] as $state => $paid) {
            $event = $this->event($purchase);
            $expires = $state === 'expired' ? now()->subMinute() : now()->addYear();
            $mock = $this->mock(StoreVerifier::class);
            $mock->shouldReceive('verify')->once()->andReturn(['identity' => $purchase->identity, 'environment' => 'production', 'product_id' => $purchase->storeProduct->product_id, 'base_plan_id' => $purchase->storeProduct->base_plan_id, 'proof' => 'verified-proof', 'status' => $state, 'expires_at' => $expires]);
            $mock->shouldReceive('acknowledge')->once();
            app(ProcessStoreNotification::class)->handle($event->id);
            $this->assertSame($state, $purchase->fresh()->status);
            $this->assertSame($paid, $purchase->fresh()->hasPaidAccess());
            $this->assertSame('processed', $event->fresh()->status);
            app(ProcessStoreNotification::class)->handle($event->id);
        }
    }

    public function test_unmatched_and_failed_events_remain_retryable(): void
    {
        $event = StoreNotification::factory()->create();
        app(ProcessStoreNotification::class)->handle($event->id);
        $this->assertSame('waiting', $event->fresh()->status);
        $purchase = Purchase::factory()->create();
        AppUser::factory()->create(['app_id' => $purchase->app_id, 'user_id' => $purchase->user_id]);
        $event = $this->event($purchase);
        $this->mock(StoreVerifier::class)->shouldReceive('verify')->andThrow(new \RuntimeException('secret-token'));
        try {
            app(ProcessStoreNotification::class)->handle($event->id);
        } catch (\RuntimeException $exception) {
        }
        $this->assertSame('failed', $event->fresh()->status);
        $this->assertSame('RuntimeException', $event->fresh()->last_error);
    }

    public function test_old_event_uses_latest_store_state_and_does_not_revoke_new_renewal(): void
    {
        $purchase = Purchase::factory()->create();
        AppUser::factory()->create(['app_id' => $purchase->app_id, 'user_id' => $purchase->user_id]);
        $event = $this->event($purchase);
        $event->update(['type' => 'CANCELED']);
        $mock = $this->mock(StoreVerifier::class);
        $mock->shouldReceive('verify')->andReturn(['identity' => $purchase->identity, 'environment' => 'production', 'product_id' => $purchase->storeProduct->product_id, 'base_plan_id' => $purchase->storeProduct->base_plan_id, 'proof' => 'latest', 'status' => 'active', 'expires_at' => now()->addYear()]);
        $mock->shouldReceive('acknowledge');
        app(ProcessStoreNotification::class)->handle($event->id);
        $this->assertTrue($purchase->fresh()->hasPaidAccess());
    }

    public function test_apple_signature_chain_and_tampering(): void
    {
        $configPath = tempnam(sys_get_temp_dir(), 'apple-ca-');
        $this->paths[] = $configPath;
        file_put_contents($configPath, "[req]\ndistinguished_name=dn\n[dn]\n[ca]\nbasicConstraints=critical,CA:TRUE\nkeyUsage=critical,keyCertSign,cRLSign\n[intermediate]\nbasicConstraints=critical,CA:TRUE\nkeyUsage=critical,keyCertSign,cRLSign\n1.2.840.113635.100.6.2.1=ASN1:NULL\n[leaf]\nbasicConstraints=critical,CA:FALSE\nkeyUsage=critical,digitalSignature\n1.2.840.113635.100.6.11.1=ASN1:NULL\n");
        $rootKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $rootCsr = openssl_csr_new(['commonName' => 'Test Root'], $rootKey, ['config' => $configPath]);
        $root = openssl_csr_sign($rootCsr, null, $rootKey, 5, ['config' => $configPath, 'x509_extensions' => 'ca']);
        $interKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $interCsr = openssl_csr_new(['commonName' => 'Test Intermediate'], $interKey, ['config' => $configPath]);
        $inter = openssl_csr_sign($interCsr, $root, $rootKey, 5, ['config' => $configPath, 'x509_extensions' => 'intermediate'], 2);
        $leafKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $leafCsr = openssl_csr_new(['commonName' => 'Test Signing'], $leafKey, ['config' => $configPath]);
        $leaf = openssl_csr_sign($leafCsr, $inter, $interKey, 5, ['config' => $configPath, 'x509_extensions' => 'leaf'], 3);
        openssl_x509_export($root, $rootPem);
        $rootPath = tempnam(sys_get_temp_dir(), 'trusted-root-');
        $this->paths[] = $rootPath;
        file_put_contents($rootPath, $rootPem);
        config(['billing.apple.root_ca_paths' => [$rootPath]]);
        $chain = [];
        foreach ([$leaf, $inter, $root] as $cert) {
            openssl_x509_export($cert, $pem);
            $chain[] = preg_replace('/-----[^-]+-----|\s/', '', $pem);
        }
        openssl_pkey_export($leafKey, $private);
        $jwt = StoreJwt::encode(['alg' => 'ES256', 'x5c' => $chain], ['notificationUUID' => 'signed-event'], $private);
        $this->assertSame('signed-event', app(StoreNotificationVerifier::class)->appleJws($jwt)['notificationUUID']);
        Queue::fake();
        $store = StoreApp::factory()->create(['platform' => 'ios', 'apple_app_id' => '12345']);
        $payload = StoreJwt::encode(['alg' => 'ES256', 'x5c' => $chain], ['notificationUUID' => 'test-notification', 'notificationType' => 'TEST', 'data' => ['bundleId' => $store->identifier, 'appAppleId' => 12345, 'environment' => 'Production']], $private);
        $url = '/api/v1/store-notifications/'.$store->app->slug.'/ios';
        $this->postJson($url, ['signedPayload' => $payload])->assertNoContent();
        $this->assertSame('ignored', StoreNotification::sole()->status);
        Queue::assertNothingPushed();
        $wrong = StoreJwt::encode(['alg' => 'ES256', 'x5c' => $chain], ['notificationUUID' => 'other', 'notificationType' => 'TEST', 'data' => ['bundleId' => 'other.bundle', 'appAppleId' => 12345, 'environment' => 'Production']], $private);
        $this->postJson($url, ['signedPayload' => $wrong])->assertUnprocessable();
        $parts = explode('.', $jwt);
        $parts[1] = StoreJwt::base64(json_encode(['notificationUUID' => 'forged']));
        $this->expectException(HttpException::class);
        app(StoreNotificationVerifier::class)->appleJws(implode('.', $parts));
    }

    public function test_retry_command_dispatches_stalled_events(): void
    {
        Queue::fake();
        $event = StoreNotification::factory()->create(['status' => 'waiting', 'updated_at' => now()->subHour()]);
        StoreNotification::factory()->create(['status' => 'processed', 'updated_at' => now()->subHour()]);
        $this->artisan('billing:retry-notifications')->assertSuccessful();
        Queue::assertPushed(ProcessStoreNotificationJob::class, fn ($job): bool => $job->notificationId === $event->id);
        Queue::assertPushed(ProcessStoreNotificationJob::class, 1);
    }
}
