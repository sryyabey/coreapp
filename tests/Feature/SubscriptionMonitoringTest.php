<?php

namespace Tests\Feature;

use App\Filament\Resources\Purchases\Pages\ManagePurchases;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\StoreNotifications\Pages\ManageStoreNotifications;
use App\Filament\Resources\StoreNotifications\StoreNotificationResource;
use App\Models\Purchase;
use App\Models\StoreNotification;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SubscriptionMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('manage'));
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('panel_user', 'web'));
        foreach (['Purchase', 'StoreNotification'] as $model) {
            foreach (['ViewAny', 'View'] as $action) {
                $user->givePermissionTo(Permission::findOrCreate($action.':'.$model, 'web'));
            }
        }
        $this->actingAs($user);
    }

    public function test_purchase_monitor_can_search_and_filter_without_exposing_store_proof(): void
    {
        $active = Purchase::factory()->create(['proof' => 'private-purchase-proof', 'identity' => 'private-purchase-identity', 'is_trial' => true]);
        $expired = Purchase::factory()->create(['status' => 'expired', 'expires_at' => now()->subDay()]);
        $page = Livewire::test(ManagePurchases::class)->assertSuccessful()->assertCanSeeTableRecords([$active, $expired]);
        $page->filterTable('status', 'active')->assertCanSeeTableRecords([$active])->assertCanNotSeeTableRecords([$expired]);
        $page->resetTableFilters()->searchTable($active->user->email)->assertCanSeeTableRecords([$active])->assertCanNotSeeTableRecords([$expired]);
        $page->callTableAction('view', $active)->assertSee($active->user->email)->assertDontSee('private-purchase-proof')->assertDontSee('private-purchase-identity');
        $this->assertFalse(PurchaseResource::canCreate());
        $this->assertFalse(PurchaseResource::canEdit($active));
        $this->assertFalse(PurchaseResource::canDelete($active));
    }

    public function test_store_notifications_can_be_filtered_by_processing_error_and_app(): void
    {
        $failed = StoreNotification::factory()->create(['status' => 'failed', 'last_error' => 'PurchaseNotLinked', 'proof' => 'private-notification-proof']);
        $processed = StoreNotification::factory()->create(['status' => 'processed', 'processed_at' => now()]);
        $page = Livewire::test(ManageStoreNotifications::class)->assertSuccessful()->assertCanSeeTableRecords([$failed, $processed]);
        $page->filterTable('attention')->assertCanSeeTableRecords([$failed])->assertCanNotSeeTableRecords([$processed]);
        $page->resetTableFilters()->filterTable('app', $failed->storeApp->app_id)->assertCanSeeTableRecords([$failed])->assertCanNotSeeTableRecords([$processed]);
        $page->callTableAction('view', $failed)->assertSee('PurchaseNotLinked')->assertDontSee('private-notification-proof');
        $this->assertFalse(StoreNotificationResource::canCreate());
        $this->assertFalse(StoreNotificationResource::canEdit($failed));
        $this->assertFalse(StoreNotificationResource::canDelete($failed));
    }

    public function test_monitoring_requires_panel_access_and_resource_permissions(): void
    {
        $mobileUser = User::factory()->create();
        $this->actingAs($mobileUser)->get('/manage/purchases')->assertForbidden();
        $this->get('/manage/store-notifications')->assertForbidden();
        $mobileUser->assignRole(Role::findOrCreate('panel_user', 'web'));
        Livewire::test(ManagePurchases::class)->assertForbidden();
        Livewire::test(ManageStoreNotifications::class)->assertForbidden();
    }

    public function test_expired_active_records_are_excluded_from_unexpired_filter(): void
    {
        $current = Purchase::factory()->create(['status' => 'canceled', 'expires_at' => now()->addDay()]);
        $stale = Purchase::factory()->create(['status' => 'active', 'expires_at' => now()->subDay()]);
        Livewire::test(ManagePurchases::class)->filterTable('unexpired')->assertCanSeeTableRecords([$current])->assertCanNotSeeTableRecords([$stale]);
    }

    public function test_purchase_filters_keep_errors_scoped_to_the_selected_application(): void
    {
        $failure = Purchase::factory()->create(['check_failures' => 1]);
        $otherAppFailure = Purchase::factory()->create(['last_check_error' => 'StoreUnavailable']);
        $healthy = Purchase::factory()->create(['app_id' => $failure->app_id]);
        Livewire::test(ManagePurchases::class)
            ->filterTable('app', $failure->app_id)
            ->filterTable('check_errors')
            ->assertCanSeeTableRecords([$failure])
            ->assertCanNotSeeTableRecords([$otherAppFailure, $healthy]);
    }
}
