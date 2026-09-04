<?php

declare(strict_types=1);

namespace App\Console\Commands\Landlord;

use App\Services\Landlord\Tenants\TenantService;
use Illuminate\Console\Command;

class PurgeDeletedTenantsCommand extends Command
{
    protected $signature = 'landlord:purge-deleted-tenants';

    protected $description = 'Force-delete soft-deleted tenants past the retention window.';

    public function handle(TenantService $tenants): int
    {
        $purged = $tenants->purgeExpiredSoftDeletes();

        $this->info("Purged soft-deleted tenants: {$purged}");

        return self::SUCCESS;
    }
}
