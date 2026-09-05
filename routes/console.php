<?php

use App\Console\Commands\Landlord\ProcessDunningCommand;
use App\Console\Commands\Landlord\ProcessSubscriptionsCommand;
use App\Console\Commands\Landlord\PurgeDeletedTenantsCommand;
use App\Console\Commands\Landlord\PurgeDeletedUsersCommand;
use App\Console\Commands\Landlord\SendBillingRemindersCommand;
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

Schedule::command(SendBillingRemindersCommand::class)
    ->daily()
    ->withoutOverlapping();

Schedule::command(ProcessDunningCommand::class)
    ->daily()
    ->withoutOverlapping();

Schedule::command(PurgeDeletedTenantsCommand::class)
    ->daily()
    ->withoutOverlapping();

Schedule::command(PurgeDeletedUsersCommand::class)
    ->daily()
    ->withoutOverlapping();

Schedule::command('activitylog:clean')
    ->daily()
    ->withoutOverlapping();

Schedule::command('sanctum:prune-expired --hours=24')
    ->daily()
    ->withoutOverlapping();
