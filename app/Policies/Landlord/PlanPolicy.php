<?php

declare(strict_types=1);

namespace App\Policies\Landlord;

use App\Enums\Landlord\Permission;
use App\Models\Landlord\Plan;
use App\Models\Landlord\User;
use App\Policies\Landlord\Concerns\ChecksPermission;

class PlanPolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allow($user, Permission::PlansView);
    }

    public function view(User $user, Plan $plan): bool
    {
        return $this->allow($user, Permission::PlansView);
    }

    public function create(User $user): bool
    {
        return $this->allow($user, Permission::PlansCreate);
    }

    public function update(User $user, Plan $plan): bool
    {
        return $this->allow($user, Permission::PlansUpdate);
    }

    public function delete(User $user, ?Plan $plan = null): bool
    {
        return $this->allow($user, Permission::PlansDelete);
    }

    public function restore(User $user, ?Plan $plan = null): bool
    {
        return $this->allow($user, Permission::PlansDelete);
    }
}
