<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Tenant\PermissionSeeder;
use Database\Seeders\Tenant\RoleSeeder;
use Illuminate\Database\Seeder;

/**
 * Tenant database seeders — RBAC only. Never create users here.
 */
class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);
    }
}
