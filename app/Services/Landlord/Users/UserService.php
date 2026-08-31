<?php

declare(strict_types=1);

namespace App\Services\Landlord\Users;

use App\Enums\Landlord\RoleName;
use App\Models\Landlord\User;
use App\Services\Landlord\Auth\AuthService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Role;

/**
 * Administers landlord users and their roles.
 *
 * Domain: landlord panel user accounts (authentication remains in {@see AuthService}).
 *
 * Invariants:
 * - At least one active Super Admin must always exist; the last one cannot be deactivated, deleted, or demoted.
 * - Role assignment is managed via {@see syncRoles()}, not through create/update payloads.
 *
 * Side effects: creates, updates, activates, deactivates, and soft-deletes {@see User} records; syncs Spatie roles.
 */
class UserService
{
    /**
     * Paginate users using model filter, search, and include scopes.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return User::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->withIncludes($request->query('include'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString();
    }

    /**
     * Paginate user select options as label/value pairs.
     *
     * @return LengthAwarePaginator<int, array{label: string, value: int}>
     */
    public function options(Request $request): LengthAwarePaginator
    {
        return User::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (User $user): array => [
                'label' => $user->name,
                'value' => $user->id,
            ]);
    }

    /**
     * Load a user with optional allowed relationships and roles.
     */
    public function show(User $user, Request $request): User
    {
        return $user->loadAllowedIncludes($request->query('include'))->loadMissing('roles');
    }

    /**
     * Create a landlord user and optionally assign roles.
     *
     * @param  array{name: string, email: string, password: string, is_active?: bool, roles?: list<string>}  $data
     */
    public function store(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (isset($data['roles'])) {
                $user->syncRoles($data['roles']);
            }

            return $user->load('roles');
        });
    }

    /**
     * Update a landlord user's profile fields. Roles are not writable here.
     *
     * @param  array{name?: string, email?: string, password?: string, is_active?: bool}  $data
     */
    public function update(User $user, array $data): User
    {
        unset($data['roles']);

        $user->update($data);

        return $user->refresh()->load('roles');
    }

    /**
     * Replace the user's roles.
     *
     * @param  list<string>  $roles
     *
     * @throws ValidationException When demoting the last Super Admin.
     * @throws RoleDoesNotExist When a role name is invalid.
     */
    public function syncRoles(User $user, array $roles): User
    {
        return DB::transaction(function () use ($user, $roles): User {
            $this->guardLastSuperAdmin($user, $roles);

            $user->syncRoles($roles);

            return $user->refresh()->load('roles');
        });
    }

    /**
     * Activate a landlord user.
     */
    public function activate(User $user): User
    {
        $user->update(['is_active' => true]);

        return $user->refresh()->load('roles');
    }

    /**
     * Deactivate a landlord user.
     *
     * @throws ValidationException When deactivating the last Super Admin.
     */
    public function deactivate(User $user): User
    {
        if ($user->hasRole(RoleName::SuperAdmin->value) && $this->superAdminCount() <= 1) {
            throw ValidationException::withMessages([
                'is_active' => 'The last Super Admin cannot be deactivated.',
            ]);
        }

        $user->update(['is_active' => false]);

        return $user->refresh()->load('roles');
    }

    /**
     * Soft delete a landlord user.
     *
     * @throws ValidationException When deleting the last Super Admin.
     */
    public function destroy(User $user): void
    {
        if ($user->hasRole(RoleName::SuperAdmin->value) && $this->superAdminCount() <= 1) {
            throw ValidationException::withMessages([
                'id' => 'The last Super Admin cannot be deleted.',
            ]);
        }

        $user->delete();
    }

    /**
     * Prevent removing the Super Admin role from the last Super Admin and validate role names.
     *
     * @param  list<string>  $roles
     *
     * @throws ValidationException When demoting the last Super Admin.
     * @throws RoleDoesNotExist When a role name is invalid.
     */
    private function guardLastSuperAdmin(User $user, array $roles): void
    {
        $keepsSuperAdmin = in_array(RoleName::SuperAdmin->value, $roles, true);

        if ($user->hasRole(RoleName::SuperAdmin->value) && ! $keepsSuperAdmin && $this->superAdminCount() <= 1) {
            throw ValidationException::withMessages([
                'roles' => 'The last Super Admin cannot lose that role.',
            ]);
        }

        foreach ($roles as $role) {
            Role::findByName($role, 'web');
        }
    }

    /**
     * Count users with the Super Admin role.
     */
    private function superAdminCount(): int
    {
        return User::role(RoleName::SuperAdmin->value)->count();
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
