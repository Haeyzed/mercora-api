<?php

declare(strict_types=1);

namespace App\Policies\Landlord;

use App\Enums\Landlord\Permission;
use App\Models\Landlord\Activity;
use App\Models\Landlord\User;
use App\Policies\Landlord\Concerns\ChecksPermission;

class ActivityPolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allow($user, Permission::ActivitiesView);
    }

    public function view(User $user, Activity $activity): bool
    {
        return $this->allow($user, Permission::ActivitiesView);
    }

    public function delete(User $user, ?Activity $activity = null): bool
    {
        return $this->allow($user, Permission::ActivitiesPurge);
    }
}
