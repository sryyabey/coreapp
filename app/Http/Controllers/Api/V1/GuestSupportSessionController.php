<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GuestSupportSessionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $app = $request->attributes->get('mobile_app');
        abort_unless(in_array($app->slug, config('support.guest_apps', []), true), 404);
        $data = $request->validate(['external_user_id' => ['required', 'uuid'], 'secret' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/']]);
        $hash = hash('sha256', $data['secret']);
        DB::table('support_guest_sessions')->insertOrIgnore([
            'app_id' => $app->id, 'external_user_id' => $data['external_user_id'], 'token_hash' => $hash,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $session = DB::table('support_guest_sessions')->where('app_id', $app->id)->where('external_user_id', $data['external_user_id'])->firstOrFail();
        abort_unless(hash_equals($session->token_hash, $hash), 401);

        return response()->json(['data' => ['external_user_id' => $session->external_user_id]])->header('Cache-Control', 'no-store, private');
    }
}
