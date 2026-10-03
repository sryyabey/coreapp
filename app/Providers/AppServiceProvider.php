<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('mobile-auth', fn (Request $request): array => [
            Limit::perMinute(20)->by('ip:'.$request->ip()),
            Limit::perMinute(5)->by('auth:'.$request->route('app').':'.(is_string($request->input('email')) ? mb_strtolower(trim($request->input('email'))) : '').':'.$request->ip()),
        ]);
        RateLimiter::for('mobile-api', fn (Request $request): Limit => Limit::perMinute(120)->by($request->route('app').':'.$request->user()->id));
    }
}
