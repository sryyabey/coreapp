<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureShiftCalApp
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->attributes->get('mobile_app')?->slug === 'shiftcal', 404);

        return $next($request);
    }
}
