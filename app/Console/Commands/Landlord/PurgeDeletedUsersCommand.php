<?php

declare(strict_types=1);

namespace App\Console\Commands\Landlord;

use App\Models\Landlord\User;
use App\Services\Landlord\SettingService;
use Illuminate\Console\Command;

class PurgeDeletedUsersCommand extends Command
{
    protected $signature = 'landlord:purge-deleted-users';

    protected $description = 'Force-delete soft-deleted landlord users past the compliance retention window.';

    public function handle(SettingService $settings): int
    {
        $days = max(7, (int) $settings->value('compliance.soft_deleted_user_retention_days', 90));
        $cutoff = now()->subDays($days);
        $purged = 0;

        User::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->orderBy('id')
            ->each(function (User $user) use (&$purged): void {
                $user->forceDelete();
                $purged++;
            });

        $this->info("Purged soft-deleted users: {$purged}");

        return self::SUCCESS;
    }
}
