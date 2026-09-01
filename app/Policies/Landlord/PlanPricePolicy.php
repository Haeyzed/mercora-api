<?php

declare(strict_types=1);

namespace App\Policies\Landlord;

use App\Enums\Landlord\Permission;
use App\Models\Landlord\PlanPrice;
use App\Models\Landlord\User;
use App\Policies\Landlord\Concerns\ChecksPermission;

class PlanPricePolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allow($user, Permission::PlansView);
    }

    public function view(User $user, PlanPrice $planPrice): bool
    {
        return $this->allow($user, Permission::PlansView);
    }

    public function create(User $user): bool
    {
        return $this->allow($user, Permission::PlansCreate);
    }

    public function update(User $user, PlanPrice $planPrice): bool
    {
        return $this->allow($user, Permission::PlansUpdate);
    }

    public function delete(User $user, PlanPrice $planPrice): bool
    {
        return $this->allow($user, Permission::PlansDelete);
    }

    public function activate(User $user, PlanPrice $planPrice): bool
    {
        return $this->allow($user, Permission::PlansUpdate);
    }

    public function deactivate(User $user, PlanPrice $planPrice): bool
    {
        return $this->allow($user, Permission::PlansUpdate);
    }
}
