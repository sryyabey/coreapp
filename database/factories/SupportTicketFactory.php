<?php

namespace Database\Factories;

use App\Models\AppUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupportTicketFactory extends Factory
{
    public function definition(): array
    {
        $membership = AppUser::factory()->create();

        return ['app_id' => $membership->app_id, 'user_id' => $membership->user_id, 'subject' => fake()->sentence(), 'status' => 'open', 'locale' => 'tr', 'client_request_id' => fake()->uuid()];
    }
}
