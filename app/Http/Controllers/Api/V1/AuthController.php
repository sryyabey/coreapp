<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AppleLoginRequest;
use App\Http\Requests\Api\GoogleLoginRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Resources\Api\AppResource;
use App\Http\Resources\Api\UserResource;
use App\Models\AppUser;
use App\Models\Device;
use App\Models\User;
use App\Services\AppleIdTokenVerifier;
use App\Services\GoogleIdTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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

    public function google(GoogleLoginRequest $request, GoogleIdTokenVerifier $verifier): JsonResponse
    {
        $app = $request->attributes->get('mobile_app');
        $claims = $verifier->verify($request->validated('id_token'), $app->slug);

        return DB::transaction(function () use ($request, $claims): JsonResponse {
            $user = User::where('google_id', $claims['sub'])->lockForUpdate()->first();
            if (! $user) {
                $email = mb_strtolower($claims['email']);
                $user = User::where('email', $email)->lockForUpdate()->first();
                if ($user) {
                    $authoritative = str_ends_with($email, '@gmail.com') || ! empty($claims['hd']);
                    if (! $authoritative || $user->google_id !== null) {
                        throw ValidationException::withMessages(['id_token' => ['Bu hesap için önce e-posta ve şifrenizle giriş yapın.']]);
                    }
                } else {
                    $user = new User([
                        'name' => mb_substr($claims['name'] ?? strstr($email, '@', true), 0, 255),
                        'email' => $email,
                        'password' => Str::random(64),
                    ]);
                }
                $user->forceFill(['google_id' => $claims['sub'], 'email_verified_at' => $user->email_verified_at ?? now()])->save();
            }

            return $this->issueToken($request, $user);
        });
    }

    public function appleChallenge(Request $request): JsonResponse
    {
        $input = $request->validate(['device_id' => ['required', 'uuid'], 'platform' => ['required', 'in:ios']]);
        $app = $request->attributes->get('mobile_app');
        $id = (string) Str::uuid();
        $nonce = bin2hex(random_bytes(32));
        Cache::put('apple-login:'.$app->id.':'.$id, ['nonce' => $nonce, ...$input], now()->addMinutes(5));

        return response()->json(['data' => ['challenge_id' => $id, 'nonce' => $nonce]])->header('Cache-Control', 'no-store, private');
    }

    public function apple(AppleLoginRequest $request, AppleIdTokenVerifier $verifier): JsonResponse
    {
        $app = $request->attributes->get('mobile_app');
        $key = 'apple-login:'.$app->id.':'.$request->validated('challenge_id');
        $challenge = Cache::get($key);
        abort_unless(is_array($challenge) && $challenge['device_id'] === $request->validated('device_id') && $challenge['platform'] === $request->validated('platform'), 401, 'Apple giriş isteği geçersiz veya süresi dolmuş.');
        $claims = $verifier->verify($request->validated('id_token'), $app->slug, $challenge['nonce']);

        return Cache::lock($key.':lock', 15)->block(5, function () use ($key, $request, $claims): JsonResponse {
            abort_unless(Cache::pull($key) !== null, 401, 'Apple giriş isteği daha önce kullanılmış.');

            return DB::transaction(function () use ($request, $claims): JsonResponse {
                $user = User::where('apple_id', $claims['sub'])->lockForUpdate()->first();
                if (! $user) {
                    $email = mb_strtolower($claims['email'] ?? '');
                    if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! in_array($claims['email_verified'] ?? false, [true, 'true'], true)) {
                        throw ValidationException::withMessages(['id_token' => ['Apple hesabının doğrulanmış e-posta adresi gerekli.']]);
                    }
                    if (User::where('email', $email)->exists()) {
                        throw ValidationException::withMessages(['id_token' => ['Bu e-posta ile bir hesabınız var. Mevcut giriş yönteminizi kullanın.']]);
                    }
                    $user = new User([
                        'name' => $request->validated('name') ?: 'Apple User',
                        'email' => $email,
                        'password' => Str::random(64),
                    ]);
                    $user->forceFill(['apple_id' => $claims['sub'], 'email_verified_at' => now()])->save();
                }

                return $this->issueToken($request, $user);
            });
        });
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
