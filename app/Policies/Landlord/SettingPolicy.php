<?php

declare(strict_types=1);

namespace App\Policies\Landlord;

use App\Enums\Landlord\Permission;
use App\Models\Landlord\Setting;
use App\Models\Landlord\User;
use App\Policies\Landlord\Concerns\ChecksPermission;

class SettingPolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allow($user, Permission::SettingsView);
    }

    public function view(User $user, Setting $setting): bool
    {
        return $this->allow($user, Permission::SettingsView);
    }

    public function create(User $user): bool
    {
        return $this->allow($user, Permission::SettingsCreate);
    }

    public function update(User $user, Setting $setting): bool
    {
        return $this->allow($user, Permission::SettingsUpdate);
    }

    public function delete(User $user, ?Setting $setting = null): bool
    {
        return $this->allow($user, Permission::SettingsDelete);
    }

    public function restore(User $user, ?Setting $setting = null): bool
    {
        return $this->allow($user, Permission::SettingsDelete);
    }
}
