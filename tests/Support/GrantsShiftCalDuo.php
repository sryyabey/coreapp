<?php

namespace Tests\Support;

use App\Models\AppUser;
use App\Models\Purchase;
use App\Models\StoreApp;
use App\Models\StoreProduct;
use App\Models\SubscriptionPlan;

trait GrantsShiftCalDuo
{
    private function grantDuo(AppUser $member, array $attributes = []): Purchase
    {
        $plan = SubscriptionPlan::factory()->create(['app_id' => $member->app_id, 'shares_with_partner' => true,
            'features' => ['shiftcal.together', 'shiftcal.reports', 'shiftcal.backup']]);
        $store = StoreApp::where('app_id', $member->app_id)->first() ?? StoreApp::factory()->create(['app_id' => $member->app_id]);
        $product = StoreProduct::factory()->create(['subscription_plan_id' => $plan->id, 'store_app_id' => $store->id]);

        return Purchase::factory()->create(array_merge(['store_product_id' => $product->id, 'user_id' => $member->user_id], $attributes));
    }
}
