<?php

namespace App\Http\Middleware;

use App\Models\App;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveMobileApp
{
    public function handle(Request $request, Closure $next): Response
    {
        $app = App::where('slug', $request->route('app'))->where('is_active', true)->firstOrFail();
        $request->attributes->set('mobile_app', $app);

        return $next($request);
    }
}
