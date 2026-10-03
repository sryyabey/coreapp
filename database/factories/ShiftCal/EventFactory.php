<?php

namespace Database\Factories\ShiftCal;

use App\Models\App;
use App\Models\ShiftCal\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'app_id' => App::factory(), 'user_id' => User::factory(), 'type' => 'work', 'starts_at' => now(), 'ends_at' => now()->addHours(9), 'timezone' => 'Europe/Istanbul', 'color' => '#299B56',
        ];
    }
}
