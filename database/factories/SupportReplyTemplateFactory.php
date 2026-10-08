<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SupportReplyTemplateFactory extends Factory
{
    public function definition(): array
    {
        return ['app_id' => null, 'name' => fake()->words(3, true), 'locale' => 'tr', 'body' => fake()->paragraph(), 'is_active' => true];
    }
}
