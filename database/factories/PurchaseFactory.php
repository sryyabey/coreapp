<?php

namespace Database\Factories;

use App\Models\Purchase;
use App\Models\StoreProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_product_id' => StoreProduct::factory(),
            'store_app_id' => fn (array $attributes) => StoreProduct::findOrFail($attributes['store_product_id'])->store_app_id,
            'app_id' => fn (array $attributes) => StoreProduct::findOrFail($attributes['store_product_id'])->subscriptionPlan->app_id,
            'user_id' => User::factory(), 'environment' => fn (array $attributes) => StoreProduct::findOrFail($attributes['store_product_id'])->environment, 'identity' => fake()->unique()->uuid(),
            'proof' => fake()->uuid(), 'status' => 'active', 'expires_at' => now()->addMonth(), 'verified_at' => now(),
        ];
    }
}
