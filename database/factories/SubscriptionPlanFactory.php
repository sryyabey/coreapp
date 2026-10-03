<?php

namespace Database\Factories;

use App\Models\App;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'app_id' => App::factory(), 'name' => 'Premium', 'slug' => fake()->unique()->slug(),
            'features' => ['premium'],
            'period' => 'yearly', 'catalog_price' => '14.99', 'currency' => 'USD', 'is_active' => true,
        ];
    }
}
