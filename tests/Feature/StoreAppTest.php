<?php

namespace Tests\Feature;

use App\Filament\Resources\StoreApps\Pages\CreateStoreApp;
use App\Filament\Resources\StoreApps\Pages\EditStoreApp;
use App\Filament\Resources\StoreApps\Pages\ListStoreApps;
use App\Models\App;
use App\Models\StoreApp;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class StoreAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('manage'));
        $user = User::factory()->create();
        foreach (['ViewAny', 'View', 'Create', 'Update'] as $action) {
            $user->givePermissionTo(Permission::findOrCreate($action.':StoreApp', 'web'));
        }
        $this->actingAs($user);
    }

    public function test_store_records_can_be_created_and_updated(): void
    {
        $app = App::factory()->create();
        Livewire::test(CreateStoreApp::class)->fillForm([
            'app_id' => $app->id, 'platform' => 'ios', 'identifier' => 'dev.sryya.shiftcal',
            'apple_app_id' => '1234567890', 'is_active' => true,
        ])->call('create')->assertHasNoFormErrors();
        $store = StoreApp::sole();
        $this->assertCount(1, $app->storeApps);
        Livewire::test(EditStoreApp::class, ['record' => $store->id])
            ->fillForm(['is_active' => false])->call('save')->assertHasNoFormErrors();
        $this->assertFalse($store->fresh()->is_active);
        StoreApp::factory()->create(['app_id' => $app->id, 'platform' => 'android']);
        $this->assertCount(2, $app->fresh()->storeApps);
    }

    public function test_duplicate_platform_is_rejected(): void
    {
        $store = StoreApp::factory()->create();
        Livewire::test(CreateStoreApp::class)->fillForm([
            'app_id' => $store->app_id, 'platform' => 'android', 'identifier' => 'dev.sryya.another',
        ])->call('create')->assertHasFormErrors(['platform' => 'unique']);
    }

    public function test_invalid_and_reused_identifiers_are_rejected(): void
    {
        $store = StoreApp::factory()->create();
        $app = App::factory()->create();
        Livewire::test(CreateStoreApp::class)->fillForm([
            'app_id' => $app->id, 'platform' => 'android', 'identifier' => $store->identifier,
        ])->call('create')->assertHasFormErrors(['identifier' => 'unique']);
        Livewire::test(CreateStoreApp::class)->fillForm([
            'app_id' => $app->id, 'platform' => 'ios', 'identifier' => 'invalid identifier', 'apple_app_id' => 'abc',
        ])->call('create')->assertHasFormErrors(['identifier' => 'regex', 'apple_app_id' => 'regex']);
    }

    public function test_database_enforces_unique_app_platform(): void
    {
        $store = StoreApp::factory()->create();
        $this->expectException(UniqueConstraintViolationException::class);
        StoreApp::factory()->create(['app_id' => $store->app_id]);
    }

    public function test_platform_filter_and_android_apple_id_cleanup(): void
    {
        $android = StoreApp::factory()->create(['apple_app_id' => '12345']);
        $ios = StoreApp::factory()->create(['platform' => 'ios']);
        $this->assertNull($android->fresh()->apple_app_id);
        Livewire::test(ListStoreApps::class)->filterTable('platform', 'ios')
            ->assertCanSeeTableRecords([$ios])->assertCanNotSeeTableRecords([$android]);
    }

    public function test_store_management_requires_permissions(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(ListStoreApps::class)->assertForbidden();
        Livewire::test(CreateStoreApp::class)->assertForbidden();
        Livewire::test(EditStoreApp::class, ['record' => StoreApp::factory()->create()->id])->assertForbidden();
    }
}
