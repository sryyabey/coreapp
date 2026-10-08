<?php

namespace Database\Seeders;

use App\Models\SupportReplyTemplate;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SupportSetupSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [];
        foreach (['ViewAny:SupportTicket', 'View:SupportTicket', 'Update:SupportTicket', 'ViewAllApps:SupportTicket', 'ManageAccess:SupportTicket', 'ManageTemplates:SupportTicket'] as $name) {
            $permissions[] = Permission::findOrCreate($name, 'web');
        }
        $role = Role::where('name', config('filament-shield.super_admin.name'))->where('guard_name', 'web')->first();
        if ($role) {
            $role->givePermissionTo($permissions);
        }
        foreach ([['tr', 'Ek bilgi isteği', 'Sorunun yaşandığı ekranı ve izlediğin adımları paylaşabilir misin? Uygulama sürümünü ve telefon modelini de eklersen inceleyebiliriz.'], ['en', 'Request more details', 'Could you share the screen and the steps that led to the issue? Please include your app version and phone model so we can investigate.']] as [$locale,$name,$body]) {
            SupportReplyTemplate::firstOrCreate(['app_id' => null, 'name' => $name, 'locale' => $locale], ['body' => $body, 'is_active' => true]);
        }
    }
}
