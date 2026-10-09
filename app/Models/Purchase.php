<?php

namespace App\Models;

use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['app_id', 'user_id', 'store_app_id', 'store_product_id', 'environment', 'identity', 'proof', 'status', 'expires_at', 'verified_at', 'next_check_at', 'check_failures', 'last_check_error', 'is_trial', 'auto_renews'])]
#[Hidden(['proof', 'identity'])]
class Purchase extends Model
{
    /** @use HasFactory<PurchaseFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_trial' => 'boolean', 'auto_renews' => 'boolean', 'proof' => 'encrypted', 'expires_at' => 'datetime', 'verified_at' => 'datetime', 'next_check_at' => 'datetime'];
    }

    public function storeProduct(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class);
    }

    public function storeApp(): BelongsTo
    {
        return $this->belongsTo(StoreApp::class);
    }

    public function hasPaidAccess(): bool
    {
        return $this->environment === 'production' && $this->storeProduct?->environment === 'production' && in_array($this->status, ['active', 'grace', 'canceled'], true) && $this->expires_at !== null && $this->expires_at->isFuture();
    }
}
