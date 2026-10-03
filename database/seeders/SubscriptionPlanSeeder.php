<?php

namespace Database\Seeders;

use App\Models\App;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $app = App::where('slug', 'shiftcal')->first();
        if ($app) {
            SubscriptionPlan::firstOrCreate(['app_id' => $app->id, 'slug' => 'premium-yearly'], [
                'features' => ['premium'], 'name' => 'ShiftCal Premium', 'period' => 'yearly', 'catalog_price' => '14.99', 'currency' => 'USD', 'is_active' => true,
            ]);
        }
    }
}
