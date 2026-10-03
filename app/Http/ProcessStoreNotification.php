<?php

namespace App\Http;

use App\Models\Purchase;
use App\Models\StoreNotification;
use Illuminate\Support\Facades\Cache;

class ProcessStoreNotification
{
    public function __construct(private RefreshPurchase $refresher) {}

    public function handle(int $id): void
    {
        $event = StoreNotification::findOrFail($id);
        Cache::lock('billing-store:'.$event->store_app_id, 120)->block(5, function () use ($event): void {
            $event->refresh();
            if (in_array($event->status, ['processed', 'ignored', 'exhausted'], true)) {
                return;
            }
            $event->increment('attempts');
            try {
                $this->process($event);
            } catch (\Throwable $exception) {
                $event->update(['status' => $event->attempts >= 20 ? 'exhausted' : 'failed', 'last_error' => class_basename($exception)]);
                throw $exception;
            }
        });
    }

    private function process(StoreNotification $event): void
    {
        if (! $event->identity) {
            $event->update(['status' => 'ignored', 'processed_at' => now()]);

            return;
        }
        $query = Purchase::where('store_app_id', $event->store_app_id)->where('identity', $event->identity);
        if ($event->environment !== 'unknown') {
            $query->where('environment', $event->environment);
        }
        $purchase = $query->first();
        if (! $purchase) {
            $event->update(['status' => 'waiting', 'last_error' => 'PurchaseNotLinked']);

            return;
        }
        $this->refresher->refreshLocked($purchase, $event->proof);
        $event->update(['status' => 'processed', 'processed_at' => now(), 'last_error' => null]);
    }
}
