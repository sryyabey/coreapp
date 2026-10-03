<?php

namespace Tests\Feature;

use App\Http\BillingEnvironment;
use App\Http\FeatureAccess;
use App\Models\AppUser;
use App\Models\Purchase;
use App\Models\StoreApp;
use App\Models\StoreProduct;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BillingEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_product_identity_has_separate_environment_mapping(): void
    {
        $production = StoreProduct::factory()->create();
        $sandbox = StoreProduct::factory()->create(['store_app_id' => $production->store_app_id, 'subscription_plan_id' => $production->subscription_plan_id, 'product_id' => $production->product_id, 'base_plan_id' => $production->base_plan_id, 'environment' => 'sandbox']);
        $this->assertNotSame($production->id, $sandbox->id);
        $this->assertDatabaseCount('store_products', 2);
    }

    public function test_sandbox_is_disabled_by_default(): void
    {
        $store = StoreApp::factory()->create();
        config(['billing.allow_sandbox' => false]);
        BillingEnvironment::assertAllowed($store, 'production');
        $this->expectException(HttpException::class);
        BillingEnvironment::assertAllowed($store, 'sandbox');
    }

    public function test_sandbox_override_is_application_scoped(): void
    {
        $first = StoreApp::factory()->create();
        $second = StoreApp::factory()->create();
        config(['billing.allow_sandbox' => false, 'billing.apps.'.$first->app->slug.'.allow_sandbox' => true]);
        BillingEnvironment::assertAllowed($first, 'sandbox');
        $this->expectException(HttpException::class);
        BillingEnvironment::assertAllowed($second, 'sandbox');
    }

    public function test_sandbox_purchase_never_grants_production_features(): void
    {
        $plan = SubscriptionPlan::factory()->create(['features' => ['premium']]);
        $product = StoreProduct::factory()->create(['subscription_plan_id' => $plan->id, 'environment' => 'sandbox']);
        $purchase = Purchase::factory()->create(['store_product_id' => $product->id]);
        $user = User::findOrFail($purchase->user_id);
        AppUser::factory()->create(['app_id' => $purchase->app_id, 'user_id' => $user->id]);
        config(['billing.allow_sandbox' => true]);
        $this->assertFalse($purchase->hasPaidAccess());
        $this->assertFalse(app(FeatureAccess::class)->allows($plan->app, $user, 'premium'));
        $purchase->update(['environment' => 'production']);
        $this->assertFalse(app(FeatureAccess::class)->allows($plan->app, $user, 'premium'));
    }

    public function test_product_environment_cannot_change_after_purchase(): void
    {
        $purchase = Purchase::factory()->create();
        $this->expectException(ValidationException::class);
        $purchase->storeProduct->update(['environment' => 'sandbox']);
    }
}
