<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\RoleName;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\User;
use App\Services\Concerns\PaginatesRequests;
use App\Services\Landlord\Plans\UsageLimiter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Administers tenant staff users and seat limits.
 *
 * Domain: tenant DB user accounts (auth remains in {@see AuthService}).
 *
 * Invariants:
 * - Creating a user requires an entitled users.max seat via {@see UsageLimiter}.
 * - The last Admin cannot be soft-deleted or deactivated.
 * - Roles default to Staff on create when omitted.
 */
class UserService
{
    use PaginatesRequests;

    public function __construct(private UsageLimiter $usageLimiter) {}

    /**
     * Paginate tenant users with filter, search, and includes.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return User::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->withIncludes($request->query('include'))
            ->with('roles')
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString();
    }

    /**
     * Load a user with optional allowed relationships and roles.
     */
    public function show(User $user, Request $request): User
    {
        return $user->loadAllowedIncludes($request->query('include'))->loadMissing('roles');
    }

    /**
     * Create a tenant user, enforcing the users.max seat cap.
     *
     * @param  array{name: string, email: string, password: string, phone?: string|null, is_active?: bool, roles?: list<string>}  $data
     *
     * @throws ValidationException|Throwable
     */
    public function store(array $data): User
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            throw ValidationException::withMessages([
                'feature' => 'Tenant context is required.',
            ]);
        }

        $this->usageLimiter->assertCanCreate($tenant, UsageLimiter::FEATURE_USERS_MAX);

        return DB::transaction(function () use ($data): User {
            $roles = $data['roles'] ?? [RoleName::Staff->value];
            unset($data['roles']);

            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            $user->syncRoles($roles);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $user->load('roles');
        });
    }

    /**
     * Update a tenant user's profile fields.
     *
     * @param  array{name?: string, email?: string, phone?: string|null, password?: string, is_active?: bool}  $data
     *
     * @throws ValidationException
     */
    public function update(User $user, array $data): User
    {
        if (array_key_exists('is_active', $data) && $data['is_active'] === false) {
            $this->guardLastAdmin($user);
        }

        $user->update($data);

        return $user->refresh()->load('roles');
    }

    /**
     * Soft delete a tenant user.
     *
     * @throws ValidationException When deleting the last Admin.
     */
    public function destroy(User $user): void
    {
        $this->guardLastAdmin($user);

        $user->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @throws ValidationException
     */
    private function guardLastAdmin(User $user): void
    {
        if (! $user->hasRole(RoleName::Admin->value)) {
            return;
        }

        $adminCount = User::query()
            ->role(RoleName::Admin->value)
            ->count();

        if ($adminCount <= 1) {
            throw ValidationException::withMessages([
                'role' => 'The last Admin cannot be removed or deactivated.',
            ]);
        }
    }
}
