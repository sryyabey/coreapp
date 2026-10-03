<?php

namespace Database\Factories\ShiftCal;

use App\Models\App;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CloudBackupFactory extends Factory
{
    public function definition(): array
    {
        return ['app_id' => App::factory(), 'user_id' => User::factory(), 'revision' => 1, 'payload' => ['schema_version' => 1, 'entries' => []]];
    }
}
