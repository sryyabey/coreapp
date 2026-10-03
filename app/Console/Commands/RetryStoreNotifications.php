<?php

namespace App\Console\Commands;

use App\Jobs\ProcessStoreNotificationJob;
use App\Models\StoreNotification;
use Illuminate\Console\Command;

class RetryStoreNotifications extends Command
{
    protected $signature = 'billing:retry-notifications';

    protected $description = 'Retry unprocessed store notifications, including purchases not linked yet';

    public function handle(): int
    {
        StoreNotification::whereIn('status', ['pending', 'failed', 'waiting'])->where('updated_at', '<=', now()->subMinutes(10))
            ->orderBy('id')->limit(500)->get()->each(function (StoreNotification $event): void {
                ProcessStoreNotificationJob::dispatch($event->id);
            });

        return self::SUCCESS;
    }
}
