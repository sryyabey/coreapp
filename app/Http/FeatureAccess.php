<?php

namespace App\Http;

use App\Models\App;
use App\Models\AppUser;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FeatureAccess
{
    /** @return array<string, string> */
    public function forUser(App $app, User $user): array
    {
        return $this->summary($app, $user)['rights'];
    }

    /** @return array{rights: array<string, string>, subscriptions: array<int, array<string, mixed>>} */
    public function summary(App $app, User $user): array
    {
        $result = ['rights' => [], 'subscriptions' => []];
        if (! $app->is_active || ! $this->activeMember($app, $user->id)) {
            return $result;
        }
        $owners = [$user->id];
        if ($app->slug === 'shiftcal') {
            $link = DB::table('shiftcal_partner_links')->where('app_id', $app->id)->where('user_id', $user->id)->first();
            if ($link && $this->activeMember($app, $link->partner_user_id)
                && DB::table('shiftcal_partner_links')->where('app_id', $app->id)->where('user_id', $link->partner_user_id)
                    ->where('partner_user_id', $user->id)->where('connection_id', $link->connection_id)->exists()) {
                $owners[] = $link->partner_user_id;
            }
        }
        $environment = config('billing.apps.'.$app->slug.'.feature_environment', 'production');
        if (! in_array($environment, ['production', 'sandbox'], true)
            || ($environment === 'sandbox' && ! config('billing.apps.'.$app->slug.'.allow_sandbox', config('billing.allow_sandbox')))) {
            return $result;
        }
        $purchases = Purchase::with(['storeProduct.subscriptionPlan', 'storeApp'])->where('app_id', $app->id)
            ->whereIn('user_id', $owners)->where('environment', $environment)->whereIn('status', ['active', 'grace', 'canceled'])
            ->where('expires_at', '>', now())->orderByDesc('expires_at')->get();
        foreach ($purchases as $purchase) {
            $product = $purchase->storeProduct;
            $plan = $product?->subscriptionPlan;
            $shared = $purchase->user_id !== $user->id;
            if (! $plan || $plan->app_id !== $app->id || $product->environment !== $environment
                || $product->store_app_id !== $purchase->store_app_id || $purchase->storeApp?->app_id !== $app->id
                || ($shared && ! $plan->shares_with_partner)) {
                continue;
            }
            foreach ($plan->features ?? [] as $feature) {
                if (! is_string($feature) || ! preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/', $feature)) {
                    continue;
                }
                $expires = $purchase->expires_at->toIso8601String();
                if (! isset($result['rights'][$feature]) || $expires > $result['rights'][$feature]) {
                    $result['rights'][$feature] = $expires;
                }
            }
            $result['subscriptions'][] = [
                'source' => $shared ? 'partner' : 'own', 'platform' => $purchase->storeApp->platform,
                'product_id' => $product->product_id, 'status' => $purchase->status, 'environment' => $environment,
                'expires_at' => $purchase->expires_at->toIso8601String(), 'is_trial' => $purchase->is_trial,
                'auto_renews' => $purchase->auto_renews, 'features' => $plan->features ?? [],
            ];
        }
        ksort($result['rights']);

        return $result;
    }

    private function activeMember(App $app, int $userId): bool
    {
        return AppUser::where('app_id', $app->id)->where('user_id', $userId)->where('is_active', true)->exists();
    }

    public function allows(App $app, User $user, string $feature): bool
    {
        return array_key_exists($feature, $this->forUser($app, $user));
    }
}
