<?php

declare(strict_types=1);

namespace App\Policies\Landlord;

use App\Enums\Landlord\Permission;
use App\Models\Landlord\Domain;
use App\Models\Landlord\User;
use App\Policies\Landlord\Concerns\ChecksPermission;

class DomainPolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allow($user, Permission::DomainsView);
    }

    public function create(User $user): bool
    {
        return $this->allow($user, Permission::DomainsCreate);
    }

    public function delete(User $user, Domain $domain): bool
    {
        return $this->allow($user, Permission::DomainsDelete);
    }
}
