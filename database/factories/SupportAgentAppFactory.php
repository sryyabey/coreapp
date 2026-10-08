<?php

namespace Database\Factories;

use App\Models\App;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SupportAgentAppFactory extends Factory
{
    public function definition(): array
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('panel_user', 'web'));
        foreach (['ViewAny:SupportTicket', 'View:SupportTicket', 'Update:SupportTicket'] as $name) {
            $user->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }

        return ['app_id' => App::factory(), 'user_id' => $user->id];
    }
}
