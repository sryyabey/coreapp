<?php

namespace App\Http\Middleware;

use App\Http\FeatureAccess;
use App\Models\App;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePaidFeature
{
    public function __construct(private FeatureAccess $access) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $app = $request->attributes->get('mobile_app');
        abort_unless($app instanceof App && $request->user() && $request->attributes->get('app_membership') && $this->access->allows($app, $request->user(), $feature), 403, 'Bu özellik için aktif abonelik gerekli.');

        return $next($request);
    }
}
