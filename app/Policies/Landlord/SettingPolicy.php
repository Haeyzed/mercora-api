<?php

declare(strict_types=1);

namespace App\Policies\Landlord;

use App\Enums\Landlord\Permission;
use App\Models\Landlord\User;
use App\Policies\Landlord\Concerns\ChecksPermission;

class SettingPolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allow($user, Permission::SettingsView);
    }

    public function update(User $user): bool
    {
        return $this->allow($user, Permission::SettingsUpdate);
    }
}
