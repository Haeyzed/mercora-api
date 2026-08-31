<?php

use App\Enums\Landlord\RoleName;
use App\Models\Landlord\Activity;
use App\Models\Landlord\Tenant;
use App\Models\Landlord\User;
use App\Models\Shared\Country;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('returns 401 when a sensitive route is requested without a token', function () {
    $this->postJson('/api/landlord/tenants/'.fake()->uuid().'/suspend')
        ->assertUnauthorized();
});

it('returns 403 when an authenticated user lacks the tenant suspend permission', function () {
    $actor = User::factory()->create();
    actingAsLandlord($actor, superAdmin: false);

    $tenant = Tenant::factory()->active()->create();

    $this->postJson("/api/landlord/tenants/{$tenant->id}/suspend")
        ->assertForbidden();
});

it('allows an Operator to view tenants but not purge activities', function () {
    $actor = User::factory()->create();
    actingAsLandlord($actor, superAdmin: false);
    $actor->assignRole(RoleName::Operator->value);

    $activity = Activity::factory()->create();

    $this->getJson('/api/landlord/tenants')->assertOk();
    $this->getJson('/api/landlord/activities')->assertOk();
    $this->deleteJson("/api/landlord/activities/{$activity->id}")->assertForbidden();
});

it('returns 403 when an Operator writes World data', function () {
    $actor = User::factory()->create();
    actingAsLandlord($actor, superAdmin: false);
    $actor->assignRole(RoleName::Operator->value);

    $country = Country::query()->first() ?? Country::factory()->create();

    $this->getJson('/api/landlord/world/countries')->assertOk();
    $this->deleteJson("/api/landlord/world/countries/{$country->id}")->assertForbidden();
});
