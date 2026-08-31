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
 * Landlord role catalog backed by Spatie Permission.
 */
class RoleService
{
    /**
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

    public function show(Role $role): Role
    {
        return $role->load('permissions');
    }

    /**
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
     * @param  array{name?: string, permissions?: list<string>}  $data
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

    public function destroy(Role $role): void
    {
        if ($this->isProtected($role)) {
            throw ValidationException::withMessages([
                'name' => 'The Super Admin role cannot be deleted.',
            ]);
        }

        $role->delete();
    }

    private function isProtected(Role $role): bool
    {
        return $role->name === RoleName::SuperAdmin->value;
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
