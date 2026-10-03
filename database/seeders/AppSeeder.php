<?php

namespace Database\Seeders;

use App\Models\App;
use Illuminate\Database\Seeder;

class AppSeeder extends Seeder
{
    public function run(): void
    {
        App::firstOrCreate(['slug' => 'shiftcal'], ['name' => 'ShiftCal', 'description' => 'Vardiya ve zaman planlama uygulaması.', 'is_active' => true]);
    }
}
