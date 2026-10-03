<?php

namespace Database\Factories\ShiftCal;

use App\Models\App;
use App\Models\ShiftCal\WageSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WageSetting>
 */
class WageSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'app_id' => App::factory(), 'user_id' => User::factory(), 'hourly_rate' => '100.00', 'overtime_multiplier' => '1.50', 'currency' => 'TRY', 'weekly_target_minutes' => 2400,
        ];
    }
}
