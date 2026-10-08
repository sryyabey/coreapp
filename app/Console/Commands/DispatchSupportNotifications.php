<?php

namespace App\Console\Commands;

use App\Jobs\SendSupportReplyPush;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DispatchSupportNotifications extends Command
{
    protected $signature = 'support:dispatch-notifications';

    protected $description = 'Queue pending application-scoped support reply notifications';

    public function handle(): int
    {
        DB::table('support_push_outbox')->where('status', 'pending')->orderBy('id')->chunk(100, function ($events): void {
            foreach ($events as $event) {
                SendSupportReplyPush::dispatch($event->id);
            }
        });

        return self::SUCCESS;
    }
}
