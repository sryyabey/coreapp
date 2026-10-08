<?php

namespace App\Jobs;

use App\Models\SupportMessage;
use App\Services\ShiftCal\FcmSender;
use App\Services\SupportPush;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendSupportReplyPush implements ShouldBeUnique, ShouldQueue
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

    public function handle(FcmSender $sender, SupportPush $outbox): void
    {
        $lock = Cache::lock('support:push:'.$this->outboxId, 300);
        if (! $lock->get()) {
            return;
        }
        try {
            $event = DB::table('support_push_outbox')->where('id', $this->outboxId)->where('status', 'pending')->first();
            if (! $event) {
                return;
            }
            $appSlug = DB::table('apps')->where('id', $event->app_id)->value('slug');
            if (! $outbox->valid($event)) {
                DB::table('support_push_outbox')->where('id', $event->id)->update(['status' => 'skipped', 'updated_at' => now()]);

                return;
            }
            if (! $sender->configured($appSlug)) {
                return;
            }
            $devices = DB::table('support_push_devices as push')->join('devices', 'devices.id', '=', 'push.device_id')
                ->where('push.app_id', $event->app_id)->where('push.user_id', $event->user_id)->where('push.enabled', true)
                ->where('devices.app_id', $event->app_id)->where('devices.user_id', $event->user_id)->whereNull('devices.revoked_at')->select('push.*')->get();
            foreach ($devices as $device) {
                DB::table('support_push_deliveries')->insertOrIgnore(['outbox_id' => $event->id, 'push_device_id' => $device->id, 'created_at' => now(), 'updated_at' => now()]);
                $delivery = DB::table('support_push_deliveries')->where('outbox_id', $event->id)->where('push_device_id', $device->id)->first();
                if ($delivery->status !== 'pending') {
                    continue;
                }
                if ($delivery->attempts >= 5) {
                    DB::table('support_push_deliveries')->where('id', $delivery->id)->update(['status' => 'failed']);

                    continue;
                }
                if (! $outbox->valid($event)) {
                    DB::table('support_push_outbox')->where('id', $event->id)->update(['status' => 'skipped']);

                    return;
                }
                DB::table('support_push_deliveries')->where('id', $delivery->id)->increment('attempts');
                try {
                    $result = $sender->send($this->message($event, $device), $appSlug);
                    DB::table('support_push_deliveries')->where('id', $delivery->id)->update(['status' => $result['status'], 'provider_id' => $result['id'] ?? null, 'updated_at' => now()]);
                    if ($result['status'] === 'invalid_token') {
                        DB::table('support_push_devices')->where('id', $device->id)->update(['enabled' => false]);
                    }
                } catch (Throwable $error) {
                    DB::table('support_push_deliveries')->where('id', $delivery->id)->update(['last_error' => 'provider_request_failed', 'updated_at' => now()]);
                    throw $error;
                }
            }
            DB::table('support_push_outbox')->where('id', $event->id)->update(['status' => 'completed', 'updated_at' => now()]);
        } finally {
            $lock->release();
        }
    }

    /** @return array<string, mixed> */
    private function message(object $event, object $device): array
    {
        $ticket = SupportMessage::findOrFail($event->message_id)->ticket;
        $ttl = max(1, min(3600, CarbonImmutable::parse($event->expires_at)->getTimestamp() - time()));

        return ['token' => Crypt::decryptString($device->token),
            'notification' => ['title' => $ticket->app->name, 'body' => $device->locale === 'tr' ? 'Destek talebiniz yanıtlandı.' : 'Your support request has been answered.'],
            'data' => ['event_id' => $event->id, 'type' => 'support_replied', 'ticket_id' => $ticket->id, 'account_id' => (string) $event->user_id, 'app_slug' => $ticket->app->slug],
            'android' => ['priority' => 'high', 'ttl' => $ttl.'s', 'notification' => ['channel_id' => $ticket->app->slug.'_support', 'tag' => $event->id, 'icon' => 'ic_notification']],
            'apns' => ['headers' => ['apns-expiration' => (string) (time() + $ttl), 'apns-collapse-id' => $event->id], 'payload' => ['aps' => ['sound' => 'default']]],
        ];
    }
}
