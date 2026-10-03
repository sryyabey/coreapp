<?php

namespace Tests\Feature;

use App\Models\App;
use App\Models\AppUser;
use App\Models\Device;
use App\Models\ShiftCal\Event;
use App\Models\ShiftCal\ShiftTemplate;
use App\Models\ShiftCal\WageSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftCalApiTest extends TestCase
{
    use RefreshDatabase;

    private AppUser $membership;

    private string $prefix = '/api/v1/apps/shiftcal/shiftcal';

    protected function setUp(): void
    {
        parent::setUp();
        $app = App::factory()->create(['slug' => 'shiftcal']);
        $this->membership = AppUser::factory()->create(['app_id' => $app->id]);
        $device = Device::factory()->create(['app_id' => $app->id, 'user_id' => $this->membership->user_id]);
        $token = $device->user->createToken('phone', ['app:'.$app->id]);
        $token->accessToken->forceFill(['device_id' => $device->id])->save();
        $this->withToken($token->plainTextToken);
    }

    private function eventData(): array
    {
        return ['type' => 'duty', 'starts_at' => '2026-10-03T22:00:00+03:00', 'ends_at' => '2026-10-04T08:00:00+03:00', 'timezone' => 'Europe/Istanbul', 'color' => '#FF6B16', 'note' => 'Gece nöbeti'];
    }

    public function test_event_crud_and_overnight_duration(): void
    {
        $response = $this->postJson($this->prefix.'/events', $this->eventData())->assertCreated()->assertJsonPath('data.duration_minutes', 600);
        $id = $response->json('data.id');
        $this->assertSame('2026-10-03 19:00:00', Event::sole()->starts_at->format('Y-m-d H:i:s'));
        $this->getJson($this->prefix.'/events/'.$id)->assertOk();
        $this->patchJson($this->prefix.'/events/'.$id, ['note' => 'Güncellendi'])->assertOk()->assertJsonPath('data.note', 'Güncellendi');
        $this->getJson($this->prefix.'/events?from=2026-10-04T00:00:00%2B03:00&to=2026-10-05T00:00:00%2B03:00')->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson($this->prefix.'/events/'.$id)->assertNoContent();
        $this->getJson($this->prefix.'/events/'.$id)->assertNotFound();
    }

    public function test_retried_create_is_idempotent_and_key_cannot_be_reused_for_different_data(): void
    {
        $data = array_replace($this->eventData(), ['client_request_id' => '22222222-2222-4222-8222-222222222222']);
        $first = $this->postJson($this->prefix.'/events', $data)->assertCreated();
        $this->postJson($this->prefix.'/events', $data)->assertOk()->assertJsonPath('data.id', $first->json('data.id'));
        $this->postJson($this->prefix.'/events', array_replace($data, ['note' => 'Different']))->assertConflict();
        $this->patchJson($this->prefix.'/events/'.$first->json('data.id'), ['client_request_id' => '33333333-3333-4333-8333-333333333333'])
            ->assertUnprocessable()->assertJsonValidationErrors('client_request_id');
        $this->assertDatabaseCount('shiftcal_events', 1);
    }

    public function test_invalid_event_dates_timezone_and_owner_are_rejected(): void
    {
        $this->postJson($this->prefix.'/events', array_replace($this->eventData(), ['ends_at' => '2026-10-03T21:00:00+03:00', 'timezone' => 'invalid', 'user_id' => 1]))
            ->assertUnprocessable()->assertJsonValidationErrors(['ends_at', 'timezone', 'user_id']);
        $this->postJson($this->prefix.'/events', array_replace($this->eventData(), ['starts_at' => '2026-10-03 22:00:00']))->assertUnprocessable()->assertJsonValidationErrors('starts_at');
    }

    public function test_foreign_records_are_not_listed_or_editable(): void
    {
        $foreign = Event::factory()->create(['app_id' => $this->membership->app_id]);
        $otherApp = Event::factory()->create(['user_id' => $this->membership->user_id]);
        $this->getJson($this->prefix.'/events')->assertOk()->assertJsonCount(0, 'data');
        foreach ([$foreign, $otherApp] as $event) {
            $this->getJson($this->prefix.'/events/'.$event->id)->assertNotFound();
            $this->patchJson($this->prefix.'/events/'.$event->id, ['note' => 'foreign'])->assertNotFound();
            $this->deleteJson($this->prefix.'/events/'.$event->id)->assertNotFound();
        }
    }

    public function test_template_crud_and_next_day_validation(): void
    {
        $data = ['name' => 'Gece', 'type' => 'duty', 'start_time' => '22:00', 'end_time' => '08:00', 'end_day_offset' => 1, 'color' => '#FF6B16'];
        $id = $this->postJson($this->prefix.'/shift-templates', $data)->assertCreated()->json('data.id');
        $this->patchJson($this->prefix.'/shift-templates/'.$id, ['name' => 'Gece vardiyası'])->assertOk()->assertJsonPath('data.name', 'Gece vardiyası');
        $this->postJson($this->prefix.'/shift-templates', array_replace($data, ['end_day_offset' => 0]))->assertUnprocessable()->assertJsonValidationErrors('end_time');
        $foreign = ShiftTemplate::factory()->create(['app_id' => $this->membership->app_id]);
        $this->getJson($this->prefix.'/shift-templates/'.$foreign->id)->assertNotFound();
        $this->deleteJson($this->prefix.'/shift-templates/'.$id)->assertNoContent();
    }

    public function test_wage_settings_are_unique_and_user_scoped(): void
    {
        WageSetting::factory()->create(['app_id' => $this->membership->app_id]);
        $this->getJson($this->prefix.'/wage-settings')->assertOk()->assertJsonPath('data.hourly_rate', '0.00');
        $data = ['hourly_rate' => '120.50', 'overtime_multiplier' => '1.50', 'currency' => 'TRY', 'weekly_target_minutes' => 2400];
        $this->putJson($this->prefix.'/wage-settings', $data)->assertOk()->assertJsonPath('data.hourly_rate', '120.50');
        $this->putJson($this->prefix.'/wage-settings', array_replace($data, ['hourly_rate' => '150.00']))->assertOk();
        $this->assertDatabaseCount('shiftcal_wage_settings', 2);
        $this->getJson($this->prefix.'/wage-settings')->assertOk()->assertJsonPath('data.hourly_rate', '150.00');
        $this->putJson($this->prefix.'/wage-settings', array_replace($data, ['hourly_rate' => '-1']))->assertUnprocessable();
    }

    public function test_other_apps_cannot_access_shiftcal_module(): void
    {
        $other = AppUser::factory()->create();
        $device = Device::factory()->create(['app_id' => $other->app_id, 'user_id' => $other->user_id]);
        $token = $device->user->createToken('phone', ['app:'.$other->app_id]);
        $token->accessToken->forceFill(['device_id' => $device->id])->save();
        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)->getJson('/api/v1/apps/'.$other->app->slug.'/shiftcal/events')->assertNotFound();
    }

    public function test_duration_uses_real_elapsed_time_across_daylight_saving_change(): void
    {
        $this->postJson($this->prefix.'/events', array_replace($this->eventData(), [
            'starts_at' => '2026-03-29T01:30:00+01:00', 'ends_at' => '2026-03-29T03:30:00+02:00', 'timezone' => 'Europe/Berlin',
        ]))->assertCreated()->assertJsonPath('data.duration_minutes', 60);
    }

    public function test_invalid_uuid_and_client_supplied_identity_are_rejected(): void
    {
        $this->getJson($this->prefix.'/events/not-a-uuid')->assertNotFound();
        $this->getJson($this->prefix.'/shift-templates/123')->assertNotFound();
        $this->postJson($this->prefix.'/events', array_replace($this->eventData(), ['id' => '11111111-1111-4111-8111-111111111111']))
            ->assertUnprocessable()->assertJsonValidationErrors('id');
        $this->assertDatabaseCount('shiftcal_events', 0);
    }

    public function test_invalid_calendar_dates_and_offsetless_filters_are_rejected(): void
    {
        foreach (['2026-02-30T22:00:00+03:00', '2026-10-03T24:00:00+03:00', '2026-10-03T22:00:00+15:00'] as $date) {
            $this->postJson($this->prefix.'/events', array_replace($this->eventData(), ['starts_at' => $date]))
                ->assertUnprocessable()->assertJsonValidationErrors('starts_at');
        }
        $this->getJson($this->prefix.'/events?from=2026-10-03&to=2026-10-04')->assertUnprocessable()->assertJsonValidationErrors(['from', 'to']);
    }

    public function test_midnight_boundaries_exclude_events_touching_the_day_without_overlap(): void
    {
        foreach ([['2026-10-03T22:00:00+03:00', '2026-10-04T00:00:00+03:00'],
            ['2026-10-04T00:00:00+03:00', '2026-10-04T01:00:00+03:00'],
            ['2026-10-05T00:00:00+03:00', '2026-10-05T01:00:00+03:00']] as [$start, $end]) {
            $this->postJson($this->prefix.'/events', array_replace($this->eventData(), ['starts_at' => $start, 'ends_at' => $end]))->assertCreated();
        }
        $this->getJson($this->prefix.'/events?from=2026-10-04T00:00:00%2B03:00&to=2026-10-05T00:00:00%2B03:00')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.duration_minutes', 60);
        $this->postJson($this->prefix.'/events', array_replace($this->eventData(), ['ends_at' => $this->eventData()['starts_at']]))
            ->assertUnprocessable()->assertJsonValidationErrors('ends_at');
    }
}
