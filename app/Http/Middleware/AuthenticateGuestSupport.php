<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateGuestSupport
{
    public function handle(Request $request, Closure $next): Response
    {
        $app = $request->attributes->get('mobile_app');
        abort_unless(in_array($app->slug, config('support.guest_apps', []), true), 404);
        $token = $request->bearerToken();
        $session = DB::table('support_guest_sessions')->where('app_id', $app->id)->where('external_user_id', $request->route('externalUser'))->first();
        abort_unless(is_string($token) && $token !== '' && $session !== null && hash_equals($session->token_hash, hash('sha256', $token)), 401);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
