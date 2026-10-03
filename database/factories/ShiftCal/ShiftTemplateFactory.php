<?php

namespace Database\Factories\ShiftCal;

use App\Models\App;
use App\Models\ShiftCal\ShiftTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftTemplate>
 */
class ShiftTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'app_id' => App::factory(), 'user_id' => User::factory(), 'name' => 'Gündüz', 'type' => 'work', 'start_time' => '08:00', 'end_time' => '17:00', 'end_day_offset' => 0, 'color' => '#299B56',
        ];
    }
}
