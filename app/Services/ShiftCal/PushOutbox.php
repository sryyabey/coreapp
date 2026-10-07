<?php

namespace App\Services\ShiftCal;

use App\Http\Controllers\Api\V1\ShiftCal\NotificationController;
use App\Jobs\SendShiftCalPush;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PushOutbox
{
    public function enqueue(int $appId, int $userId, string $type, string $key, ?string $planId, ?string $connectionId, ?CarbonImmutable $expires = null): void
    {
        $id = (string) Str::uuid();
        $inserted = DB::table('shiftcal_push_outbox')->insertOrIgnore(['id' => $id, 'app_id' => $appId, 'user_id' => $userId,
            'type' => $type, 'event_key' => $key, 'plan_id' => $planId, 'connection_id' => $connectionId,
            'due_at' => now(), 'expires_at' => $expires ?? now()->addDay(), 'created_at' => now(), 'updated_at' => now()]);
        if ($inserted && app(FcmSender::class)->configured()) {
            SendShiftCalPush::dispatch($id)->afterCommit();
        }
    }

    /** @return array<string, mixed> */
    public function preferences(int $appId, int $userId): array
    {
        $row = DB::table('shiftcal_notification_preferences')->where('app_id', $appId)->where('user_id', $userId)->first();

        return array_replace(NotificationController::DEFAULTS, $row ? (array) $row : []);
    }

    public function valid(object $event): bool
    {
        if (CarbonImmutable::parse($event->expires_at)->lte(now()) || ! DB::table('app_users')->where('app_id', $event->app_id)->where('user_id', $event->user_id)->where('is_active', true)->exists()) {
            return false;
        }
        $prefs = $this->preferences($event->app_id, $event->user_id);
        $key = match ($event->type) {
            'reminder' => 'plan_enabled', 'cancelled', 'withdrawn', 'disconnected' => 'shared_cancellations', default => 'shared_updates'
        };
        if (! $prefs[$key]) {
            return false;
        }
        $link = DB::table('shiftcal_partner_links')->where('app_id', $event->app_id)->where('user_id', $event->user_id)->first();
        if ($event->type === 'disconnected') {
            return $link === null;
        }
        if (! $link || $link->connection_id !== $event->connection_id) {
            return false;
        }
        $plan = DB::table('shiftcal_shared_plans')->where('id', $event->plan_id)->where('app_id', $event->app_id)->where('connection_id', $event->connection_id)->first();
        if (! $plan) {
            return false;
        }
        $status = match ($event->type) {
            'offered' => 'pending', 'accepted', 'reminder' => 'accepted', 'declined' => 'declined', default => 'cancelled'
        };
        if ($plan->status !== $status) {
            return false;
        }
        if ($event->type === 'reminder') {
            $start = CarbonImmutable::parse($plan->starts_at);
            $due = $start->subMinutes((int) $prefs['plan_minutes']);

            return $due->lte(now()) && $start->gt(now()) && $event->event_key === 'reminder:'.$plan->id.':'.$prefs['plan_minutes'];
        }

        return true;
    }

    public function reminders(): void
    {
        $plans = DB::table('shiftcal_shared_plans')->where('status', 'accepted')->where('starts_at', '>', now())->where('starts_at', '<=', now()->addMinutes(30))->get();
        foreach ($plans as $plan) {
            foreach ([$plan->proposer_id, $plan->recipient_id] as $userId) {
                $prefs = $this->preferences($plan->app_id, $userId);
                if ($prefs['plan_enabled'] && CarbonImmutable::parse($plan->starts_at)->subMinutes((int) $prefs['plan_minutes'])->lte(now())) {
                    $this->enqueue($plan->app_id, $userId, 'reminder', 'reminder:'.$plan->id.':'.$prefs['plan_minutes'], $plan->id, $plan->connection_id, CarbonImmutable::parse($plan->starts_at));
                }
            }
        }
    }
}
