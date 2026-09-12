<?php

declare(strict_types=1);

use App\Enums\Landlord\TenantStatus;
use App\Enums\Tenant\Permission;
use App\Enums\Tenant\RoleName;
use App\Jobs\Landlord\FinalizeTenantProvision;
use App\Models\Tenant\User;
use App\Support\Tenant\Authorization;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Support\TenantTestContext;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    config([
        'tenancy.central_domains' => ['mercora.test', 'localhost', '127.0.0.1'],
    ]);
});

afterEach(function (): void {
    if (isset($this->tenantContext) && $this->tenantContext instanceof TenantTestContext) {
        $this->tenantContext->tearDown();
    }
});

it('seeds tenant RBAC without creating users', function (): void {
    $context = $this->tenantContext = TenantTestContext::provision();
    $context->seedRbac();

    $context->tenant->run(function (): void {
        expect(User::query()->count())->toBe(0)
            ->and(Role::findByName(RoleName::Admin->value, Authorization::GUARD))->not->toBeNull()
            ->and(Role::findByName(RoleName::Staff->value, Authorization::GUARD))->not->toBeNull();

        $admin = Role::findByName(RoleName::Admin->value, Authorization::GUARD);
        expect($admin->permissions->pluck('name')->all())->toEqualCanonicalizing(Permission::values());

        $staff = Role::findByName(RoleName::Staff->value, Authorization::GUARD);
        expect($staff->permissions)->toHaveCount(0);
    });
});

it('finalizes pending admin as Admin and clears pending_provision', function (): void {
    $context = $this->tenantContext = TenantTestContext::provisionWithAdmin([
        'name' => 'Store Admin',
        'email' => 'admin@store.test',
        'password' => 'Password1!',
    ]);

    expect($context->tenant->fresh()->pending_provision)->toBeNull()
        ->and($context->tenant->fresh()->status)->toBe(TenantStatus::Active);

    $context->tenant->run(function (): void {
        $user = User::query()->where('email', 'admin@store.test')->first();

        expect($user)->not->toBeNull()
            ->and($user->hasRole(RoleName::Admin->value))->toBeTrue();
    });
});

it('allows the finalized Admin to log in via tenant auth', function (): void {
    $context = $this->tenantContext = TenantTestContext::provisionWithAdmin([
        'name' => 'Store Admin',
        'email' => 'login-admin@store.test',
        'password' => 'Password1!',
    ]);

    $this->postJson($context->url('/api/tenant/auth/login'), [
        'email' => 'login-admin@store.test',
        'password' => 'Password1!',
    ])
        ->assertOk()
        ->assertJsonPath('data.user.email', 'login-admin@store.test')
        ->assertJsonPath('data.user.roles.0', RoleName::Admin->value);
});

it('fails finalize when pending_provision.admin is missing', function (): void {
    $context = $this->tenantContext = TenantTestContext::provision(TenantStatus::Pending, [
        'pending_provision' => null,
        'provisioned_at' => null,
    ]);
    $context->seedRbac();

    expect(fn () => (new FinalizeTenantProvision($context->tenant))->handle())
        ->toThrow(RuntimeException::class, 'pending_provision.admin');
});
