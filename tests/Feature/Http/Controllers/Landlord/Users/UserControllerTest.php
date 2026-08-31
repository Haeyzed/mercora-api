<?php

use App\Enums\Landlord\RoleName;
use App\Models\Landlord\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

describe('index', function () {
    it('returns a paginated list of users', function () {
        User::factory()->create(['name' => 'Ada Lovelace']);

        $this->getJson('/api/landlord/users')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'name', 'email', 'is_active'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    });
});

describe('store', function () {
    it('creates a landlord user and assigns roles', function () {
        $this->postJson('/api/landlord/users', [
            'name' => 'Grace Hopper',
            'email' => 'grace@mercora.test',
            'password' => 'password',
            'roles' => [RoleName::Operator->value],
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Grace Hopper')
            ->assertJsonPath('data.email', 'grace@mercora.test')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonMissingPath('data.password');

        $user = User::query()->where('email', 'grace@mercora.test')->first();
        expect($user)->not->toBeNull()
            ->and($user->hasRole(RoleName::Operator->value))->toBeTrue();
    });
});

describe('activate and deactivate', function () {
    it('deactivates and reactivates a user', function () {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Operator->value);

        $this->postJson("/api/landlord/users/{$user->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->postJson("/api/landlord/users/{$user->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);
    });

    it('returns 422 when deactivating the last Super Admin', function () {
        $user = User::query()->role(RoleName::SuperAdmin->value)->first();

        $this->postJson("/api/landlord/users/{$user->id}/deactivate")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_active']);
    });
});

describe('roles', function () {
    it('replaces the assigned roles', function () {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Operator->value);

        $this->postJson("/api/landlord/users/{$user->id}/roles", [
            'roles' => [RoleName::Operator->value],
        ])
            ->assertOk();

        expect($user->fresh()->hasRole(RoleName::Operator->value))->toBeTrue();
    });
});

describe('authorization', function () {
    it('returns 403 when the user cannot create users', function () {
        $actor = User::factory()->create();
        actingAsLandlord($actor, superAdmin: false);
        $actor->assignRole(RoleName::Operator->value);

        $this->postJson('/api/landlord/users', [
            'name' => 'Blocked User',
            'email' => 'blocked@mercora.test',
            'password' => 'password',
        ])->assertForbidden();
    });
});
