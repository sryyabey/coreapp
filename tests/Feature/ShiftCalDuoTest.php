<?php

namespace Tests\Feature;

use App\Http\FeatureAccess;
use App\Models\App;
use App\Models\AppUser;
use App\Models\Device;
use App\Models\StoreApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\GrantsShiftCalDuo;
use Tests\TestCase;

class ShiftCalDuoTest extends TestCase
{
    use GrantsShiftCalDuo;
    use RefreshDatabase;

    private function link(AppUser $owner, AppUser $partner): void
    {
        $connection = (string) Str::uuid();
        foreach ([[$owner, $partner], [$partner, $owner]] as [$first, $second]) {
            DB::table('shiftcal_partner_links')->insert(['app_id' => $owner->app_id, 'user_id' => $first->user_id,
                'partner_user_id' => $second->user_id, 'connection_id' => $connection, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function test_current_partner_receives_trial_and_loses_access_on_disconnect_or_expiry(): void
    {
        $app = App::factory()->create(['slug' => 'shiftcal']);
        $owner = AppUser::factory()->create(['app_id' => $app->id]);
        $partner = AppUser::factory()->create(['app_id' => $app->id]);
        $purchase = $this->grantDuo($owner, ['is_trial' => true, 'auto_renews' => true]);
        $this->link($owner, $partner);
        $access = app(FeatureAccess::class);
        $summary = $access->summary($app, $partner->user);
        $this->assertCount(3, $summary['rights']);
        $this->assertSame('partner', $summary['subscriptions'][0]['source']);
        $this->assertTrue($summary['subscriptions'][0]['is_trial']);
        DB::table('shiftcal_partner_links')->delete();
        $this->assertFalse($access->allows($app, $partner->user, 'shiftcal.backup'));
        $replacement = AppUser::factory()->create(['app_id' => $app->id]);
        $this->link($owner, $replacement);
        $this->assertTrue($access->allows($app, $replacement->user, 'shiftcal.reports'));
        $this->assertFalse($access->allows($app, $partner->user, 'shiftcal.reports'));
        $purchase->update(['expires_at' => now()]);
        $this->assertFalse($access->allows($app, $replacement->user, 'shiftcal.reports'));
    }

    public function test_unshared_revoked_inactive_and_sandbox_purchases_do_not_leak_access(): void
    {
        $app = App::factory()->create(['slug' => 'shiftcal']);
        $owner = AppUser::factory()->create(['app_id' => $app->id]);
        $partner = AppUser::factory()->create(['app_id' => $app->id]);
        $purchase = $this->grantDuo($owner);
        $this->link($owner, $partner);
        $access = app(FeatureAccess::class);
        $purchase->storeProduct->subscriptionPlan->update(['shares_with_partner' => false]);
        $this->assertFalse($access->allows($app, $partner->user, 'shiftcal.together'));
        $this->assertTrue($access->allows($app, $owner->user, 'shiftcal.together'));
        $purchase->storeProduct->subscriptionPlan->update(['shares_with_partner' => true]);
        foreach (['revoked', 'on_hold', 'pending'] as $state) {
            $purchase->update(['status' => $state]);
            $this->assertFalse($access->allows($app, $partner->user, 'shiftcal.together'));
        }
        $purchase->update(['status' => 'active', 'environment' => 'sandbox']);
        DB::table('store_products')->where('id', $purchase->store_product_id)->update(['environment' => 'sandbox']);
        $this->assertFalse($access->allows($app, $partner->user, 'shiftcal.together'));
        config(['billing.apps.shiftcal.feature_environment' => 'sandbox', 'billing.apps.shiftcal.allow_sandbox' => true]);
        $this->assertTrue($access->allows($app, $partner->user, 'shiftcal.together'));
        $owner->update(['is_active' => false]);
        $this->assertFalse($access->allows($app, $partner->user, 'shiftcal.together'));
    }

    public function test_free_api_rejects_paid_operations_but_allows_personal_events_and_join_status(): void
    {
        $app = App::factory()->create(['slug' => 'shiftcal']);
        $member = AppUser::factory()->create(['app_id' => $app->id]);
        $device = Device::factory()->create(['app_id' => $app->id, 'user_id' => $member->user_id]);
        $token = $member->user->createToken('phone', ['app:'.$app->id]);
        $token->accessToken->forceFill(['device_id' => $device->id])->save();
        $this->withToken($token->plainTextToken);
        $prefix = '/api/v1/apps/shiftcal/shiftcal';
        foreach (['/cloud-backup', '/partner/schedule', '/partner/plans'] as $path) {
            $this->getJson($prefix.$path)->assertForbidden();
        }
        $this->postJson($prefix.'/cloud-backup')->assertForbidden();
        $this->postJson($prefix.'/partner/invitation')->assertForbidden();
        $this->getJson($prefix.'/events')->assertOk();
        $this->getJson($prefix.'/partner')->assertOk();
        $this->postJson($prefix.'/partner/preview', ['code' => 'INVALID'])->assertUnprocessable();
        $this->grantDuo($member);
        $this->getJson($prefix.'/cloud-backup')->assertOk();
    }

    public function test_catalog_command_is_preview_by_default_and_idempotent_when_applied(): void
    {
        $app = App::factory()->create(['slug' => 'shiftcal']);
        StoreApp::factory()->create(['app_id' => $app->id, 'platform' => 'ios']);
        StoreApp::factory()->create(['app_id' => $app->id, 'platform' => 'android']);
        $this->artisan('shiftcal:configure-duo')->assertSuccessful();
        $this->assertDatabaseCount('subscription_plans', 0);
        for ($i = 0; $i < 2; $i++) {
            $this->artisan('shiftcal:configure-duo', ['--apply' => true])->assertSuccessful();
        }
        $this->assertDatabaseCount('subscription_plans', 1);
        $this->assertDatabaseCount('store_products', 2);
        $this->assertDatabaseHas('store_products', ['product_id' => 'shiftcal.duo.yearly', 'base_plan_id' => '']);
    }
}
