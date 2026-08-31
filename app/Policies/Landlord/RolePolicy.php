<?php

declare(strict_types=1);

namespace App\Policies\Landlord;

use App\Enums\Landlord\Permission;
use App\Models\Landlord\User;
use App\Policies\Landlord\Concerns\ChecksPermission;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allow($user, Permission::RolesView);
    }

    public function view(User $user, Role $role): bool
    {
        return $this->allow($user, Permission::RolesView);
    }

    public function create(User $user): bool
    {
        return $this->allow($user, Permission::RolesCreate);
    }

    public function update(User $user, Role $role): bool
    {
        return $this->allow($user, Permission::RolesUpdate);
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->allow($user, Permission::RolesDelete);
    }
}
