<?php

namespace App\Http\Controllers\Api\V1\ShiftCal;

use App\Http\Controllers\Controller;
use App\Services\AppleAuthorizationRevoker;
use App\Services\AppleIdTokenVerifier;
use App\Services\ShiftCal\AccountDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountDeletionController extends Controller
{
    public function challenge(Request $request, AppleAuthorizationRevoker $revoker): JsonResponse
    {
        $user = $request->user();
        $app = $request->attributes->get('mobile_app');
        if ($user->apple_id !== null) {
            $revoker->assertConfigured($app->slug);
        }
        $id = (string) Str::uuid();
        $nonce = bin2hex(random_bytes(32));
        Cache::put('delete-account:'.$id, [
            'app_id' => $app->id, 'user_id' => $user->id,
            'device_id' => $request->attributes->get('mobile_device')->id, 'nonce' => $nonce,
        ], now()->addMinutes(5));

        return response()->json(['data' => ['challenge_id' => $id, 'nonce' => $nonce, 'requires_apple' => $user->apple_id !== null]])->header('Cache-Control', 'no-store, private');
    }

    public function destroy(Request $request, AccountDeletionService $deletion, AppleIdTokenVerifier $verifier, AppleAuthorizationRevoker $revoker): Response
    {
        $input = $request->validate([
            'confirmed' => ['required', 'accepted'], 'challenge_id' => ['required', 'uuid'],
            'id_token' => ['nullable', 'string', 'max:16384'], 'authorization_code' => ['nullable', 'string', 'max:4096'],
            'user_id' => ['prohibited'], 'app_id' => ['prohibited'],
        ]);
        $app = $request->attributes->get('mobile_app');
        $key = 'delete-account:'.$input['challenge_id'];

        return Cache::lock($key.':lock', 40)->block(5, function () use ($request, $input, $app, $key, $deletion, $verifier, $revoker): Response {
            $challenge = Cache::get($key);
            abort_unless(is_array($challenge) && $challenge['app_id'] === $app->id && $challenge['user_id'] === $request->user()->id && $challenge['device_id'] === $request->attributes->get('mobile_device')->id, 401, 'Hesap silme isteği geçersiz veya süresi dolmuş.');
            if ($request->user()->apple_id !== null) {
                abort_unless(! empty($input['id_token']) && ! empty($input['authorization_code']), 422, 'Hesabı silmek için Apple ile tekrar doğrulayın.');
                $claims = $verifier->verify($input['id_token'], $app->slug, $challenge['nonce']);
                abort_unless(hash_equals($request->user()->apple_id, $claims['sub']), 401, 'Apple hesabı mevcut hesapla eşleşmiyor.');
                $revoker->revoke($input['authorization_code'], $app->slug, $claims['sub'], $challenge['nonce']);
            }
            DB::transaction(fn () => $deletion->delete($request->attributes->get('app_membership')));
            Cache::forget($key);

            return response()->noContent();
        });
    }
}
