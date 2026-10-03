<?php

namespace Database\Factories;

use App\Models\App;
use App\Models\StoreApp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreApp>
 */
class StoreAppFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'app_id' => App::factory(),
            'platform' => 'android',
            'identifier' => 'dev.sryya.app'.fake()->unique()->numerify('########'),
            'is_active' => true,
        ];
    }
}
