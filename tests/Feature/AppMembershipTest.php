<?php

namespace Tests\Feature;

use App\Filament\Resources\AppUsers\Pages\CreateAppUser;
use App\Filament\Resources\AppUsers\Pages\EditAppUser;
use App\Filament\Resources\AppUsers\Pages\ListAppUsers;
use App\Models\App;
use App\Models\AppUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AppMembershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('manage'));
        $user = User::factory()->create();
        foreach (['ViewAny', 'View', 'Create', 'Update'] as $action) {
            $user->givePermissionTo(Permission::findOrCreate($action.':AppUser', 'web'));
        }
        $this->actingAs($user);
    }

    public function test_membership_can_be_created_and_deactivated(): void
    {
        $app = App::factory()->create();
        $user = User::factory()->create();
        Livewire::test(CreateAppUser::class)->fillForm([
            'app_id' => $app->id, 'user_id' => $user->id,
            'is_active' => true, 'joined_at' => now()->format('Y-m-d H:i:s'),
        ])->call('create')->assertHasNoFormErrors();
        $membership = AppUser::sole();
        $this->assertSame($user->id, $membership->user->id);
        $this->assertSame($app->id, $membership->app->id);
        $this->assertCount(1, $user->appMemberships);
        $this->assertCount(1, $app->appMemberships);
        Livewire::test(EditAppUser::class, ['record' => $membership->id])
            ->fillForm(['is_active' => false])->call('save')->assertHasNoFormErrors();
        $this->assertFalse($membership->fresh()->is_active);
    }

    public function test_duplicate_membership_is_rejected_by_form(): void
    {
        $membership = AppUser::factory()->create();
        Livewire::test(CreateAppUser::class)->fillForm([
            'app_id' => $membership->app_id, 'user_id' => $membership->user_id,
            'joined_at' => now()->format('Y-m-d H:i:s'),
        ])->call('create')->assertHasFormErrors(['user_id' => 'unique']);
        $this->assertDatabaseCount('app_users', 1);
    }

    public function test_database_rejects_duplicate_memberships(): void
    {
        $membership = AppUser::factory()->create();
        $this->expectException(UniqueConstraintViolationException::class);
        AppUser::factory()->create(['app_id' => $membership->app_id, 'user_id' => $membership->user_id]);
    }

    public function test_user_can_join_multiple_apps_and_filter_by_app(): void
    {
        $first = AppUser::factory()->create();
        $second = AppUser::factory()->create(['user_id' => $first->user_id]);
        Livewire::test(ListAppUsers::class)->filterTable('app', $first->app_id)
            ->assertCanSeeTableRecords([$first])->assertCanNotSeeTableRecords([$second]);
    }

    public function test_membership_requires_permissions(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(ListAppUsers::class)->assertForbidden();
        Livewire::test(CreateAppUser::class)->assertForbidden();
        Livewire::test(EditAppUser::class, ['record' => AppUser::factory()->create()->id])->assertForbidden();
    }
}
