<?php

namespace Database\Seeders;

use App\Enums\Landlord\RoleName;
use App\Models\Landlord\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed the default landlord super admin account.
     */
    public function run(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => config('landlord.seed.admin_email')],
            [
                'name' => config('landlord.seed.admin_name'),
                'password' => config('landlord.seed.admin_password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        if (! $user->hasRole(RoleName::SuperAdmin->value)) {
            $user->assignRole(RoleName::SuperAdmin->value);
        }
    }
}
