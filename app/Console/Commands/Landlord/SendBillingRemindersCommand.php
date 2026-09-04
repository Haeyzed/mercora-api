<?php

declare(strict_types=1);

namespace App\Console\Commands\Landlord;

use App\Services\Landlord\BillingOpsService;
use Illuminate\Console\Command;

class SendBillingRemindersCommand extends Command
{
    protected $signature = 'landlord:send-billing-reminders';

    protected $description = 'Send due-soon, overdue, and renewal billing reminders.';

    public function handle(BillingOpsService $billingOps): int
    {
        $counts = $billingOps->sendReminders();

        $this->info(sprintf(
            'Reminders sent — due soon: %d, overdue: %d, renewal: %d',
            $counts['due_soon'],
            $counts['overdue'],
            $counts['renewal'],
        ));

        return self::SUCCESS;
    }
}
