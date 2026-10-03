<?php

namespace Database\Factories;

use App\Models\StoreApp;
use App\Models\StoreNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreNotification>
 */
class StoreNotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_app_id' => StoreApp::factory(), 'event_id' => fake()->uuid(), 'type' => 'RENEWED',
            'environment' => 'production', 'identity' => fake()->uuid(), 'proof' => fake()->uuid(), 'status' => 'pending',
        ];
    }
}
