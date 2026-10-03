<?php

namespace Database\Factories;

use App\Models\App;
use App\Models\AppUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppUser>
 */
class AppUserFactory extends Factory
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
            'user_id' => User::factory(),
            'is_active' => true,
            'joined_at' => now(),
        ];
    }
}
