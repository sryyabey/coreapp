<?php

namespace App\Http;

use App\Models\App;
use App\Models\AppUser;
use App\Models\Purchase;
use App\Models\User;

class FeatureAccess
{
    /** @return array<string, string> Feature key mapped to its latest expiration. */
    public function forUser(App $app, User $user): array
    {
        if (! $app->is_active || ! AppUser::where('app_id', $app->id)->where('user_id', $user->id)->where('is_active', true)->exists()) {
            return [];
        }
        $purchases = Purchase::with(['storeProduct.subscriptionPlan', 'storeApp'])
            ->where('app_id', $app->id)->where('user_id', $user->id)->where('environment', 'production')
            ->whereIn('status', ['active', 'grace', 'canceled'])->where('expires_at', '>', now())->get();
        $rights = [];
        foreach ($purchases as $purchase) {
            $product = $purchase->storeProduct;
            $plan = $product?->subscriptionPlan;
            if ($product?->environment !== 'production' || ! $plan || $plan->app_id !== $app->id || $product->store_app_id !== $purchase->store_app_id || $purchase->storeApp?->app_id !== $app->id) {
                continue;
            }
            foreach ($plan->features ?? [] as $feature) {
                if (! is_string($feature) || ! preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/', $feature)) {
                    continue;
                }
                $expires = $purchase->expires_at->toIso8601String();
                if (! isset($rights[$feature]) || $expires > $rights[$feature]) {
                    $rights[$feature] = $expires;
                }
            }
        }
        ksort($rights);

        return $rights;
    }

    public function allows(App $app, User $user, string $feature): bool
    {
        return array_key_exists($feature, $this->forUser($app, $user));
    }
}
