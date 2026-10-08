<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupportActivityFactory extends Factory
{
    public function definition(): array
    {
        return ['support_ticket_id' => SupportTicket::factory(), 'actor_id' => User::factory(), 'type' => 'note', 'body' => fake()->paragraph(), 'request_id' => fake()->uuid()];
    }
}
