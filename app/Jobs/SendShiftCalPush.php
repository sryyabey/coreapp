<?php

namespace App\Jobs;

use App\Services\ShiftCal\FcmSender;
use App\Services\ShiftCal\PushOutbox;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendShiftCalPush implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $uniqueFor = 300;

    public function __construct(public string $outboxId) {}

    public function uniqueId(): string
    {
        return $this->outboxId;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [15, 60, 180, 300];
    }

    public function handle(FcmSender $sender, PushOutbox $outbox): void
    {
        if (! $sender->configured()) {
            return;
        }
        $lock = Cache::lock('shiftcal:push:'.$this->outboxId, 300);
        if (! $lock->get()) {
            return;
        }
        try {
            $event = DB::table('shiftcal_push_outbox')->where('id', $this->outboxId)->where('status', 'pending')->first();
            if (! $event) {
                return;
            }
            if (! $outbox->valid($event)) {
                DB::table('shiftcal_push_outbox')->where('id', $event->id)->update(['status' => 'skipped', 'updated_at' => now()]);

                return;
            }
            $devices = DB::table('shiftcal_push_devices as push')->join('devices', 'devices.id', '=', 'push.device_id')
                ->where('push.app_id', $event->app_id)->where('push.user_id', $event->user_id)->where('push.enabled', true)
                ->where('devices.app_id', $event->app_id)->where('devices.user_id', $event->user_id)->whereNull('devices.revoked_at')->select('push.*')->get();
            foreach ($devices as $device) {
                DB::table('shiftcal_push_deliveries')->insertOrIgnore(['outbox_id' => $event->id, 'push_device_id' => $device->id, 'created_at' => now(), 'updated_at' => now()]);
                $delivery = DB::table('shiftcal_push_deliveries')->where('outbox_id', $event->id)->where('push_device_id', $device->id)->first();
                if ($delivery->status !== 'pending') {
                    continue;
                }
                if ($delivery->attempts >= 5) {
                    DB::table('shiftcal_push_deliveries')->where('id', $delivery->id)->update(['status' => 'failed']);

                    continue;
                }
                if (! $outbox->valid($event)) {
                    DB::table('shiftcal_push_outbox')->where('id', $event->id)->update(['status' => 'skipped']);

                    return;
                }
                DB::table('shiftcal_push_deliveries')->where('id', $delivery->id)->increment('attempts');
                try {
                    $result = $sender->send($this->message($event, $device, $outbox));
                    DB::table('shiftcal_push_deliveries')->where('id', $delivery->id)->update(['status' => $result['status'], 'provider_id' => $result['id'] ?? null, 'updated_at' => now()]);
                    if ($result['status'] === 'invalid_token') {
                        DB::table('shiftcal_push_devices')->where('id', $device->id)->update(['enabled' => false]);
                    }
                } catch (Throwable $error) {
                    DB::table('shiftcal_push_deliveries')->where('id', $delivery->id)->update(['last_error' => 'provider_request_failed', 'updated_at' => now()]);
                    throw $error;
                }
            }
            DB::table('shiftcal_push_outbox')->where('id', $event->id)->update(['status' => 'completed', 'updated_at' => now()]);
        } finally {
            $lock->release();
        }
    }

    /** @return array<string, mixed> */
    private function message(object $event, object $device, PushOutbox $outbox): array
    {
        $tr = $device->locale === 'tr';
        $body = match ($event->type) {
            'offered' => $tr ? 'Yeni bir ortak plan önerin var.' : 'You have a new shared plan proposal.',
            'accepted' => $tr ? 'Ortak plan önerin kabul edildi.' : 'Your shared plan was accepted.',
            'declined' => $tr ? 'Ortak plan önerin reddedildi.' : 'Your shared plan was declined.',
            'withdrawn' => $tr ? 'Ortak plan önerisi geri çekildi.' : 'The shared proposal was withdrawn.',
            'cancelled' => $tr ? 'Ortak planınız iptal edildi.' : 'Your shared plan was cancelled.',
            'disconnected' => $tr ? 'Eş bağlantınız kaldırıldı.' : 'Your partner connection was removed.',
            default => $tr ? 'Ortak planınız yaklaşıyor.' : 'Your shared plan starts soon.',
        };
        $plan = $event->plan_id ? DB::table('shiftcal_shared_plans')->where('id', $event->plan_id)->first() : null;
        if ($plan && $outbox->preferences($event->app_id, $event->user_id)['show_details']) {
            $body .= ' '.$plan->title;
        }
        $ttl = max(1, min(3600, CarbonImmutable::parse($event->expires_at)->getTimestamp() - time()));

        return ['token' => Crypt::decryptString($device->token), 'notification' => ['title' => 'ShiftCal', 'body' => $body],
            'data' => ['event_id' => $event->id, 'type' => $event->type, 'plan_id' => $event->plan_id ?? '', 'account_id' => (string) $event->user_id, 'starts_at' => $plan ? CarbonImmutable::parse($plan->starts_at)->utc()->toIso8601String() : ''],
            'android' => ['priority' => 'high', 'ttl' => $ttl.'s', 'notification' => ['channel_id' => 'shiftcal_shared', 'tag' => $event->id, 'icon' => 'ic_notification']],
            'apns' => ['headers' => ['apns-expiration' => (string) (time() + $ttl), 'apns-collapse-id' => $event->id], 'payload' => ['aps' => ['sound' => 'default']]],
        ];
    }
}
