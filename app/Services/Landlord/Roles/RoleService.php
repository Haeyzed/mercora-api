<?php

declare(strict_types=1);

namespace App\Services\Landlord\Roles;

use App\Enums\Landlord\Permission;
use App\Enums\Landlord\RoleName;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Manages the landlord role catalog backed by Spatie Permission.
 *
 * Domain: named roles with attached permissions for landlord users.
 *
 * Invariants:
 * - The Super Admin role cannot be renamed, deleted, or stripped of any permission.
 * - Roles use the web guard.
 *
 * Side effects: creates, updates, and deletes {@see Role} records and syncs permission pivots.
 */
class RoleService
{
    /**
     * Paginate roles with their permissions, optionally filtered by search term.
     *
     * @return LengthAwarePaginator<int, Role>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        $term = is_string($request->query('search')) ? trim($request->query('search')) : '';

        return Role::query()
            ->with('permissions')
            ->when($term !== '', fn ($query) => $query->where('name', 'like', '%'.$term.'%'))
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString();
    }

    /**
     * Load a role with its permissions.
     */
    public function show(Role $role): Role
    {
        return $role->load('permissions');
    }

    /**
     * Create a role and optionally sync permissions.
     *
     * @param  array{name: string, permissions?: list<string>}  $data
     */
    public function store(array $data): Role
    {
        return DB::transaction(function () use ($data): Role {
            $role = Role::create([
                'name' => $data['name'],
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($data['permissions'] ?? []);

            return $role->load('permissions');
        });
    }

    /**
     * Update a role's name and/or permissions.
     *
     * @param  array{name?: string, permissions?: list<string>}  $data
     *
     * @throws ValidationException When renaming or demoting the Super Admin role.
     */
    public function update(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data): Role {
            if ($this->isProtected($role) && isset($data['name']) && $data['name'] !== $role->name) {
                throw ValidationException::withMessages([
                    'name' => 'The Super Admin role cannot be renamed.',
                ]);
            }

            if ($this->isProtected($role) && isset($data['permissions'])) {
                $missing = array_diff(Permission::values(), $data['permissions']);

                if ($missing !== []) {
                    throw ValidationException::withMessages([
                        'permissions' => 'The Super Admin role must keep every permission.',
                    ]);
                }
            }

            if (isset($data['name'])) {
                $role->name = $data['name'];
                $role->save();
            }

            if (isset($data['permissions'])) {
                $role->syncPermissions($data['permissions']);
            }

            return $role->refresh()->load('permissions');
        });
    }

    /**
     * Delete a role.
     *
     * @throws ValidationException When attempting to delete the Super Admin role.
     */
    public function destroy(Role $role): void
    {
        if ($this->isProtected($role)) {
            throw ValidationException::withMessages([
                'name' => 'The Super Admin role cannot be deleted.',
            ]);
        }

        $role->delete();
    }

    /**
     * Determine whether the role is the protected Super Admin role.
     */
    private function isProtected(Role $role): bool
    {
        return $role->name === RoleName::SuperAdmin->value;
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
