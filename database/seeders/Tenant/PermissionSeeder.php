<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Support\Tenant\Authorization;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        Authorization::seed();
    }
}
