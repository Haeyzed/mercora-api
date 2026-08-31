<?php

declare(strict_types=1);

namespace App\Policies\Landlord\Concerns;

use App\Enums\Landlord\Permission;
use App\Models\Landlord\User;

trait ChecksPermission
{
    protected function allow(User $user, Permission $permission): bool
    {
        return $user->can($permission->value);
    }
}
