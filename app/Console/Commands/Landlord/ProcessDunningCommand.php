<?php

declare(strict_types=1);

namespace App\Console\Commands\Landlord;

use App\Services\Landlord\BillingOpsService;
use Illuminate\Console\Command;

class ProcessDunningCommand extends Command
{
    protected $signature = 'landlord:process-dunning';

    protected $description = 'Send dunning notices for past-due subscriptions on the configured cadence.';

    public function handle(BillingOpsService $billingOps): int
    {
        $dunned = $billingOps->processDunning();

        $this->info("Dunning notices sent: {$dunned}");

        return self::SUCCESS;
    }
}
