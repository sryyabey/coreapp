<?php

namespace Tests\Feature;

use App\Filament\Resources\SubscriptionPlans\Pages\CreateSubscriptionPlan;
use App\Http\FeatureAccess;
use App\Http\Middleware\EnsureAppMembership;
use App\Http\Middleware\ResolveMobileApp;
use App\Models\App;
use App\Models\AppUser;
use App\Models\Device;
use App\Models\Purchase;
use App\Models\StoreProduct;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FeatureAccessTest extends TestCase
{
    use RefreshDatabase;

    private function purchase(array $attributes = [], array $features = ['premium', 'cloud_backup']): Purchase
    {
        $plan = SubscriptionPlan::factory()->create(['features' => $features]);
        $product = StoreProduct::factory()->create(['subscription_plan_id' => $plan->id]);
        $purchase = Purchase::factory()->create(array_merge(['store_product_id' => $product->id], $attributes));
        AppUser::factory()->create(['app_id' => $purchase->app_id, 'user_id' => $purchase->user_id]);

        return $purchase;
    }

    public function test_access_is_scoped_to_user_app_and_named_feature(): void
    {
        $purchase = $this->purchase();
        $access = app(FeatureAccess::class);
        $user = User::findOrFail($purchase->user_id);
        $app = $purchase->storeApp->app;
        $this->assertTrue($access->allows($app, $user, 'premium'));
        $this->assertFalse($access->allows($app, $user, 'unknown'));
        $this->assertFalse($access->allows($app, User::factory()->create(), 'premium'));
        $other = $this->purchase(['user_id' => $user->id], ['advanced_reports']);
        $this->assertFalse($access->allows($other->storeApp->app, $user, 'premium'));
    }

    public function test_only_valid_paid_states_grant_access(): void
    {
        $purchase = $this->purchase();
        $access = app(FeatureAccess::class);
        $user = User::findOrFail($purchase->user_id);
        $app = $purchase->storeApp->app;
        foreach (['active', 'grace', 'canceled'] as $state) {
            $purchase->update(['status' => $state]);
            $this->assertTrue($access->allows($app, $user, 'premium'));
        }
        foreach (['expired', 'revoked', 'pending', 'on_hold', 'paused', 'unknown'] as $state) {
            $purchase->update(['status' => $state]);
            $this->assertFalse($access->allows($app, $user, 'premium'));
        }
        $purchase->update(['status' => 'active', 'environment' => 'sandbox']);
        $this->assertFalse($access->allows($app, $user, 'premium'));
        $purchase->update(['environment' => 'production', 'expires_at' => now()]);
        $this->assertFalse($access->allows($app, $user, 'premium'));
    }

    public function test_inactive_membership_blocks_but_delisted_product_preserves_paid_access(): void
    {
        $purchase = $this->purchase();
        $access = app(FeatureAccess::class);
        $user = User::findOrFail($purchase->user_id);
        $app = $purchase->storeApp->app;
        $purchase->storeProduct->update(['is_active' => false]);
        $purchase->storeProduct->subscriptionPlan->update(['is_active' => false]);
        $this->assertTrue($access->allows($app, $user, 'premium'));
        AppUser::where('user_id', $user->id)->update(['is_active' => false]);
        $this->assertFalse($access->allows($app, $user, 'premium'));
    }

    public function test_multiple_purchases_merge_features_and_use_latest_expiry(): void
    {
        $first = $this->purchase();
        $plan = SubscriptionPlan::factory()->create(['app_id' => $first->app_id, 'features' => ['premium', 'advanced_reports']]);
        $product = StoreProduct::factory()->create(['subscription_plan_id' => $plan->id, 'store_app_id' => $first->store_app_id]);
        $second = Purchase::factory()->create(['store_product_id' => $product->id, 'user_id' => $first->user_id, 'expires_at' => now()->addYear()]);
        $rights = app(FeatureAccess::class)->forUser($first->storeApp->app, User::findOrFail($first->user_id));
        $this->assertCount(3, $rights);
        $this->assertSame($second->expires_at->toIso8601String(), $rights['premium']);
    }

    public function test_api_and_middleware_enforce_feature_access(): void
    {
        $purchase = $this->purchase();
        $device = Device::factory()->create(['app_id' => $purchase->app_id, 'user_id' => $purchase->user_id]);
        $token = $device->user->createToken('phone', ['app:'.$purchase->app_id]);
        $token->accessToken->forceFill(['device_id' => $device->id])->save();
        $this->withToken($token->plainTextToken);
        Route::get('/api/v1/apps/{app}/test-paid', fn () => response()->json(['ok' => true]))->middleware([ResolveMobileApp::class, 'auth:sanctum', EnsureAppMembership::class, 'paid-feature:cloud_backup']);
        $prefix = '/api/v1/apps/'.$device->app->slug;
        $this->getJson($prefix.'/entitlements?user_id=999')->assertOk()->assertJsonPath('data.has_paid_access', true)->assertJsonCount(2, 'data.features');
        $this->getJson($prefix.'/test-paid')->assertOk();
        $purchase->update(['status' => 'revoked']);
        $this->getJson($prefix.'/test-paid')->assertForbidden();
        $this->getJson($prefix.'/entitlements')->assertOk()->assertJsonPath('data.has_paid_access', false);
    }

    public function test_filament_validates_feature_keys(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('manage'));
        $user = User::factory()->create();
        foreach (['Create:SubscriptionPlan', 'ViewAny:SubscriptionPlan'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $this->actingAs($user);
        Livewire::test(CreateSubscriptionPlan::class)->fillForm(['app_id' => App::factory()->create()->id, 'name' => 'Premium', 'slug' => 'premium', 'period' => 'monthly', 'currency' => 'USD', 'features' => ['Invalid feature']])
            ->call('create')->assertHasFormErrors(['features.0']);
    }
}
