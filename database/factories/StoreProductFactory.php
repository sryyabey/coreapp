<?php

namespace Database\Factories;

use App\Models\StoreApp;
use App\Models\StoreProduct;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreProduct>
 */
class StoreProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_plan_id' => SubscriptionPlan::factory(),
            'store_app_id' => fn (array $attributes) => StoreApp::factory()->create(['app_id' => SubscriptionPlan::findOrFail($attributes['subscription_plan_id'])->app_id])->id,
            'environment' => 'production',
            'product_id' => fake()->unique()->slug(), 'base_plan_id' => 'yearly', 'is_active' => true,
        ];
    }
}
