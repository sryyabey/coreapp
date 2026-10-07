<?php

namespace App\Console\Commands;

use App\Jobs\SendShiftCalPush;
use App\Services\ShiftCal\FcmSender;
use App\Services\ShiftCal\PushOutbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DispatchShiftCalNotifications extends Command
{
    protected $signature = 'shiftcal:dispatch-notifications';

    protected $description = 'Queue pending ShiftCal notifications and due shared plan reminders';

    public function handle(FcmSender $sender, PushOutbox $outbox): int
    {
        if (! $sender->configured()) {
            $this->warn('ShiftCal FCM is not configured. No push messages dispatched.');

            return self::SUCCESS;
        }
        $outbox->reminders();
        DB::table('shiftcal_push_outbox')->where('status', 'pending')->where('due_at', '<=', now())->orderBy('id')->chunk(100, function ($events): void {
            foreach ($events as $event) {
                SendShiftCalPush::dispatch($event->id);
            }
        });

        return self::SUCCESS;
    }
}
