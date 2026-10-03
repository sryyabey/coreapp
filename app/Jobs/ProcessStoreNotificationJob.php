<?php

namespace App\Jobs;

use App\Http\ProcessStoreNotification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessStoreNotificationJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public int $uniqueFor = 7200;

    public function __construct(public int $notificationId)
    {
        $this->onQueue('billing');
    }

    public function uniqueId(): string
    {
        return (string) $this->notificationId;
    }

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(ProcessStoreNotification $processor): void
    {
        $processor->handle($this->notificationId);
    }
}
