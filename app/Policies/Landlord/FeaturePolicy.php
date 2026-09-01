<?php

declare(strict_types=1);

namespace App\Policies\Landlord;

use App\Enums\Landlord\Permission;
use App\Models\Landlord\Feature;
use App\Models\Landlord\User;
use App\Policies\Landlord\Concerns\ChecksPermission;

class FeaturePolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allow($user, Permission::FeaturesView);
    }

    public function view(User $user, Feature $feature): bool
    {
        return $this->allow($user, Permission::FeaturesView);
    }

    public function create(User $user): bool
    {
        return $this->allow($user, Permission::FeaturesCreate);
    }

    public function update(User $user, Feature $feature): bool
    {
        return $this->allow($user, Permission::FeaturesUpdate);
    }

    public function delete(User $user, ?Feature $feature = null): bool
    {
        return $this->allow($user, Permission::FeaturesDelete);
    }

    public function restore(User $user, ?Feature $feature = null): bool
    {
        return $this->allow($user, Permission::FeaturesDelete);
    }
}
