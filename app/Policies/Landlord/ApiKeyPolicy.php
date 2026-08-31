<?php

declare(strict_types=1);

namespace App\Policies\Landlord;

use App\Enums\Landlord\Permission;
use App\Models\Landlord\ApiKey;
use App\Models\Landlord\User;
use App\Policies\Landlord\Concerns\ChecksPermission;

class ApiKeyPolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allow($user, Permission::ApiKeysView);
    }

    public function view(User $user, ApiKey $apiKey): bool
    {
        return $this->allow($user, Permission::ApiKeysView);
    }

    public function create(User $user): bool
    {
        return $this->allow($user, Permission::ApiKeysCreate);
    }

    public function update(User $user, ApiKey $apiKey): bool
    {
        return $this->allow($user, Permission::ApiKeysUpdate);
    }

    public function delete(User $user, ?ApiKey $apiKey = null): bool
    {
        return $this->allow($user, Permission::ApiKeysDelete);
    }

    public function restore(User $user, ?ApiKey $apiKey = null): bool
    {
        return $this->allow($user, Permission::ApiKeysDelete);
    }

    public function revoke(User $user, ApiKey $apiKey): bool
    {
        return $this->allow($user, Permission::ApiKeysRevoke);
    }
}
