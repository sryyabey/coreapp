<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\DeviceResource;
use App\Models\AppUser;
use App\Models\Device;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class DeviceController extends Controller
{
    private function ownedDevices(Request $request): Builder
    {
        return Device::query()->where('app_id', $request->attributes->get('mobile_app')->id)->where('user_id', $request->user()->id);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return DeviceResource::collection($this->ownedDevices($request)->orderByDesc('last_seen_at')->paginate(20));
    }

    public function show(Request $request, string $app, string $device): DeviceResource
    {
        return new DeviceResource($this->ownedDevices($request)->whereKey($device)->firstOrFail());
    }

    public function destroy(Request $request, string $app, string $device): Response
    {
        DB::transaction(function () use ($request, $device): void {
            AppUser::whereKey($request->attributes->get('app_membership')->id)->lockForUpdate()->firstOrFail();
            $ownedDevice = $this->ownedDevices($request)->whereKey($device)->lockForUpdate()->firstOrFail();
            $ownedDevice->update(['revoked_at' => now()]);
            $ownedDevice->tokens()->delete();
        });

        return response()->noContent();
    }
}
