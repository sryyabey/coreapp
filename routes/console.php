<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('billing:retry-notifications')->everyTenMinutes()->withoutOverlapping();

Schedule::command('billing:check-subscriptions')->everyFiveMinutes()->withoutOverlapping();

Schedule::command('shiftcal:dispatch-notifications')->everyMinute()->withoutOverlapping();

Schedule::command('support:dispatch-notifications')->everyMinute()->withoutOverlapping();
