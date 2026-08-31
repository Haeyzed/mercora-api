<?php

declare(strict_types=1);

namespace App\Policies\Landlord;

use App\Enums\Landlord\Permission;
use App\Models\Landlord\User;
use App\Policies\Landlord\Concerns\ChecksPermission;
use Illuminate\Database\Eloquent\Model;

class WorldPolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allow($user, Permission::WorldView);
    }

    public function view(User $user, Model $model): bool
    {
        return $this->allow($user, Permission::WorldView);
    }

    public function create(User $user): bool
    {
        return $this->allow($user, Permission::WorldManage);
    }

    public function update(User $user, ?Model $model = null): bool
    {
        return $this->allow($user, Permission::WorldManage);
    }

    public function delete(User $user, ?Model $model = null): bool
    {
        return $this->allow($user, Permission::WorldManage);
    }

    public function restore(User $user, ?Model $model = null): bool
    {
        return $this->allow($user, Permission::WorldManage);
    }
}
