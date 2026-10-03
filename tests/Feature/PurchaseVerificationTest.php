<?php

namespace Tests\Feature;

use App\Http\StoreJwt;
use App\Models\AppUser;
use App\Models\Device;
use App\Models\Purchase;
use App\Models\StoreApp;
use App\Models\StoreProduct;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PurchaseVerificationTest extends TestCase
{
    use RefreshDatabase;

    private array $paths = [];

    private AppUser $membership;

    private StoreApp $store;

    private string $prefix;

    private string $account;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->membership = AppUser::factory()->create();
        $device = Device::factory()->create(['app_id' => $this->membership->app_id, 'user_id' => $this->membership->user_id]);
        $token = $device->user->createToken('phone', ['app:'.$device->app_id]);
        $token->accessToken->forceFill(['device_id' => $device->id])->save();
        $this->withToken($token->plainTextToken);
        $this->prefix = '/api/v1/apps/'.$this->membership->app->slug;
        $this->account = $this->getJson($this->prefix.'/billing/context')->assertOk()->json('data.app_account_token');
        $this->store = StoreApp::factory()->create(['app_id' => $this->membership->app_id]);
        $plan = SubscriptionPlan::factory()->create(['app_id' => $this->membership->app_id]);
        StoreProduct::factory()->create(['subscription_plan_id' => $plan->id, 'store_app_id' => $this->store->id, 'product_id' => 'premium', 'base_plan_id' => 'yearly']);
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $path = tempnam(sys_get_temp_dir(), 'billing-test-');
        $this->paths[] = $path;
        file_put_contents($path, json_encode(['client_email' => 'test@example.com', 'private_key' => $pem]));
        config(['billing.google.service_account_path' => $path]);
    }

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            unlink($path);
        }
        parent::tearDown();
    }

    private function google(array $overrides = []): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $body = array_replace_recursive([
            'subscriptionState' => 'SUBSCRIPTION_STATE_ACTIVE', 'acknowledgementState' => 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED',
            'externalAccountIdentifiers' => ['obfuscatedExternalAccountId' => hash('sha256', $this->account)],
            'lineItems' => [['productId' => 'premium', 'expiryTime' => now()->addMonth()->toIso8601String(), 'offerDetails' => ['basePlanId' => 'yearly']]],
        ], $overrides);
        Http::fake(['https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'oauth']), 'https://androidpublisher.googleapis.com/*' => Http::response($body)]);
    }

    public function test_google_verify_restore_is_idempotent_and_proof_is_encrypted(): void
    {
        $this->google();
        $payload = ['platform' => 'android', 'purchase_token' => 'secret-purchase-token'];
        $this->postJson($this->prefix.'/purchases/verify', $payload)->assertOk()->assertJsonPath('data.is_active', true)->assertJsonMissingPath('data.proof');
        $this->postJson($this->prefix.'/purchases/restore', $payload)->assertOk();
        $this->assertDatabaseCount('purchases', 1);
        $this->assertNotSame('secret-purchase-token', Purchase::sole()->getRawOriginal('proof'));
        $this->assertSame('secret-purchase-token', Purchase::sole()->proof);
    }

    public function test_wrong_owner_and_unknown_product_do_not_grant_purchase(): void
    {
        $this->google(['externalAccountIdentifiers' => ['obfuscatedExternalAccountId' => 'foreign']]);
        $this->postJson($this->prefix.'/purchases/verify', ['platform' => 'android', 'purchase_token' => 'token'])->assertForbidden();
        $this->google(['lineItems' => [['productId' => 'other']]]);
        $this->postJson($this->prefix.'/purchases/verify', ['platform' => 'android', 'purchase_token' => 'token'])->assertNotFound();
        $this->assertDatabaseCount('purchases', 0);
    }

    public function test_expired_pending_and_canceled_states(): void
    {
        foreach (['SUBSCRIPTION_STATE_EXPIRED' => false, 'SUBSCRIPTION_STATE_PENDING' => false, 'SUBSCRIPTION_STATE_CANCELED' => true] as $state => $active) {
            $this->google(['subscriptionState' => $state]);
            $this->postJson($this->prefix.'/purchases/verify', ['platform' => 'android', 'purchase_token' => $state])->assertOk()->assertJsonPath('data.is_active', $active);
        }
    }

    public function test_sandbox_is_rejected_by_default_and_never_grants_production_access(): void
    {
        $this->google(['testPurchase' => []]);
        $this->postJson($this->prefix.'/purchases/verify', ['platform' => 'android', 'purchase_token' => 'sandbox'])->assertUnprocessable();
        config(['billing.allow_sandbox' => true]);
        $product = StoreProduct::sole();
        StoreProduct::factory()->create(['store_app_id' => $product->store_app_id, 'subscription_plan_id' => $product->subscription_plan_id, 'product_id' => $product->product_id, 'base_plan_id' => $product->base_plan_id, 'environment' => 'sandbox']);
        $this->postJson($this->prefix.'/purchases/verify', ['platform' => 'android', 'purchase_token' => 'sandbox'])->assertOk()->assertJsonPath('data.is_active', false)->assertJsonPath('data.environment', 'sandbox');
    }

    public function test_missing_credentials_fail_closed(): void
    {
        config(['billing.google.service_account_path' => null]);
        $this->postJson($this->prefix.'/purchases/restore', ['platform' => 'android', 'purchase_token' => 'token'])->assertStatus(503);
        $this->assertDatabaseCount('purchases', 0);
        Http::assertNothingSent();
    }

    public function test_store_failure_does_not_create_purchase(): void
    {
        Http::fake(['https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'oauth']), 'https://androidpublisher.googleapis.com/*' => Http::response([], 503)]);
        $this->postJson($this->prefix.'/purchases/verify', ['platform' => 'android', 'purchase_token' => 'token'])->assertStatus(503);
        $this->assertDatabaseCount('purchases', 0);
    }

    public function test_apple_fetches_latest_subscription_for_restore(): void
    {
        $planId = StoreProduct::sole()->subscription_plan_id;
        $this->store = StoreApp::factory()->create(['app_id' => $this->membership->app_id, 'platform' => 'ios']);
        StoreProduct::factory()->create(['store_app_id' => $this->store->id, 'subscription_plan_id' => $planId, 'product_id' => 'premium']);
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($key, $pem);
        $path = tempnam(sys_get_temp_dir(), 'apple-test-');
        $this->paths[] = $path;
        file_put_contents($path, $pem);
        config(['billing.apple' => ['issuer_id' => 'issuer', 'key_id' => 'key', 'private_key_path' => $path]]);
        $tx = ['bundleId' => $this->store->identifier, 'environment' => 'Production', 'type' => 'Auto-Renewable Subscription', 'originalTransactionId' => '123', 'productId' => 'premium', 'appAccountToken' => $this->account, 'expiresDate' => now()->addYear()->getTimestampMs()];
        $jws = StoreJwt::encode(['alg' => 'ES256'], $tx, $pem);
        Http::fake([
            'https://api.storekit.itunes.apple.com/inApps/v1/transactions/*' => Http::response(['signedTransactionInfo' => $jws]),
            'https://api.storekit.itunes.apple.com/inApps/v1/subscriptions/*' => Http::response(['data' => [['lastTransactions' => [['originalTransactionId' => '123', 'status' => 1, 'signedTransactionInfo' => $jws]]]]]),
        ]);
        $this->postJson($this->prefix.'/purchases/restore', ['platform' => 'ios', 'transaction_id' => '123'])->assertOk()->assertJsonPath('data.is_active', true);
        $this->assertSame('123', Purchase::sole()->identity);
    }

    public function test_purchase_list_is_scoped_to_user_and_app(): void
    {
        $this->google();
        $this->postJson($this->prefix.'/purchases/verify', ['platform' => 'android', 'purchase_token' => 'own'])->assertOk();
        Purchase::factory()->create();
        Purchase::factory()->create(['user_id' => $this->membership->user_id]);
        $this->getJson($this->prefix.'/purchases')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_google_acknowledges_verified_owned_purchase(): void
    {
        $this->google(['acknowledgementState' => 'ACKNOWLEDGEMENT_STATE_PENDING']);
        $this->postJson($this->prefix.'/purchases/verify', ['platform' => 'android', 'purchase_token' => 'ack-token'])->assertOk();
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), ':acknowledge') && $request->method() === 'POST');
    }

    public function test_existing_purchase_cannot_be_transferred_to_another_user(): void
    {
        $product = StoreProduct::sole();
        $foreign = Purchase::factory()->create(['store_app_id' => $this->store->id, 'store_product_id' => $product->id, 'app_id' => $this->membership->app_id, 'identity' => hash('sha256', 'claimed')]);
        $this->google();
        $this->postJson($this->prefix.'/purchases/restore', ['platform' => 'android', 'purchase_token' => 'claimed'])->assertStatus(409);
        $this->assertSame($foreign->user_id, $foreign->fresh()->user_id);
    }

    public function test_google_environment_cannot_be_spoofed_by_client(): void
    {
        $this->google();
        $this->postJson($this->prefix.'/purchases/verify', ['platform' => 'android', 'purchase_token' => 'production-proof', 'environment' => 'sandbox'])->assertUnprocessable();
        config(['billing.allow_sandbox' => true]);
        $this->google(['testPurchase' => []]);
        $this->postJson($this->prefix.'/purchases/verify', ['platform' => 'android', 'purchase_token' => 'test-proof', 'environment' => 'production'])->assertUnprocessable();
        $this->assertDatabaseCount('purchases', 0);
    }
}
