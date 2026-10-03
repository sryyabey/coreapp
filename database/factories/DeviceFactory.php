<?php

namespace Database\Factories;

use App\Models\App;
use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'app_id' => App::factory(), 'user_id' => User::factory(),
            'installation_id' => fake()->uuid(), 'name' => 'Test phone', 'platform' => 'ios',
        ];
    }
}
