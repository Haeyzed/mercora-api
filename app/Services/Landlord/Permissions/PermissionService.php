<?php

declare(strict_types=1);

namespace App\Services\Landlord\Permissions;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

/**
 * Seeded landlord permission catalog. Permissions are not created through the API.
 */
class PermissionService
{
    /**
     * @return Collection<int, Permission>
     */
    public function all(): Collection
    {
        return Permission::query()->orderBy('name')->get();
    }
}
