<?php

namespace App\Console\Commands;

use App\Jobs\RefreshPurchaseJob;
use App\Models\Purchase;
use Illuminate\Console\Command;

class CheckSubscriptions extends Command
{
    protected $signature = 'billing:check-subscriptions';

    protected $description = 'Queue due subscription checks to reconcile missed store notifications';

    public function handle(): int
    {
        Purchase::where('environment', 'production')
            ->where(function ($query): void {
                $query->whereNull('next_check_at')->orWhere('next_check_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNotIn('status', ['expired', 'revoked'])->orWhere('expires_at', '>=', now()->subDays(30));
            })
            ->orderBy('next_check_at')->orderBy('id')->limit(500)->get()->each(function (Purchase $purchase): void {
                RefreshPurchaseJob::dispatch($purchase->id);
            });

        return self::SUCCESS;
    }
}
