<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupportMessageFactory extends Factory
{
    public function definition(): array
    {
        return ['support_ticket_id' => SupportTicket::factory(), 'sender_id' => null, 'sender_type' => 'user', 'body' => fake()->paragraph(), 'client_request_id' => fake()->uuid()];
    }
}
