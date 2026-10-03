<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\VerifyPurchaseRequest;
use App\Http\Resources\Api\PurchaseResource;
use App\Http\StoreVerifier;
use App\Models\AppUser;
use App\Models\Purchase;
use App\Models\StoreApp;
use App\Models\StoreProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseController extends Controller
{
    private function billingMembership(Request $request): AppUser
    {
        return DB::transaction(function () use ($request): AppUser {
            $membership = AppUser::whereKey($request->attributes->get('app_membership')->id)->lockForUpdate()->firstOrFail();
            if (! $membership->billing_account_id) {
                $membership->forceFill(['billing_account_id' => (string) Str::uuid()])->save();
            }

            return $membership;
        });
    }

    public function context(Request $request): JsonResponse
    {
        $membership = $this->billingMembership($request);

        return response()->json(['data' => ['app_account_token' => $membership->billing_account_id, 'obfuscated_account_id' => hash('sha256', $membership->billing_account_id)]]);
    }

    public function verify(VerifyPurchaseRequest $request, StoreVerifier $verifier): PurchaseResource
    {
        $app = $request->attributes->get('mobile_app');
        $store = StoreApp::where('app_id', $app->id)->where('platform', $request->validated('platform'))->firstOrFail();

        return Cache::lock('billing-store:'.$store->id, 120)->block(5, fn (): PurchaseResource => $this->verifyLocked($request, $verifier, $store));
    }

    private function verifyLocked(VerifyPurchaseRequest $request, StoreVerifier $verifier, StoreApp $store): PurchaseResource
    {
        $app = $request->attributes->get('mobile_app');
        $membership = $this->billingMembership($request);
        $verified = $verifier->verify($store, $membership, $request->validated());
        $product = StoreProduct::where('store_app_id', $store->id)->where('environment', $verified['environment'])->where('product_id', $verified['product_id'])->where('base_plan_id', $verified['base_plan_id'])->whereHas('subscriptionPlan', fn ($query) => $query->where('app_id', $app->id))->firstOrFail();
        $purchase = DB::transaction(function () use ($request, $app, $store, $product, $verified, $membership): Purchase {
            AppUser::whereKey($membership->id)->lockForUpdate()->firstOrFail();
            $purchase = Purchase::firstOrCreate([
                'store_app_id' => $store->id, 'environment' => $verified['environment'], 'identity' => $verified['identity'],
            ], ['app_id' => $app->id, 'user_id' => $request->user()->id, 'store_product_id' => $product->id, 'proof' => $verified['proof'], 'status' => $verified['status'], 'expires_at' => $verified['expires_at'], 'verified_at' => now()]);
            $purchase = Purchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            abort_unless($purchase->user_id === $request->user()->id && $purchase->app_id === $app->id, 409, 'Satın alma başka hesaba bağlı.');
            $purchase->update(['store_product_id' => $product->id, 'proof' => $verified['proof'], 'status' => $verified['status'], 'expires_at' => $verified['expires_at'], 'verified_at' => now()]);

            return $purchase;
        });
        $verifier->acknowledge($store, $verified);

        return new PurchaseResource($purchase);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return PurchaseResource::collection(Purchase::where('app_id', $request->attributes->get('mobile_app')->id)->where('user_id', $request->user()->id)->orderByDesc('verified_at')->paginate(20));
    }
}
