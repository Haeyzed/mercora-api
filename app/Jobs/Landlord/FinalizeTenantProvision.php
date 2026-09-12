<?php

declare(strict_types=1);

namespace App\Jobs\Landlord;

use App\Enums\Tenant\RoleName;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Create the first tenant Admin from pending_provision after RBAC seed.
 */
class FinalizeTenantProvision implements ShouldQueue
{
    use Queueable;

    public function __construct(public Tenant $tenant) {}

    /**
     * @throws Throwable When pending_provision.admin is missing or user creation fails.
     */
    public function handle(): void
    {
        $tenant = $this->tenant->fresh();

        if ($tenant === null) {
            return;
        }

        /** @var array<string, mixed>|null $pending */
        $pending = $tenant->pending_provision;

        if (! is_array($pending) || ! is_array($pending['admin'] ?? null)) {
            throw new \RuntimeException('Tenant pending_provision.admin is required to finalize provisioning.');
        }

        /** @var array{name: string, email: string, password: string, phone?: string|null} $admin */
        $admin = $pending['admin'];

        $tenant->run(function () use ($admin): void {
            $user = User::query()->create([
                'name' => $admin['name'],
                'email' => $admin['email'],
                'phone' => $admin['phone'] ?? null,
                'password' => $admin['password'],
                'is_active' => true,
            ]);

            $user->assignRole(RoleName::Admin->value);

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });

        $tenant->forceFill([
            'pending_provision' => null,
        ])->save();
    }
}
