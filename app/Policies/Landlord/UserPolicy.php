<?php

declare(strict_types=1);

namespace App\Policies\Landlord;

use App\Enums\Landlord\Permission;
use App\Models\Landlord\User;
use App\Policies\Landlord\Concerns\ChecksPermission;

class UserPolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allow($user, Permission::UsersView);
    }

    public function view(User $user, User $model): bool
    {
        return $this->allow($user, Permission::UsersView);
    }

    public function create(User $user): bool
    {
        return $this->allow($user, Permission::UsersCreate);
    }

    public function update(User $user, User $model): bool
    {
        return $this->allow($user, Permission::UsersUpdate);
    }

    public function delete(User $user, User $model): bool
    {
        return $this->allow($user, Permission::UsersDelete);
    }

    public function activate(User $user, User $model): bool
    {
        return $this->allow($user, Permission::UsersUpdate);
    }

    public function deactivate(User $user, User $model): bool
    {
        return $this->allow($user, Permission::UsersUpdate);
    }

    public function assignRole(User $user, User $model): bool
    {
        return $this->allow($user, Permission::UsersUpdate);
    }
}
