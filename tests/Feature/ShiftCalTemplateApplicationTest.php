<?php

namespace Tests\Feature;

use App\Models\App;
use App\Models\AppUser;
use App\Models\Device;
use App\Models\ShiftCal\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShiftCalTemplateApplicationTest extends TestCase
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

    private function record(int $day, string $type = 'work'): array
    {
        return ['day_key' => "2026-10-$day", 'date_key' => "2026-10-$day", 'client_request_id' => (string) Str::uuid(), 'type' => $type, 'starts_at' => sprintf('2026-10-%02dT08:00:00+03:00', $day), 'ends_at' => sprintf('2026-10-%02dT16:00:00+03:00', $day), 'timezone' => 'Europe/Istanbul', 'color' => '#226644', 'note' => ''];
    }

    public function test_batch_is_idempotent_and_undo_removes_only_its_events(): void
    {
        $unrelated = Event::factory()->create(['app_id' => $this->membership->app_id, 'user_id' => $this->membership->user_id, 'starts_at' => '2026-10-03 05:00:00', 'ends_at' => '2026-10-03 13:00:00']);
        $payload = ['operation_id' => (string) Str::uuid(), 'only_safe_days' => false, 'records' => [$this->record(5), $this->record(6)]];
        $first = $this->postJson($this->prefix.'/template-applications', $payload)->assertCreated()->assertJsonCount(2, 'data.records');
        $this->postJson($this->prefix.'/template-applications', $payload)->assertOk()->assertJsonPath('data.records.0.entry.id', $first->json('data.records.0.entry.id'));
        $this->assertDatabaseCount('shiftcal_events', 3);
        $changed = $payload;
        $changed['records'][0]['note'] = 'Changed';
        $this->postJson($this->prefix.'/template-applications', $changed)->assertConflict();
        $this->deleteJson($this->prefix.'/template-applications/'.$payload['operation_id'])->assertNoContent();
        $this->deleteJson($this->prefix.'/template-applications/'.$payload['operation_id'])->assertNoContent();
        $this->assertDatabaseCount('shiftcal_events', 1);
        $this->assertDatabaseHas('shiftcal_events', ['id' => $unrelated->id]);
        $this->postJson($this->prefix.'/template-applications', $payload)->assertConflict();
    }

    public function test_conflicts_prevent_partial_writes_and_safe_mode_skips_whole_days(): void
    {
        Event::factory()->create(['app_id' => $this->membership->app_id, 'user_id' => $this->membership->user_id, 'type' => 'duty', 'starts_at' => '2026-10-05 06:00:00', 'ends_at' => '2026-10-05 09:00:00']);
        $payload = ['operation_id' => (string) Str::uuid(), 'only_safe_days' => false, 'records' => [$this->record(5), $this->record(6)]];
        $this->postJson($this->prefix.'/template-applications', $payload)->assertConflict();
        $this->assertDatabaseCount('shiftcal_events', 1);
        $this->assertDatabaseCount('shiftcal_template_applications', 0);
        $payload['only_safe_days'] = true;
        $this->postJson($this->prefix.'/template-applications', $payload)->assertCreated()->assertJsonPath('data.days', ['2026-10-6']);
        $this->assertDatabaseCount('shiftcal_events', 2);
    }

    public function test_server_rechecks_cross_midnight_and_batch_internal_overlaps(): void
    {
        $first = $this->record(5);
        $first['starts_at'] = '2026-10-05T22:00:00+03:00';
        $first['ends_at'] = '2026-10-06T09:00:00+03:00';
        $payload = ['operation_id' => (string) Str::uuid(), 'only_safe_days' => false, 'records' => [$first, $this->record(6)]];
        $this->postJson($this->prefix.'/template-applications', $payload)->assertConflict();
        $this->assertDatabaseCount('shiftcal_events', 0);
    }

    public function test_failed_insert_rolls_back_entire_batch(): void
    {
        $count = 0;
        Event::creating(function () use (&$count): void {
            if (++$count === 2) {
                throw new \RuntimeException('Injected insert failure');
            }
        });
        try {
            $this->postJson($this->prefix.'/template-applications', ['operation_id' => (string) Str::uuid(), 'only_safe_days' => false, 'records' => [$this->record(5), $this->record(6)]])->assertServerError();
            $this->assertDatabaseCount('shiftcal_events', 0);
            $this->assertDatabaseCount('shiftcal_template_applications', 0);
        } finally {
            Event::flushEventListeners();
        }
    }

    public function test_foreign_application_cannot_be_read_or_undone(): void
    {
        $payload = ['operation_id' => (string) Str::uuid(), 'only_safe_days' => false, 'records' => [$this->record(5)]];
        $this->postJson($this->prefix.'/template-applications', $payload)->assertCreated();
        $other = AppUser::factory()->create(['app_id' => $this->membership->app_id]);
        $device = Device::factory()->create(['app_id' => $other->app_id, 'user_id' => $other->user_id]);
        $token = $device->user->createToken('other', ['app:'.$other->app_id]);
        $token->accessToken->forceFill(['device_id' => $device->id])->save();
        $this->withToken($token->plainTextToken);
        $this->app['auth']->forgetGuards();
        $this->postJson($this->prefix.'/template-applications', $payload)->assertNotFound();
        $this->deleteJson($this->prefix.'/template-applications/'.$payload['operation_id'])->assertNotFound();
        $this->assertDatabaseCount('shiftcal_events', 1);
    }

    public function test_invalid_record_rejects_everything_and_identical_events_are_not_duplicated(): void
    {
        $payload = ['operation_id' => (string) Str::uuid(), 'only_safe_days' => false, 'records' => [$this->record(5), $this->record(6)]];
        $payload['records'][1]['ends_at'] = '2026-10-06T07:00:00+03:00';
        $this->postJson($this->prefix.'/template-applications', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('shiftcal_events', 0);
        $payload['records'][1] = $this->record(6);
        $this->postJson($this->prefix.'/template-applications', $payload)->assertCreated();
        $payload['operation_id'] = (string) Str::uuid();
        $this->postJson($this->prefix.'/template-applications', $payload)->assertConflict();
        $this->assertDatabaseCount('shiftcal_events', 2);
    }
}
