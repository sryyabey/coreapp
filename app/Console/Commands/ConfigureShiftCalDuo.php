<?php

namespace App\Console\Commands;

use App\Models\App;
use App\Models\StoreProduct;
use App\Models\SubscriptionPlan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ConfigureShiftCalDuo extends Command
{
    protected $signature = 'shiftcal:configure-duo {--apply : Save catalog mappings} {--environment=production} {--android-product=shiftcal_duo} {--android-base-plan=yearly}';

    protected $description = 'Preview or configure the ShiftCal Duo yearly subscription catalog';

    public function handle(): int
    {
        $environment = $this->option('environment');
        if (! in_array($environment, ['production', 'sandbox'], true)) {
            $this->error('Environment must be production or sandbox.');

            return self::FAILURE;
        }
        $app = App::where('slug', 'shiftcal')->first();
        if (! $app) {
            $this->error('Create the ShiftCal app and store identifiers in the admin panel first.');

            return self::FAILURE;
        }
        $stores = $app->storeApps()->get();
        if ($stores->isEmpty() || ! preg_match('/^[a-zA-Z0-9_.-]+$/', (string) $this->option('android-product')) || ! preg_match('/^[a-zA-Z0-9_.-]+$/', (string) $this->option('android-base-plan'))) {
            $this->error('Store identifiers and valid Android product/base plan IDs are required.');

            return self::FAILURE;
        }
        $this->info('Duo: yearly, USD 14.99 reference, owner + current partner. Trial and local prices are configured in the stores.');
        foreach ($stores as $store) {
            $this->line($store->platform.': '.$store->identifier.' / '.($store->platform === 'ios' ? 'shiftcal.duo.yearly' : $this->option('android-product')).' / '.$environment);
        }
        if (! $this->option('apply')) {
            $this->comment('Preview only. Pass --apply to save.');

            return self::SUCCESS;
        }
        DB::transaction(function () use ($app, $stores, $environment): void {
            $plan = SubscriptionPlan::updateOrCreate(['app_id' => $app->id, 'slug' => 'duo-yearly'], [
                'name' => 'ShiftCal Duo Annual', 'period' => 'yearly', 'catalog_price' => '14.99', 'currency' => 'USD',
                'is_active' => true, 'shares_with_partner' => true, 'features' => ['shiftcal.together', 'shiftcal.reports', 'shiftcal.backup'],
            ]);
            foreach ($stores as $store) {
                StoreProduct::updateOrCreate([
                    'store_app_id' => $store->id, 'environment' => $environment,
                    'product_id' => $store->platform === 'ios' ? 'shiftcal.duo.yearly' : $this->option('android-product'),
                    'base_plan_id' => $store->platform === 'ios' ? '' : $this->option('android-base-plan'),
                ], ['subscription_plan_id' => $plan->id, 'is_active' => true]);
            }
        });
        $this->info('Duo catalog saved.');

        return self::SUCCESS;
    }
}
