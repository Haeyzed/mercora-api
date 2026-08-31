<?php

declare(strict_types=1);

namespace App\Services\Landlord\Permissions;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

/**
 * Exposes the seeded landlord permission catalog.
 *
 * Domain: Spatie Permission definitions for the landlord guard.
 *
 * Invariants:
 * - Permissions are seeded and read-only through the API; they are not created or mutated here.
 *
 * Side effects: none (read-only queries).
 */
class PermissionService
{
    /**
     * Return all permissions ordered by name.
     *
     * @return Collection<int, Permission>
     */
    public function all(): Collection
    {
        return Permission::query()->orderBy('name')->get();
    }
}
