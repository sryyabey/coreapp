<?php

namespace Tests\Feature;

use App\Models\App;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('manage'));
    }

    public function test_guest_is_redirected_to_panel_login(): void
    {
        $this->get('/manage')->assertRedirect('/manage/login');
    }

    public function test_mobile_user_is_denied_even_with_direct_resource_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('ViewAny:App', 'web'));
        $this->actingAs($user, 'web');
        $this->get('/manage')->assertForbidden();
        $this->get('/manage/apps')->assertForbidden();
        $this->get('/manage/shield/roles')->assertForbidden();
    }

    public function test_mobile_user_cannot_log_in_to_filament(): void
    {
        $user = User::factory()->create();
        Livewire::test(Login::class)->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')->assertHasFormErrors(['email']);
        $this->assertGuest('web');
    }

    public function test_panel_user_can_enter_but_resource_permissions_are_separate(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('panel_user', 'web'));
        $this->actingAs($user, 'web');
        $this->get('/manage')->assertOk();
        $this->get('/manage/apps')->assertForbidden();
        $user->givePermissionTo(Permission::findOrCreate('ViewAny:App', 'web'));
        $this->get('/manage/apps')->assertOk();
        $this->get('/manage/shield/roles')->assertForbidden();
    }

    public function test_super_admin_can_log_in_and_disabled_role_does_not_grant_access(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('super_admin', 'web'));
        Livewire::test(Login::class)->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($user, 'web');
        $this->get('/manage')->assertOk();
        config(['filament-shield.super_admin.enabled' => false]);
        $this->assertFalse($user->canAccessPanel(Filament::getPanel('manage')));
        $this->get('/manage')->assertForbidden();
    }

    public function test_revoking_panel_role_blocks_existing_session(): void
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate('panel_user', 'web');
        $user->assignRole($role);
        $this->actingAs($user, 'web')->get('/manage')->assertOk();
        $user->removeRole($role);
        $this->get('/manage')->assertForbidden();
        $this->assertFalse($user->canAccessPanel(Panel::make()->id('other')));
    }

    public function test_mobile_token_does_not_authenticate_panel_and_role_payload_is_rejected(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('super_admin', 'web'));
        $token = $user->createToken('phone', ['app:1'])->plainTextToken;
        $this->withToken($token)->get('/manage')->assertRedirect('/manage/login');
        $app = App::factory()->create();
        $this->postJson('/api/v1/apps/'.$app->slug.'/auth/register', [
            'name' => 'Mobile', 'email' => 'mobile@example.com', 'password' => 'password123', 'password_confirmation' => 'password123',
            'device_id' => fake()->uuid(), 'device_name' => 'phone', 'platform' => 'ios',
            'roles' => ['super_admin'], 'permissions' => ['ViewAny:Role'], 'is_admin' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors(['roles', 'permissions', 'is_admin']);
        $this->assertDatabaseMissing('users', ['email' => 'mobile@example.com']);
    }
}
