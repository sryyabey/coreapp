<?php

namespace App\Http;

use App\Models\StoreApp;

class BillingEnvironment
{
    public static function assertAllowed(StoreApp $store, string $environment): void
    {
        abort_unless(in_array($environment, ['production', 'sandbox'], true), 422, 'Geçersiz mağaza ortamı.');
        abort_if($environment === 'sandbox' && ! config('billing.apps.'.$store->app->slug.'.allow_sandbox', config('billing.allow_sandbox')), 422, 'Bu uygulama için sandbox işlemleri kapalı.');
    }
}
