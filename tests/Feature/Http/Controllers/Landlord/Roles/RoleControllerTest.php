<?php

use App\Enums\Landlord\Permission;
use App\Enums\Landlord\RoleName;
use App\Models\Landlord\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);

describe('index', function () {
    it('lists seeded roles', function () {
        $this->getJson('/api/landlord/roles')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    });
});

describe('permissions', function () {
    it('lists the seeded permission catalog', function () {
        $this->getJson('/api/landlord/permissions')
            ->assertOk()
            ->assertJsonFragment(['name' => Permission::TenantsView->value]);
    });
});

describe('store', function () {
    it('creates a role with selected permissions', function () {
        $this->postJson('/api/landlord/roles', [
            'name' => 'Billing',
            'permissions' => [Permission::InvoicesView->value, Permission::InvoicesPay->value],
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Billing');

        $role = Role::findByName('Billing', 'web');
        expect($role->hasPermissionTo(Permission::InvoicesPay->value))->toBeTrue();
    });
});

describe('update', function () {
    it('returns 422 when Super Admin permissions are stripped', function () {
        $role = Role::findByName(RoleName::SuperAdmin->value, 'web');

        $this->putJson("/api/landlord/roles/{$role->id}", [
            'permissions' => [Permission::TenantsView->value],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions']);
    });
});

describe('destroy', function () {
    it('returns 422 when Super Admin is deleted', function () {
        $role = Role::findByName(RoleName::SuperAdmin->value, 'web');

        $this->deleteJson("/api/landlord/roles/{$role->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('deletes an unprotected role', function () {
        $role = Role::create(['name' => 'Temporary', 'guard_name' => 'web']);

        $this->deleteJson("/api/landlord/roles/{$role->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    });

    it('returns 403 when the user cannot delete roles', function () {
        $actor = User::factory()->create();
        actingAsLandlord($actor, superAdmin: false);
        $actor->assignRole(RoleName::Operator->value);

        $role = Role::create(['name' => 'Temporary', 'guard_name' => 'web']);

        $this->deleteJson("/api/landlord/roles/{$role->id}")
            ->assertForbidden();
    });
});
