<?php

namespace App\Models;

use Database\Factories\SubscriptionPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['app_id', 'name', 'slug', 'period', 'catalog_price', 'currency', 'is_active', 'features', 'shares_with_partner'])]
class SubscriptionPlan extends Model
{
    /** @use HasFactory<SubscriptionPlanFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (SubscriptionPlan $plan): void {
            if (($plan->isDirty('app_id') || $plan->isDirty('period')) && $plan->storeProducts()->exists()) {
                throw ValidationException::withMessages(['app_id' => 'Mağaza ürünleri olan planın uygulaması veya dönemi değiştirilemez.']);
            }
        });
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'catalog_price' => 'decimal:2', 'features' => 'array', 'shares_with_partner' => 'boolean'];
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    public function storeProducts(): HasMany
    {
        return $this->hasMany(StoreProduct::class);
    }
}
