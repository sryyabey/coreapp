<?php

namespace Tests\Feature;

use App\Models\App;
use App\Models\AppUser;
use App\Models\Device;
use App\Models\ShiftCal\CloudBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\GrantsShiftCalDuo;
use Tests\TestCase;

class ShiftCalCloudBackupTest extends TestCase
{
    use GrantsShiftCalDuo;
    use RefreshDatabase;

    private string $url = '/api/v1/apps/shiftcal/shiftcal/cloud-backup';

    private function signIn(AppUser $membership): void
    {
        $this->grantDuo($membership);
        $this->app['auth']->forgetGuards();
        $device = Device::factory()->create(['app_id' => $membership->app_id, 'user_id' => $membership->user_id]);
        $token = $membership->user->createToken('phone', ['app:'.$membership->app_id]);
        $token->accessToken->forceFill(['device_id' => $device->id])->save();
        $this->withToken($token->plainTextToken);
    }

    private function payload(): array
    {
        return ['schema_version' => 1, 'revision' => 0, 'entries' => [[
            'id' => '11111111-1111-4111-8111-111111111111', 'date_key' => '2026-10-03', 'type' => 'duty',
            'start' => 1320, 'end' => 480, 'color' => 4280916822, 'note' => 'Gece nöbeti',
            'timezone' => 'Europe/Istanbul', 'starts_at' => '2026-10-03T19:00:00Z', 'ends_at' => '2026-10-04T05:00:00Z',
        ]]];
    }

    public function test_backup_restore_revision_and_encrypted_storage(): void
    {
        $app = App::factory()->create(['slug' => 'shiftcal']);
        $membership = AppUser::factory()->create(['app_id' => $app->id]);
        $this->signIn($membership);
        $this->getJson($this->url)->assertOk()->assertJsonPath('data.exists', false)->assertJsonPath('data.revision', 0);
        $this->postJson($this->url, $this->payload())->assertOk()->assertJsonPath('data.revision', 1);
        $this->getJson($this->url)->assertOk()->assertJsonPath('data.entries.0.timezone', 'Europe/Istanbul');
        $this->assertStringNotContainsString('Gece nöbeti', DB::table('shiftcal_cloud_backups')->value('payload'));
        $this->postJson($this->url, $this->payload())->assertConflict();
        $this->postJson($this->url, array_replace($this->payload(), ['revision' => 1]))->assertOk()->assertJsonPath('data.revision', 2);
        $this->assertDatabaseCount('shiftcal_cloud_backups', 1);
    }

    public function test_backups_are_account_scoped_and_invalid_data_does_not_replace_them(): void
    {
        $app = App::factory()->create(['slug' => 'shiftcal']);
        $first = AppUser::factory()->create(['app_id' => $app->id]);
        $this->signIn($first);
        $this->postJson($this->url, $this->payload())->assertOk();
        $bad = $this->payload();
        $bad['revision'] = 1;
        $bad['entries'][0]['timezone'] = 'invalid';
        $this->postJson($this->url, $bad)->assertUnprocessable()->assertJsonValidationErrors('entries.0.timezone');
        $this->assertSame(1, CloudBackup::sole()->revision);
        $second = AppUser::factory()->create(['app_id' => $app->id]);
        $this->signIn($second);
        $this->getJson($this->url)->assertOk()->assertJsonPath('data.exists', false)->assertJsonCount(0, 'data.entries');
        $this->postJson($this->url, $this->payload())->assertOk();
        $this->assertDatabaseCount('shiftcal_cloud_backups', 2);
    }

    public function test_requires_authentication_and_rejects_owner_override_duplicate_ids(): void
    {
        $app = App::factory()->create(['slug' => 'shiftcal']);
        $this->getJson($this->url)->assertUnauthorized();
        $this->signIn(AppUser::factory()->create(['app_id' => $app->id]));
        $payload = $this->payload();
        $payload['user_id'] = 1;
        $payload['entries'][] = $payload['entries'][0];
        $this->postJson($this->url, $payload)->assertUnprocessable()->assertJsonValidationErrors(['user_id', 'entries.0.id']);
        $this->assertDatabaseCount('shiftcal_cloud_backups', 0);
    }
}
