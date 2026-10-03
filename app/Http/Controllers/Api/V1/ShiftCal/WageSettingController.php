<?php

namespace App\Http\Controllers\Api\V1\ShiftCal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ShiftCal\WageSettingRequest;
use App\Http\Resources\Api\ShiftCal\WageSettingResource;
use App\Models\ShiftCal\WageSetting;
use Illuminate\Http\Request;

class WageSettingController extends Controller
{
    public function show(Request $request): WageSettingResource
    {
        $record = WageSetting::where('app_id', $request->attributes->get('mobile_app')->id)->where('user_id', $request->user()->id)->first();

        return new WageSettingResource($record ?? new WageSetting(['hourly_rate' => 0, 'overtime_multiplier' => 1.5, 'currency' => 'TRY', 'weekly_target_minutes' => 2400]));
    }

    public function update(WageSettingRequest $request): WageSettingResource
    {
        $owner = ['app_id' => $request->attributes->get('mobile_app')->id, 'user_id' => $request->user()->id];
        $record = WageSetting::unguarded(fn (): WageSetting => WageSetting::firstOrCreate($owner));
        $record->update($request->validated());

        return new WageSettingResource($record->fresh());
    }
}
