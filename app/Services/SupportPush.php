<?php

namespace App\Services;

use App\Jobs\SendSupportReplyPush;
use App\Models\SupportMessage;
use App\Services\ShiftCal\FcmSender;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupportPush
{
    public function enqueue(SupportMessage $message): void
    {
        $ticket = $message->ticket;
        $id = (string) Str::uuid();
        $inserted = DB::table('support_push_outbox')->insertOrIgnore(['id' => $id, 'message_id' => $message->id, 'app_id' => $ticket->app_id, 'user_id' => $ticket->user_id, 'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()]);
        if ($inserted && app(FcmSender::class)->configured($ticket->app->slug)) {
            SendSupportReplyPush::dispatch($id)->afterCommit();
        }
    }

    public function valid(object $event): bool
    {
        if (CarbonImmutable::parse($event->expires_at)->lte(now())) {
            return false;
        }
        $message = SupportMessage::find($event->message_id);
        $ticket = $message?->ticket;

        return $message?->sender_type === 'staff' && $ticket !== null && $ticket->app_id === $event->app_id && $ticket->user_id === $event->user_id && $ticket->app->is_active
            && DB::table('app_users')->where('app_id', $event->app_id)->where('user_id', $event->user_id)->where('is_active', true)->where('support_replies', true)->exists()
            && ($ticket->user_read_at === null || $ticket->user_read_at->lt($message->created_at));
    }
}
