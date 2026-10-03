<?php

namespace Tests\Feature;

use App\Filament\Resources\Apps\Pages\EditApp;
use App\Filament\Resources\Apps\RelationManagers\AppMembershipsRelationManager;
use App\Filament\Resources\Apps\RelationManagers\StoreAppsRelationManager;
use App\Filament\Resources\AppUsers\Pages\ListAppUsers;
use App\Models\App;
use App\Models\AppUser;
use App\Models\StoreApp;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AppManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('manage'));
        $user = User::factory()->create();
        foreach (['ViewAny:AppUser', 'Create:AppUser', 'Update:AppUser', 'ViewAny:StoreApp', 'Create:StoreApp', 'View:App', 'Update:App'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $this->actingAs($user);
    }

    public function test_application_relations_show_only_owned_records(): void
    {
        $app = App::factory()->create();
        $own = AppUser::factory()->create(['app_id' => $app->id]);
        $foreign = AppUser::factory()->create();
        Livewire::test(AppMembershipsRelationManager::class, ['ownerRecord' => $app, 'pageClass' => EditApp::class])
            ->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$foreign]);
        $store = StoreApp::factory()->create(['app_id' => $app->id]);
        $foreignStore = StoreApp::factory()->create();
        Livewire::test(StoreAppsRelationManager::class, ['ownerRecord' => $app, 'pageClass' => EditApp::class])
            ->assertCanSeeTableRecords([$store])->assertCanNotSeeTableRecords([$foreignStore]);
    }

    public function test_membership_can_be_added_from_application(): void
    {
        $app = App::factory()->create();
        $user = User::factory()->create();
        Livewire::test(AppMembershipsRelationManager::class, ['ownerRecord' => $app, 'pageClass' => EditApp::class])
            ->callAction(TestAction::make('create')->table(), data: ['user_id' => $user->id, 'is_active' => true, 'joined_at' => now()->format('Y-m-d H:i:s')])
            ->assertHasNoActionErrors();
        $this->assertDatabaseHas('app_users', ['app_id' => $app->id, 'user_id' => $user->id]);
    }

    public function test_membership_status_action_requires_update_permission(): void
    {
        $membership = AppUser::factory()->create();
        Livewire::test(ListAppUsers::class)->callAction(TestAction::make('toggleMembership')->table($membership));
        $this->assertFalse($membership->fresh()->is_active);
        auth()->user()->revokePermissionTo('Update:AppUser');
        Livewire::test(ListAppUsers::class)->assertActionHidden(TestAction::make('toggleMembership')->table($membership));
    }

    public function test_relation_visibility_requires_own_resource_permission(): void
    {
        $app = App::factory()->create();
        auth()->user()->revokePermissionTo('ViewAny:AppUser');
        $this->assertFalse(AppMembershipsRelationManager::canViewForRecord($app, EditApp::class));
        $this->assertTrue(StoreAppsRelationManager::canViewForRecord($app, EditApp::class));
    }
}
