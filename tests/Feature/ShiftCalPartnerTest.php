<?php

namespace Tests\Feature;

use App\Models\App;
use App\Models\AppUser;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShiftCalPartnerTest extends TestCase
{
    use RefreshDatabase;

    private AppUser $first;

    private AppUser $second;

    private string $prefix = '/api/v1/apps/shiftcal/shiftcal/partner';

    protected function setUp(): void
    {
        parent::setUp();
        $app = App::factory()->create(['slug' => 'shiftcal']);
        $this->first = AppUser::factory()->create(['app_id' => $app->id]);
        $this->second = AppUser::factory()->create(['app_id' => $app->id]);
        $this->signIn($this->first);
    }

    private function signIn(AppUser $membership): void
    {
        $device = Device::factory()->create(['app_id' => $membership->app_id, 'user_id' => $membership->user_id]);
        $token = $device->user->createToken('phone', ['app:'.$membership->app_id]);
        $token->accessToken->forceFill(['device_id' => $device->id])->save();
        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken);
    }

    private function invite(): string
    {
        return $this->postJson($this->prefix.'/invitation')->assertCreated()->json('data.code');
    }

    public function test_preview_does_not_connect_and_acceptance_is_mutual_and_private(): void
    {
        $code = $this->invite();
        $this->assertSame(hash('sha256', $code), DB::table('shiftcal_partner_invitations')->value('code_hash'));
        $this->getJson($this->prefix)->assertOk()->assertJsonPath('data.partner', null)->assertJsonMissingPath('data.invitation.code');
        $this->signIn($this->second);
        $this->postJson($this->prefix.'/preview', ['code' => strtolower($code)])->assertOk()->assertExactJson(['data' => ['name' => $this->first->user->name]]);
        $this->assertDatabaseCount('shiftcal_partner_links', 0);
        $this->postJson($this->prefix.'/accept', ['code' => $code])->assertOk()->assertJsonPath('data.partner.name', $this->first->user->name);
        $this->assertDatabaseCount('shiftcal_partner_links', 2);
        $this->assertDatabaseCount('shiftcal_partner_invitations', 0);
        $this->signIn($this->first);
        $this->getJson($this->prefix)->assertOk()->assertExactJson(['data' => ['partner' => ['name' => $this->second->user->name], 'invitation' => null]]);
        $this->deleteJson($this->prefix)->assertNoContent();
        $this->assertDatabaseCount('shiftcal_partner_links', 0);
        $this->signIn($this->second);
        $this->getJson($this->prefix)->assertJsonPath('data.partner', null);
    }

    public function test_self_expired_invalid_and_cancelled_codes_are_rejected(): void
    {
        $code = $this->invite();
        $this->postJson($this->prefix.'/accept', ['code' => $code])->assertUnprocessable();
        $this->signIn($this->second);
        $this->postJson($this->prefix.'/accept', ['code' => 'invalid'])->assertUnprocessable();
        $this->postJson($this->prefix.'/accept', ['code' => '000000000000'])->assertNotFound();
        $this->travel(8)->days();
        $this->postJson($this->prefix.'/accept', ['code' => $code])->assertNotFound();
        $this->travelBack();
        $this->signIn($this->first);
        $this->deleteJson($this->prefix.'/invitation')->assertNoContent();
        $this->signIn($this->second);
        $this->postJson($this->prefix.'/accept', ['code' => $code])->assertNotFound();
        $this->assertDatabaseCount('shiftcal_partner_links', 0);
    }

    public function test_replacement_and_single_partner_limit(): void
    {
        $old = $this->invite();
        $new = $this->invite();
        $this->signIn($this->second);
        $this->postJson($this->prefix.'/accept', ['code' => $old])->assertNotFound();
        $this->postJson($this->prefix.'/accept', ['code' => $new])->assertOk();
        $this->postJson($this->prefix.'/invitation')->assertConflict();
        $third = AppUser::factory()->create(['app_id' => $this->first->app_id]);
        $this->signIn($third);
        $thirdCode = $this->invite();
        $this->signIn($this->first);
        $this->postJson($this->prefix.'/accept', ['code' => $thirdCode])->assertConflict();
        $this->assertDatabaseCount('shiftcal_partner_links', 2);
    }

    public function test_app_isolation_inactive_inviter_and_authentication(): void
    {
        $code = $this->invite();
        $this->signIn($this->second);
        DB::table('shiftcal_partner_invitations')->update(['app_id' => App::factory()->create()->id]);
        $this->postJson($this->prefix.'/accept', ['code' => $code])->assertNotFound();
        DB::table('shiftcal_partner_invitations')->update(['app_id' => $this->first->app_id]);
        $this->first->update(['is_active' => false]);
        $this->postJson($this->prefix.'/accept', ['code' => $code])->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', '')->getJson($this->prefix)->assertUnauthorized();
    }
}
