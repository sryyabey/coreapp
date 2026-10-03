<?php

namespace Tests\Feature;

use App\Filament\Resources\StoreProducts\Pages\CreateStoreProduct;
use App\Filament\Resources\SubscriptionPlans\Pages\CreateSubscriptionPlan;
use App\Models\App;
use App\Models\StoreApp;
use App\Models\StoreProduct;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SubscriptionCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('manage'));
        $user = User::factory()->create();
        foreach (['SubscriptionPlan', 'StoreProduct'] as $model) {
            $user->givePermissionTo(Permission::findOrCreate('Create:'.$model, 'web'));
            $user->givePermissionTo(Permission::findOrCreate('ViewAny:'.$model, 'web'));
        }
        $this->actingAs($user);
    }

    public function test_application_can_offer_monthly_and_yearly_plans(): void
    {
        $app = App::factory()->create();
        foreach (['monthly', 'yearly'] as $period) {
            Livewire::test(CreateSubscriptionPlan::class)->fillForm(['app_id' => $app->id, 'name' => 'Premium '.$period, 'slug' => 'premium-'.$period, 'period' => $period, 'currency' => 'USD', 'catalog_price' => '14.99'])
                ->call('create')->assertHasNoFormErrors();
        }
        $this->assertCount(2, $app->subscriptionPlans);
    }

    public function test_android_base_plans_can_share_product_id(): void
    {
        $monthly = SubscriptionPlan::factory()->create(['period' => 'monthly']);
        $yearly = SubscriptionPlan::factory()->create(['app_id' => $monthly->app_id]);
        $store = StoreApp::factory()->create(['app_id' => $monthly->app_id]);
        foreach ([$monthly, $yearly] as $plan) {
            Livewire::test(CreateStoreProduct::class)->fillForm(['subscription_plan_id' => $plan->id, 'store_app_id' => $store->id, 'product_id' => 'premium', 'base_plan_id' => $plan->period])
                ->call('create')->assertHasNoFormErrors();
        }
        $this->assertDatabaseCount('store_products', 2);
    }

    public function test_ios_product_can_be_created_without_base_plan(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $store = StoreApp::factory()->create(['app_id' => $plan->app_id, 'platform' => 'ios']);
        Livewire::test(CreateStoreProduct::class)->fillForm(['subscription_plan_id' => $plan->id, 'store_app_id' => $store->id, 'product_id' => 'premium.yearly'])
            ->call('create')->assertHasNoFormErrors();
        $this->assertSame('', StoreProduct::sole()->base_plan_id);
    }

    public function test_cross_application_product_mapping_is_rejected_by_model(): void
    {
        $this->expectException(ValidationException::class);
        StoreProduct::factory()->create(['store_app_id' => StoreApp::factory()->create()->id]);
    }

    public function test_cross_application_mapping_and_missing_base_plan_are_rejected_by_form(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $foreign = StoreApp::factory()->create();
        Livewire::test(CreateStoreProduct::class)->fillForm(['subscription_plan_id' => $plan->id, 'store_app_id' => $foreign->id, 'product_id' => 'premium', 'base_plan_id' => 'yearly'])
            ->call('create')->assertHasFormErrors(['store_app_id']);
        $store = StoreApp::factory()->create(['app_id' => $plan->app_id]);
        Livewire::test(CreateStoreProduct::class)->fillForm(['subscription_plan_id' => $plan->id, 'store_app_id' => $store->id, 'product_id' => 'premium', 'base_plan_id' => ''])
            ->call('create')->assertHasFormErrors(['base_plan_id' => 'required']);
    }

    public function test_store_identity_is_unique_in_database(): void
    {
        $product = StoreProduct::factory()->create();
        $this->expectException(UniqueConstraintViolationException::class);
        StoreProduct::factory()->create(['subscription_plan_id' => $product->subscription_plan_id, 'store_app_id' => $product->store_app_id, 'product_id' => $product->product_id, 'base_plan_id' => $product->base_plan_id]);
    }

    public function test_unauthorized_user_cannot_create_plans_or_products(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(CreateSubscriptionPlan::class)->assertForbidden();
        Livewire::test(CreateStoreProduct::class)->assertForbidden();
    }
}
