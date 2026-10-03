<?php

namespace Tests\Feature;

use App\Filament\Resources\Apps\Pages\CreateApp;
use App\Filament\Resources\Apps\Pages\EditApp;
use App\Filament\Resources\Apps\Pages\ListApps;
use App\Models\App;
use App\Models\User;
use Database\Seeders\AppSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AppCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('manage'));
        $user = User::factory()->create();
        foreach (['ViewAny', 'View', 'Create', 'Update'] as $action) {
            $user->givePermissionTo(Permission::findOrCreate($action.':App', 'web'));
        }
        $this->actingAs($user);
    }

    public function test_catalog_creates_and_updates_apps(): void
    {
        Livewire::test(CreateApp::class)->fillForm([
            'name' => 'ShiftCal', 'slug' => 'shiftcal', 'is_active' => true,
        ])->call('create')->assertHasNoFormErrors();

        $app = App::sole();
        Livewire::test(EditApp::class, ['record' => $app->getRouteKey()])
            ->fillForm(['name' => 'ShiftCal Pro', 'is_active' => false])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('ShiftCal Pro', $app->fresh()->name);
        $this->assertFalse($app->fresh()->is_active);
    }

    public function test_catalog_validates_unique_slug_and_links(): void
    {
        App::factory()->create(['slug' => 'shiftcal']);
        Livewire::test(CreateApp::class)->fillForm([
            'name' => 'Another app', 'slug' => 'shiftcal',
            'support_email' => 'invalid', 'website_url' => 'invalid',
        ])->call('create')->assertHasFormErrors([
            'slug' => 'unique', 'support_email' => 'email', 'website_url' => 'url',
        ]);
    }

    public function test_catalog_filters_inactive_apps(): void
    {
        $active = App::factory()->create();
        $inactive = App::factory()->create(['is_active' => false]);
        Livewire::test(ListApps::class)->filterTable('is_active', false)
            ->assertCanSeeTableRecords([$inactive])->assertCanNotSeeTableRecords([$active]);
    }

    public function test_catalog_requires_permissions(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(ListApps::class)->assertForbidden();
        Livewire::test(CreateApp::class)->assertForbidden();
    }

    public function test_shiftcal_seed_is_idempotent_and_preserves_edits(): void
    {
        $this->seed(AppSeeder::class);
        App::sole()->update(['name' => 'Custom name']);
        $this->seed(AppSeeder::class);
        $this->assertDatabaseCount('apps', 1);
        $this->assertSame('Custom name', App::sole()->name);
    }
}
