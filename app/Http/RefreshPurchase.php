<?php

namespace App\Http;

use App\Models\AppUser;
use App\Models\Purchase;
use App\Models\StoreProduct;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RefreshPurchase
{
    public function __construct(private StoreVerifier $verifier) {}

    public function handle(int $id): void
    {
        $purchase = Purchase::find($id);
        if (! $purchase) {
            return;
        }
        Cache::lock('billing-store:'.$purchase->store_app_id, 120)->block(5, function () use ($purchase): void {
            $purchase->refresh();
            try {
                $this->refreshLocked($purchase);
            } catch (\Throwable $exception) {
                $failures = $purchase->check_failures + 1;
                $purchase->update(['check_failures' => $failures, 'last_check_error' => class_basename($exception), 'next_check_at' => now()->addMinutes(min(1440, 15 * (2 ** min($failures, 7))))]);
                throw $exception;
            }
        });
    }

    public function refreshLocked(Purchase $purchase, ?string $proof = null): void
    {
        $membership = AppUser::where('app_id', $purchase->app_id)->where('user_id', $purchase->user_id)->firstOrFail();
        $store = $purchase->storeApp;
        $input = $store->platform === 'ios' ? ['transaction_id' => $proof ?: $purchase->proof, 'environment' => $purchase->environment] : ['purchase_token' => $proof ?: $purchase->proof, 'environment' => $purchase->environment];
        $verified = $this->verifier->verify($store, $membership, $input);
        abort_unless($verified['identity'] === $purchase->identity && $verified['environment'] === $purchase->environment, 422, 'Satın alma eşleşmesi geçersiz.');
        $product = StoreProduct::where('store_app_id', $store->id)->where('environment', $verified['environment'])->where('product_id', $verified['product_id'])->where('base_plan_id', $verified['base_plan_id'])
            ->whereHas('subscriptionPlan', fn ($query) => $query->where('app_id', $purchase->app_id))->firstOrFail();
        $this->verifier->acknowledge($store, $verified);
        $next = now()->addHours(in_array($verified['status'], ['expired', 'revoked'], true) ? 24 : 6);
        if ($verified['expires_at']->isFuture() && $verified['expires_at']->lt($next)) {
            $next = $verified['expires_at']->copy()->addMinutes(5);
        }
        DB::transaction(function () use ($purchase, $product, $verified, $next): void {
            $locked = Purchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            $locked->update(['store_product_id' => $product->id, 'proof' => $verified['proof'], 'status' => $verified['status'], 'expires_at' => $verified['expires_at'], 'is_trial' => $verified['is_trial'] ?? false, 'auto_renews' => $verified['auto_renews'] ?? null, 'verified_at' => now(), 'next_check_at' => $next, 'check_failures' => 0, 'last_check_error' => null]);
        });
    }
}
