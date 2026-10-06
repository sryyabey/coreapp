<?php

namespace App\Services\ShiftCal;

use App\Models\AppUser;
use App\Models\ShiftCal\Event;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SharedPlanService
{
    public function lockConnection(int $appId, int $userId): object
    {
        $link = DB::table('shiftcal_partner_links')->where('app_id', $appId)->where('user_id', $userId)->first();
        abort_unless($link, 404, 'Eş bağlantısı bulunamadı.');
        $users = [$userId, $link->partner_user_id];
        DB::table('users')->whereIn('id', $users)->orderBy('id')->lockForUpdate()->get();
        $current = DB::table('shiftcal_partner_links')->where('app_id', $appId)->where('user_id', $userId)->where('connection_id', $link->connection_id)->lockForUpdate()->first();
        abort_unless($current, 404, 'Eş bağlantısı kaldırılmış.');
        $memberships = AppUser::where('app_id', $appId)->whereIn('user_id', $users)->orderBy('user_id')->lockForUpdate()->get();
        abort_unless($memberships->count() === 2 && $memberships->every(fn (AppUser $membership): bool => $membership->is_active), 404, 'Eş bağlantısı kullanılamıyor.');

        return $current;
    }

    public function conflicts(int $appId, int $first, int $second, CarbonImmutable $start, CarbonImmutable $end, ?string $excluding = null): bool
    {
        $event = Event::where('app_id', $appId)->whereIn('user_id', [$first, $second])->where('type', '!=', 'freeTime')
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->when(DB::transactionLevel() > 0, fn ($query) => $query->lockForUpdate())->first(['id']);
        if ($event) {
            return true;
        }

        return DB::table('shiftcal_shared_plans')->where('app_id', $appId)->where('status', 'accepted')
            ->where(fn ($query) => $query->whereIn('proposer_id', [$first, $second])->orWhereIn('recipient_id', [$first, $second]))
            ->when($excluding, fn ($query) => $query->where('id', '!=', $excluding))
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->when(DB::transactionLevel() > 0, fn ($query) => $query->lockForUpdate())->first(['id']) !== null;
    }

    /** @return array<string, mixed> */
    public function resource(object $plan, int $userId): array
    {
        $start = CarbonImmutable::parse($plan->starts_at)->utc();
        $end = CarbonImmutable::parse($plan->ends_at)->utc();
        $status = $plan->status === 'pending' && $start->lte(now()) ? 'expired' : $plan->status;

        return [
            'id' => $plan->id, 'title' => $plan->title,
            'starts_at' => $start->toIso8601String(), 'ends_at' => $end->toIso8601String(),
            'timezone' => $plan->timezone, 'status' => $status,
            'direction' => $plan->recipient_id === $userId ? 'incoming' : 'outgoing',
            'conflicts_with_schedule' => $status === 'accepted' && $this->conflicts($plan->app_id, $plan->proposer_id, $plan->recipient_id, $start, $end, $plan->id),
        ];
    }
}
