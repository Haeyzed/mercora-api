<?php

use App\Console\Commands\Landlord\ProcessSubscriptionsCommand;
use App\Console\Commands\Landlord\VerifyPendingPaymentsCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(ProcessSubscriptionsCommand::class)
    ->hourly()
    ->withoutOverlapping();

Schedule::command(VerifyPendingPaymentsCommand::class)
    ->everyFifteenMinutes()
    ->withoutOverlapping();
