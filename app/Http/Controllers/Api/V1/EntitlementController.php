<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\FeatureAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EntitlementController extends Controller
{
    public function __invoke(Request $request, FeatureAccess $access): JsonResponse
    {
        $summary = $access->summary($request->attributes->get('mobile_app'), $request->user());
        $rights = $summary['rights'];
        $features = [];
        foreach ($rights as $key => $expires) {
            $features[] = ['key' => $key, 'expires_at' => $expires];
        }

        return response()->json(['data' => ['app' => $request->attributes->get('mobile_app')->slug, 'has_paid_access' => count($features) > 0, 'features' => $features, 'subscriptions' => $summary['subscriptions']]])->header('Cache-Control', 'private, no-store');
    }
}
