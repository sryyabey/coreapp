<?php

namespace Database\Seeders;

use App\Models\App;
use Illuminate\Database\Seeder;

class MinimalFastingSupportSeeder extends Seeder
{
    public function run(): void
    {
        App::firstOrCreate(['slug' => 'minimal-fasting'], [
            'name' => 'Minimal Fasting', 'is_active' => true,
            'support_email' => config('support.minimal_fasting_email'),
            'website_url' => 'https://sryya.dev/minimal-fasting',
            'privacy_policy_url' => 'https://sryya.dev/minimal-fasting/privacy',
        ]);
    }
}
