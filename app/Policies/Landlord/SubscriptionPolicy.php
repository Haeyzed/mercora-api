<?php

declare(strict_types=1);

namespace App\Policies\Landlord;

use App\Enums\Landlord\Permission;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\User;
use App\Policies\Landlord\Concerns\ChecksPermission;

class SubscriptionPolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allow($user, Permission::SubscriptionsView);
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return $this->allow($user, Permission::SubscriptionsView);
    }

    public function create(User $user): bool
    {
        return $this->allow($user, Permission::SubscriptionsCreate);
    }

    public function changePlan(User $user, Subscription $subscription): bool
    {
        return $this->allow($user, Permission::SubscriptionsChangePlan);
    }

    public function update(User $user, Subscription $subscription): bool
    {
        return $this->changePlan($user, $subscription);
    }

    public function delete(User $user, ?Subscription $subscription = null): bool
    {
        return false;
    }

    public function restore(User $user, ?Subscription $subscription = null): bool
    {
        return false;
    }

    public function cancel(User $user, Subscription $subscription): bool
    {
        return $this->allow($user, Permission::SubscriptionsCancel);
    }

    public function renew(User $user, Subscription $subscription): bool
    {
        return $this->allow($user, Permission::SubscriptionsRenew);
    }
}
