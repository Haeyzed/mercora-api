<?php

declare(strict_types=1);

namespace App\Console\Commands\Landlord;

use App\Services\Landlord\Subscriptions\SubscriptionService;
use Illuminate\Console\Command;

class ProcessSubscriptionsCommand extends Command
{
    protected $signature = 'landlord:process-subscriptions';

    protected $description = 'Convert ended trials, renew due current subscriptions, and keep billing periods current.';

    public function handle(SubscriptionService $subscriptionService): int
    {
        $subscriptionService->processDue();

        return self::SUCCESS;
    }
}
