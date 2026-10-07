<?php

namespace App\Http\Controllers\Api\V1\ShiftCal;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public const DEFAULTS = ['shared_updates' => true, 'shared_cancellations' => true, 'show_details' => false,
        'work_enabled' => false, 'work_minutes' => 15, 'duty_enabled' => false, 'duty_minutes' => 15, 'plan_enabled' => false, 'plan_minutes' => 15];

    public function show(Request $request): JsonResponse
    {
        $row = DB::table('shiftcal_notification_preferences')->where('app_id', $request->attributes->get('mobile_app')->id)->where('user_id', $request->user()->id)->first();
        $data = self::DEFAULTS;
        foreach ($data as $key => $default) {
            $data[$key] = $row ? (is_bool($default) ? (bool) $row->$key : (int) $row->$key) : $default;
        }

        return response()->json(['data' => $data])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request): JsonResponse
    {
        $rules = [];
        foreach (self::DEFAULTS as $key => $value) {
            $rules[$key] = is_bool($value) ? ['required', 'boolean'] : ['required', 'integer', Rule::in([15, 20, 25, 30])];
        }
        $rules['user_id'] = ['prohibited'];
        $rules['app_id'] = ['prohibited'];
        $data = $request->validate($rules);
        DB::table('shiftcal_notification_preferences')->updateOrInsert(
            ['app_id' => $request->attributes->get('mobile_app')->id, 'user_id' => $request->user()->id],
            [...$data, 'updated_at' => now(), 'created_at' => now()],
        );

        return $this->show($request);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:4096'], 'enabled' => ['required', 'boolean'], 'locale' => ['required', Rule::in(['tr', 'en'])], 'user_id' => ['prohibited'], 'device_id' => ['prohibited'], 'app_id' => ['prohibited']]);
        $device = $request->attributes->get('mobile_device');
        DB::transaction(function () use ($device, $data): void {
            DB::table('devices')->where('id', $device->id)->lockForUpdate()->first();
            $hash = hash('sha256', $data['token']);
            DB::table('shiftcal_push_devices')->where('app_id', $device->app_id)->where('token_hash', $hash)->where('device_id', '!=', $device->id)->delete();
            DB::table('shiftcal_push_devices')->updateOrInsert(['device_id' => $device->id], [
                'app_id' => $device->app_id, 'user_id' => $device->user_id, 'token' => Crypt::encryptString($data['token']), 'token_hash' => $hash,
                'locale' => $data['locale'], 'enabled' => $data['enabled'], 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return response()->json(['data' => ['registered' => true]]);
    }

    public function unregister(Request $request): JsonResponse
    {
        DB::table('shiftcal_push_devices')->where('device_id', $request->attributes->get('mobile_device')->id)->delete();

        return response()->json(null, 204);
    }
}
