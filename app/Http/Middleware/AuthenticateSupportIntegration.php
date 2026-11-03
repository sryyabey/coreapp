<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateSupportIntegration
{
    public function handle(Request $request, Closure $next): Response
    {
        $app = $request->attributes->get('mobile_app');
        $token = $request->bearerToken();
        abort_unless(is_string($token) && $token !== '' && $app->support_api_key_hash !== null
            && hash_equals($app->support_api_key_hash, hash('sha256', $token)), 401);

        return $next($request);
    }
}
