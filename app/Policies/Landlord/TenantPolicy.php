<?php

declare(strict_types=1);

namespace App\Policies\Landlord;

use App\Enums\Landlord\Permission;
use App\Models\Landlord\Tenant;
use App\Models\Landlord\User;
use App\Policies\Landlord\Concerns\ChecksPermission;

class TenantPolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allow($user, Permission::TenantsView);
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $this->allow($user, Permission::TenantsView);
    }

    public function create(User $user): bool
    {
        return $this->allow($user, Permission::TenantsCreate);
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $this->allow($user, Permission::TenantsUpdate);
    }

    public function delete(User $user, ?Tenant $tenant = null): bool
    {
        return $this->allow($user, Permission::TenantsDelete);
    }

    public function restore(User $user, ?Tenant $tenant = null): bool
    {
        return $this->allow($user, Permission::TenantsDelete);
    }

    public function forceDelete(User $user, Tenant $tenant): bool
    {
        return $this->allow($user, Permission::TenantsForceDelete);
    }

    public function provision(User $user, Tenant $tenant): bool
    {
        return $this->allow($user, Permission::TenantsProvision);
    }

    public function activate(User $user, Tenant $tenant): bool
    {
        return $this->allow($user, Permission::TenantsActivate);
    }

    public function suspend(User $user, Tenant $tenant): bool
    {
        return $this->allow($user, Permission::TenantsSuspend);
    }

    public function reactivate(User $user, Tenant $tenant): bool
    {
        return $this->allow($user, Permission::TenantsActivate);
    }
}
