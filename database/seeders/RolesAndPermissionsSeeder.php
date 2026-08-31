<?php

namespace Database\Seeders;

use App\Support\Landlord\Authorization;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        Authorization::seed();
    }
}
