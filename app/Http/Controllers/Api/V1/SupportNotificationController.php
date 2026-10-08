<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupportNotificationController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $row = DB::table('app_users')->where('id', $request->attributes->get('app_membership')->id)->first();

        return response()->json(['data' => ['support_replies' => (bool) $row->support_replies]])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['support_replies' => ['required', 'boolean'], 'app_id' => ['prohibited'], 'user_id' => ['prohibited']]);
        DB::table('app_users')->where('id', $request->attributes->get('app_membership')->id)->update($data);

        return $this->show($request);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:4096'], 'enabled' => ['required', 'boolean'], 'locale' => ['required', Rule::in(['tr', 'en'])], 'app_id' => ['prohibited'], 'user_id' => ['prohibited'], 'device_id' => ['prohibited']]);
        $device = $request->attributes->get('mobile_device');
        DB::transaction(function () use ($device, $data): void {
            DB::table('devices')->where('id', $device->id)->lockForUpdate()->first();
            $hash = hash('sha256', $data['token']);
            DB::table('support_push_devices')->where('app_id', $device->app_id)->where('token_hash', $hash)->where('device_id', '!=', $device->id)->delete();
            DB::table('support_push_devices')->updateOrInsert(['device_id' => $device->id], ['app_id' => $device->app_id, 'user_id' => $device->user_id, 'token' => Crypt::encryptString($data['token']), 'token_hash' => $hash, 'enabled' => $data['enabled'], 'locale' => $data['locale'], 'created_at' => now(), 'updated_at' => now()]);
        });

        return response()->json(['data' => ['registered' => true]]);
    }

    public function unregister(Request $request): JsonResponse
    {
        DB::table('support_push_devices')->where('device_id', $request->attributes->get('mobile_device')->id)->delete();

        return response()->json(null, 204);
    }
}
