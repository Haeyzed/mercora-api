<?php

declare(strict_types=1);

namespace App\Services\Landlord\Users;

use App\Enums\Landlord\RoleName;
use App\Models\Landlord\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Landlord user administration. Authentication stays in AuthService.
 */
class UserService
{
    /**
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

    public function show(User $user, Request $request): User
    {
        return $user->loadAllowedIncludes($request->query('include'))->loadMissing('roles');
    }

    /**
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
     * @param  array{name?: string, email?: string, password?: string, is_active?: bool}  $data
     */
    public function update(User $user, array $data): User
    {
        unset($data['roles']);

        $user->update($data);

        return $user->refresh()->load('roles');
    }

    /**
     * @param  list<string>  $roles
     */
    public function syncRoles(User $user, array $roles): User
    {
        return DB::transaction(function () use ($user, $roles): User {
            $this->guardLastSuperAdmin($user, $roles);

            $user->syncRoles($roles);

            return $user->refresh()->load('roles');
        });
    }

    public function activate(User $user): User
    {
        $user->update(['is_active' => true]);

        return $user->refresh()->load('roles');
    }

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
     * @param  list<string>  $roles
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

    private function superAdminCount(): int
    {
        return User::role(RoleName::SuperAdmin->value)->count();
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
