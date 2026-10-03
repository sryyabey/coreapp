<?php

namespace App\Models;

use Database\Factories\StoreProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['subscription_plan_id', 'store_app_id', 'product_id', 'base_plan_id', 'is_active', 'environment'])]
class StoreProduct extends Model
{
    /** @use HasFactory<StoreProductFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (StoreProduct $product): void {
            if ($product->exists && $product->isDirty('environment') && Purchase::where('store_product_id', $product->id)->exists()) {
                throw ValidationException::withMessages(['environment' => 'Satın alma kayıtları olan ürünün ortamı değiştirilemez.']);
            }
            $plan = SubscriptionPlan::findOrFail($product->subscription_plan_id);
            $store = StoreApp::findOrFail($product->store_app_id);
            if ($plan->app_id !== $store->app_id) {
                throw ValidationException::withMessages(['store_app_id' => 'Plan ve mağaza aynı uygulamaya ait olmalı.']);
            }
            if ($store->platform === 'ios') {
                $product->base_plan_id = '';
            }
            if ($store->platform === 'android' && ! $product->base_plan_id) {
                throw ValidationException::withMessages(['base_plan_id' => 'Google Play Base Plan ID gerekli.']);
            }
        });
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function storeApp(): BelongsTo
    {
        return $this->belongsTo(StoreApp::class);
    }
}
