<?php

namespace App\Models;

use Database\Factories\StoreAppFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['app_id', 'platform', 'identifier', 'apple_app_id', 'is_active'])]
class StoreApp extends Model
{
    /** @use HasFactory<StoreAppFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (StoreApp $storeApp): void {
            if ($storeApp->exists && ($storeApp->isDirty('app_id') || $storeApp->isDirty('platform')) && $storeApp->storeProducts()->exists()) {
                throw ValidationException::withMessages(['app_id' => 'Ürünleri olan mağazanın uygulama veya platformu değiştirilemez.']);
            }
            if ($storeApp->platform === 'android') {
                $storeApp->apple_app_id = null;
            }
        });
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
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
