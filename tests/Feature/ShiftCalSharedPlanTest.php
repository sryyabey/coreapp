<?php

namespace Tests\Feature;

use App\Models\App;
use App\Models\AppUser;
use App\Models\Device;
use App\Models\ShiftCal\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\GrantsShiftCalDuo;
use Tests\TestCase;

class ShiftCalSharedPlanTest extends TestCase
{
    use GrantsShiftCalDuo;
    use RefreshDatabase;

    private AppUser $first;

    private AppUser $second;

    private string $prefix = '/api/v1/apps/shiftcal/shiftcal/partner';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 6)->startOfDay());
        $app = App::factory()->create(['slug' => 'shiftcal']);
        $this->first = AppUser::factory()->create(['app_id' => $app->id]);
        $this->second = AppUser::factory()->create(['app_id' => $app->id]);
        $this->signIn($this->first);
        $code = $this->postJson($this->prefix.'/invitation')->assertCreated()->json('data.code');
        $this->signIn($this->second);
        $this->postJson($this->prefix.'/accept', ['code' => $code])->assertOk();
        $this->signIn($this->first);
    }

    private function signIn(AppUser $membership): void
    {
        $this->grantDuo($membership);
        $device = Device::factory()->create(['app_id' => $membership->app_id, 'user_id' => $membership->user_id]);
        $token = $device->user->createToken('phone', ['app:'.$membership->app_id]);
        $token->accessToken->forceFill(['device_id' => $device->id])->save();
        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken);
    }

    private function data(): array
    {
        return ['title' => 'Akşam yemeği', 'starts_at' => '2026-10-07T19:00:00+03:00', 'ends_at' => '2026-10-07T20:30:00+03:00', 'timezone' => 'Europe/Istanbul', 'client_request_id' => (string) Str::uuid()];
    }

    private function proposal(): string
    {
        return $this->postJson($this->prefix.'/plans', $this->data())->assertCreated()->assertJsonPath('data.status', 'pending')->json('data.id');
    }

    private function listing(): string
    {
        return $this->prefix.'/plans?from=2026-10-07T00:00:00Z&to=2026-10-08T00:00:00Z';
    }

    private function busy(AppUser $owner): Event
    {
        return Event::factory()->create(['app_id' => $owner->app_id, 'user_id' => $owner->user_id, 'type' => 'other',
            'starts_at' => '2026-10-07 16:30:00', 'ends_at' => '2026-10-07 17:30:00', 'note' => 'Gizli not']);
    }

    public function test_proposal_does_not_reserve_time_until_recipient_accepts_and_cancellation_is_mutual(): void
    {
        $id = $this->proposal();
        $this->patchJson($this->prefix.'/plans/'.$id, ['action' => 'accept'])->assertForbidden();
        $this->signIn($this->second);
        $this->getJson($this->listing())->assertOk()->assertJsonPath('data.0.direction', 'incoming')->assertJsonPath('meta.incoming_count', 1);
        $this->patchJson($this->prefix.'/plans/'.$id, ['action' => 'accept'])->assertOk()->assertJsonPath('data.status', 'accepted');
        $this->patchJson($this->prefix.'/plans/'.$id, ['action' => 'accept'])->assertOk();
        $this->assertDatabaseCount('shiftcal_shared_plans', 1);
        $this->assertDatabaseCount('shiftcal_events', 0);
        $this->signIn($this->first);
        $this->getJson($this->listing())->assertJsonPath('data.0.status', 'accepted')->assertJsonPath('data.0.conflicts_with_schedule', false);
        $this->postJson($this->prefix.'/plans', $this->data())->assertConflict();
        $this->signIn($this->second);
        $this->patchJson($this->prefix.'/plans/'.$id, ['action' => 'cancel'])->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->patchJson($this->prefix.'/plans/'.$id, ['action' => 'accept'])->assertConflict();
        $this->signIn($this->first);
        $this->postJson($this->prefix.'/plans', $this->data())->assertCreated();
    }

    public function test_schedule_is_checked_when_sending_and_again_when_accepting(): void
    {
        $busy = $this->busy($this->second);
        $this->postJson($this->prefix.'/plans', $this->data())->assertConflict();
        $busy->delete();
        $id = $this->proposal();
        $this->busy($this->first);
        $this->signIn($this->second);
        $this->patchJson($this->prefix.'/plans/'.$id, ['action' => 'accept'])->assertConflict();
        $this->assertDatabaseHas('shiftcal_shared_plans', ['id' => $id, 'status' => 'pending']);
    }

    public function test_later_event_marks_accepted_plan_as_conflicting_without_disclosing_details(): void
    {
        $id = $this->proposal();
        $this->signIn($this->second);
        $this->patchJson($this->prefix.'/plans/'.$id, ['action' => 'accept'])->assertOk();
        $busy = $this->busy($this->first);
        $response = $this->getJson($this->listing())->assertJsonPath('data.0.conflicts_with_schedule', true)->assertJsonPath('data.0.status', 'accepted');
        $this->assertStringNotContainsString('Gizli not', $response->getContent());
        $this->assertSame(['id', 'title', 'starts_at', 'ends_at', 'timezone', 'status', 'direction', 'conflicts_with_schedule'], array_keys($response->json('data.0')));
        $busy->delete();
        $this->getJson($this->listing())->assertJsonPath('data.0.conflicts_with_schedule', false);
    }

    public function test_decline_withdrawal_duplicate_requests_and_competing_proposals(): void
    {
        $data = $this->data();
        $id = $this->postJson($this->prefix.'/plans', $data)->assertCreated()->json('data.id');
        $this->postJson($this->prefix.'/plans', $data)->assertOk()->assertJsonPath('data.id', $id);
        $this->postJson($this->prefix.'/plans', array_replace($data, ['title' => 'Farklı plan']))->assertConflict();
        $second = $this->proposal();
        $this->signIn($this->second);
        $this->patchJson($this->prefix.'/plans/'.$id, ['action' => 'cancel'])->assertForbidden();
        $this->patchJson($this->prefix.'/plans/'.$id, ['action' => 'decline'])->assertOk();
        $this->patchJson($this->prefix.'/plans/'.$id, ['action' => 'accept'])->assertConflict();
        $this->patchJson($this->prefix.'/plans/'.$second, ['action' => 'accept'])->assertOk();
        $this->signIn($this->first);
        $later = array_replace($this->data(), ['starts_at' => '2026-10-07T21:00:00+03:00', 'ends_at' => '2026-10-07T22:00:00+03:00']);
        $third = $this->postJson($this->prefix.'/plans', $later)->assertCreated()->json('data.id');
        $this->patchJson($this->prefix.'/plans/'.$third, ['action' => 'cancel'])->assertOk();
    }

    public function test_foreign_users_and_new_connections_cannot_read_or_act_on_old_plans(): void
    {
        $id = $this->proposal();
        $third = AppUser::factory()->create(['app_id' => $this->first->app_id]);
        $this->signIn($third);
        $this->getJson($this->listing())->assertNotFound();
        $this->patchJson($this->prefix.'/plans/'.$id, ['action' => 'accept'])->assertNotFound();
        $this->signIn($this->first);
        $this->deleteJson($this->prefix)->assertNoContent();
        $this->assertDatabaseHas('shiftcal_shared_plans', ['id' => $id, 'status' => 'cancelled']);
        $code = $this->postJson($this->prefix.'/invitation')->assertCreated()->json('data.code');
        $this->signIn($third);
        $this->postJson($this->prefix.'/accept', ['code' => $code])->assertOk();
        $this->getJson($this->listing())->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson($this->prefix.'/plans/'.$id, ['action' => 'accept'])->assertNotFound();
    }

    public function test_dates_owner_injection_and_expired_proposals_are_rejected(): void
    {
        $this->postJson($this->prefix.'/plans', array_replace($this->data(), ['recipient_id' => $this->second->user_id]))->assertUnprocessable();
        $this->postJson($this->prefix.'/plans', array_replace($this->data(), ['starts_at' => '2026-10-05T19:00:00+03:00']))->assertUnprocessable();
        $this->postJson($this->prefix.'/plans', array_replace($this->data(), ['title' => '   ']))->assertUnprocessable();
        $this->getJson($this->prefix.'/plans?from=2026-10-07&to=2026-10-08')->assertUnprocessable();
        $this->getJson($this->prefix.'/plans?from=2026-10-07T00:00:00Z&to=2026-10-09T00:00:00Z')->assertUnprocessable();
        $id = $this->proposal();
        $this->travel(3)->days();
        $this->signIn($this->second);
        $this->getJson($this->listing())->assertJsonPath('data.0.status', 'expired')->assertJsonPath('meta.incoming_count', 0);
        $this->patchJson($this->prefix.'/plans/'.$id, ['action' => 'accept'])->assertConflict();
    }

    public function test_competing_proposals_cannot_both_be_accepted_and_touching_boundaries_are_allowed(): void
    {
        $first = $this->proposal();
        $second = $this->proposal();
        $this->signIn($this->second);
        $this->patchJson($this->prefix.'/plans/'.$first, ['action' => 'accept'])->assertOk();
        $this->patchJson($this->prefix.'/plans/'.$second, ['action' => 'accept'])->assertConflict();
        $this->signIn($this->first);
        $touching = array_replace($this->data(), ['starts_at' => '2026-10-07T20:30:00+03:00', 'ends_at' => '2026-10-07T21:30:00+03:00']);
        $third = $this->postJson($this->prefix.'/plans', $touching)->assertCreated()->json('data.id');
        $this->signIn($this->second);
        $this->patchJson($this->prefix.'/plans/'.$third, ['action' => 'accept'])->assertOk();
        $this->assertDatabaseHas('shiftcal_shared_plans', ['id' => $second, 'status' => 'pending']);
    }
}
