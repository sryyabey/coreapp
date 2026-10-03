<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Resources\Api\AppResource;
use App\Http\Resources\Api\UserResource;
use App\Models\AppUser;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request): JsonResponse {
            $user = User::create($request->safe()->only(['name', 'email', 'password']));

            return $this->issueToken($request, $user, 201);
        });
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();
        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages(['email' => ['E-posta veya şifre hatalı.']]);
        }

        return DB::transaction(fn (): JsonResponse => $this->issueToken($request, $user));
    }

    private function issueToken(Request $request, User $user, int $status = 200): JsonResponse
    {
        $app = $request->attributes->get('mobile_app');
        $membership = AppUser::firstOrCreate(['app_id' => $app->id, 'user_id' => $user->id], ['is_active' => true]);
        $membership = AppUser::whereKey($membership->id)->lockForUpdate()->firstOrFail();
        abort_unless($membership->is_active, 403, 'Uygulama üyeliği aktif değil.');
        $membership->update(['last_seen_at' => now()]);
        $device = Device::firstOrCreate([
            'app_id' => $app->id, 'user_id' => $user->id, 'installation_id' => $request->input('device_id'),
        ], ['name' => $request->input('device_name'), 'platform' => $request->input('platform')]);
        $device->update(['name' => $request->input('device_name'), 'platform' => $request->input('platform'), 'last_seen_at' => now(), 'revoked_at' => null]);
        $device->tokens()->delete();
        $expiresAt = now()->addDays(30);
        $token = $user->createToken($request->input('device_name'), ['app:'.$app->id], $expiresAt);
        $token->accessToken->forceFill(['device_id' => $device->id])->save();

        return response()->json(['data' => [
            'token' => $token->plainTextToken, 'token_type' => 'Bearer', 'expires_at' => $expiresAt->toIso8601String(),
            'device' => ['id' => $device->id, 'installation_id' => $device->installation_id, 'platform' => $device->platform],
            'user' => new UserResource($user), 'app' => new AppResource($app),
        ]], $status)->header('Cache-Control', 'no-store, private');
    }

    public function me(Request $request): JsonResponse
    {
        $membership = $request->attributes->get('app_membership');

        return response()->json(['data' => ['user' => new UserResource($request->user()),
            'app' => new AppResource($request->attributes->get('mobile_app')),
            'membership' => ['is_active' => $membership->is_active, 'joined_at' => $membership->joined_at->toIso8601String(), 'last_seen_at' => $membership->last_seen_at?->toIso8601String()],
        ]]);
    }

    public function logout(Request $request): Response
    {
        DB::transaction(function () use ($request): void {
            $membership = $request->attributes->get('app_membership');
            AppUser::whereKey($membership->id)->lockForUpdate()->firstOrFail();
            $device = $request->attributes->get('mobile_device');
            Device::whereKey($device->id)->lockForUpdate()->firstOrFail()->update(['revoked_at' => now()]);
            $device->tokens()->delete();
        });

        return response()->noContent();
    }

    public function meta(Request $request): AppResource
    {
        return new AppResource($request->attributes->get('mobile_app'));
    }
}
