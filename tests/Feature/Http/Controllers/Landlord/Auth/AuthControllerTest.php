<?php

use App\Models\Landlord\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(LazilyRefreshDatabase::class);

describe('login', function () {
    it('issues a token for valid landlord credentials', function () {
        $user = User::factory()->create([
            'email' => 'admin@mercora.test',
        ]);

        $this->postJson('/api/landlord/auth/login', [
            'email' => 'admin@mercora.test',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'admin@mercora.test')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonMissingPath('data.user.password');

        $this->assertDatabaseCount('personal_access_tokens', 1);
    });

    it('returns 422 when the user is inactive', function () {
        User::factory()->inactive()->create([
            'email' => 'admin@mercora.test',
        ]);

        $this->postJson('/api/landlord/auth/login', [
            'email' => 'admin@mercora.test',
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    });

    it('returns 422 when the credentials are incorrect', function () {
        User::factory()->create([
            'email' => 'admin@mercora.test',
        ]);

        $this->postJson('/api/landlord/auth/login', [
            'email' => 'admin@mercora.test',
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    });

    it('returns 422 when required login fields are missing', function () {
        $this->postJson('/api/landlord/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    });
});

describe('me', function () {
    it('returns the authenticated landlord user', function () {
        $user = User::factory()->create([
            'name' => 'Ada Lovelace',
            'email' => 'ada@mercora.test',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/landlord/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'Ada Lovelace')
            ->assertJsonPath('data.email', 'ada@mercora.test')
            ->assertJsonMissingPath('data.password');
    });

    it('returns 401 when no token is provided', function () {
        $this->getJson('/api/landlord/auth/me')
            ->assertUnauthorized();
    });
});

describe('logout', function () {
    it('revokes the current landlord token', function () {
        User::factory()->create([
            'email' => 'admin@mercora.test',
        ]);

        $token = $this->postJson('/api/landlord/auth/login', [
            'email' => 'admin@mercora.test',
            'password' => 'password',
        ])->json('data.token');

        $this->withToken($token)
            ->postJson('/api/landlord/auth/logout')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    });

    it('returns 401 when no token is provided', function () {
        $this->postJson('/api/landlord/auth/logout')
            ->assertUnauthorized();
    });
});

describe('protected landlord routes', function () {
    it('returns 401 when a world route is requested without a token', function () {
        $this->getJson('/api/landlord/world/countries')
            ->assertUnauthorized();
    });

    it('returns 401 when a tenant route is requested without a token', function () {
        $this->getJson('/api/landlord/tenants')
            ->assertUnauthorized();
    });

    it('returns 401 when a plan route is requested without a token', function () {
        $this->getJson('/api/landlord/plans')
            ->assertUnauthorized();
    });

    it('returns 401 when a subscription route is requested without a token', function () {
        $this->getJson('/api/landlord/subscriptions')
            ->assertUnauthorized();
    });

    it('returns 401 when an invoice route is requested without a token', function () {
        $this->getJson('/api/landlord/invoices')
            ->assertUnauthorized();
    });

    it('returns 401 when a setting route is requested without a token', function () {
        $this->getJson('/api/landlord/settings')
            ->assertUnauthorized();
    });

    it('returns 401 when a notification route is requested without a token', function () {
        $this->getJson('/api/landlord/notifications')
            ->assertUnauthorized();
    });

    it('returns 401 when an API key route is requested without a token', function () {
        $this->getJson('/api/landlord/api-keys')
            ->assertUnauthorized();
    });

    it('returns 401 when an activity route is requested without a token', function () {
        $this->getJson('/api/landlord/activities')
            ->assertUnauthorized();
    });
});
