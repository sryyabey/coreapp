<?php

namespace App\Http\Middleware;

use App\Models\AppUser;
use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureAppMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $app = $request->attributes->get('mobile_app');
        $token = $request->user()->currentAccessToken();
        abort_unless($request->bearerToken() && $token instanceof PersonalAccessToken && in_array('app:'.$app->id, $token->abilities, true), 403, 'Token bu uygulama için geçerli değil.');
        $device = Device::whereKey($token->device_id)->where('app_id', $app->id)->where('user_id', $request->user()->id)->whereNull('revoked_at')->first();
        abort_unless($device, 403, 'Cihaz oturumu geçerli değil.');
        $membership = AppUser::where('app_id', $app->id)->where('user_id', $request->user()->id)->where('is_active', true)->first();
        abort_unless($membership, 403, 'Uygulama üyeliği aktif değil.');
        if (! $device->last_seen_at || $device->last_seen_at->lt(now()->subMinutes(5))) {
            $device->update(['last_seen_at' => now()]);
        }
        $request->attributes->set('mobile_device', $device);
        if (! $membership->last_seen_at || $membership->last_seen_at->lt(now()->subMinutes(5))) {
            $membership->update(['last_seen_at' => now()]);
        }
        $request->attributes->set('app_membership', $membership);

        return $next($request);
    }
}
