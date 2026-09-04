<?php

use App\Enums\Landlord\ApiKeyStatus;
use App\Models\Landlord\ApiKey;
use App\Models\Landlord\User;
use App\Services\Landlord\Settings\SettingService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

describe('index', function () {
    it('returns a paginated list of API keys without the plaintext token', function () {
        ApiKey::factory()->count(2)->create();

        $this->getJson('/api/landlord/api-keys')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'user_id', 'name', 'prefix', 'status'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
            ])
            ->assertJsonMissingPath('data.0.token')
            ->assertJsonMissingPath('data.0.key_hash');
    });

    it('paginates API keys using the per_page query parameter', function () {
        ApiKey::factory()->count(3)->create();

        $this->getJson('/api/landlord/api-keys?per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonCount(2, 'data');
    });

    it('filters API keys by user id', function () {
        $user = User::factory()->create();
        ApiKey::factory()->for($user)->create();
        ApiKey::factory()->create();

        $this->getJson('/api/landlord/api-keys?filter[user_id]='.$user->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.user_id', $user->id);
    });

    it('filters API keys by status', function () {
        ApiKey::factory()->revoked()->create();
        ApiKey::factory()->create();

        $this->getJson('/api/landlord/api-keys?filter[status]=revoked')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'revoked');
    });

    it('searches API keys by name', function () {
        ApiKey::factory()->create(['name' => 'CI deploy']);
        ApiKey::factory()->create(['name' => 'Local scripts']);

        $this->getJson('/api/landlord/api-keys?search=CI')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'CI deploy');
    });

    it('returns all API keys when search is blank', function () {
        ApiKey::factory()->count(2)->create();

        $this->getJson('/api/landlord/api-keys?search=')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    });

    it('ignores unknown filters instead of querying arbitrary columns', function () {
        ApiKey::factory()->create();

        $this->getJson('/api/landlord/api-keys?filter[password]=secret')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    });

    it('includes the owner when requested', function () {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);
        ApiKey::factory()->for($user)->create();

        $this->getJson('/api/landlord/api-keys?include=user')
            ->assertOk()
            ->assertJsonPath('data.0.user.name', 'Ada Lovelace');
    });
});

describe('store', function () {
    afterEach(function (): void {
        Str::createRandomStringsNormally();
    });

    it('issues an active API key and returns the plaintext token once', function () {
        Str::createRandomStringsUsing(fn (): string => 'abcdefghijklmnopqrstuvwxyz0123456789abcd');

        $user = User::factory()->create(['name' => 'Ada Lovelace']);

        $this->postJson('/api/landlord/api-keys', [
            'user_id' => $user->id,
            'name' => 'CI deploy',
            'expires_at' => '2027-08-29T20:00:00Z',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.name', 'CI deploy')
            ->assertJsonPath('data.prefix', 'mrc_abcdefgh')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.token', 'mrc_abcdefghijklmnopqrstuvwxyz0123456789abcd')
            ->assertJsonPath('data.expires_at', '2027-08-29T20:00:00.000000Z')
            ->assertJsonPath('data.user.name', 'Ada Lovelace')
            ->assertJsonMissingPath('data.key_hash');

        $this->assertDatabaseHas('api_keys', [
            'user_id' => $user->id,
            'name' => 'CI deploy',
            'prefix' => 'mrc_abcdefgh',
            'key_hash' => hash('sha256', 'mrc_abcdefghijklmnopqrstuvwxyz0123456789abcd'),
            'status' => ApiKeyStatus::Active->value,
        ]);
    });

    it('does not persist a client-supplied token or status', function () {
        $user = User::factory()->create();

        $this->postJson('/api/landlord/api-keys', [
            'user_id' => $user->id,
            'name' => 'CI deploy',
            'token' => 'injected-token',
            'status' => 'revoked',
            'key_hash' => 'injected-hash',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonMissing(['data' => ['token' => 'injected-token']]);

        $this->assertDatabaseMissing('api_keys', [
            'key_hash' => 'injected-hash',
        ]);
    });

    it('returns 422 when required API key fields are missing', function () {
        $this->postJson('/api/landlord/api-keys', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id', 'name']);
    });

    it('returns 422 when the user does not exist', function () {
        $this->postJson('/api/landlord/api-keys', [
            'user_id' => 999,
            'name' => 'CI deploy',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    });

    it('returns 422 when API key creation is disabled', function () {
        app(SettingService::class)->updateDomain('api', [
            'api.keys_enabled' => false,
        ]);

        $user = User::factory()->create();

        $this->postJson('/api/landlord/api-keys', [
            'user_id' => $user->id,
            'name' => 'CI deploy',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('returns 422 when the user is at the API key limit', function () {
        app(SettingService::class)->updateDomain('api', [
            'api.max_keys_per_user' => 1,
        ]);

        $user = User::factory()->create();
        ApiKey::factory()->for($user)->create();

        $this->postJson('/api/landlord/api-keys', [
            'user_id' => $user->id,
            'name' => 'Second key',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    });

    it('applies the default TTL when expires_at is omitted', function () {
        $this->travelTo(now()->parse('2026-09-02 12:00:00'));

        app(SettingService::class)->updateDomain('api', [
            'api.default_key_ttl_days' => 30,
        ]);

        $user = User::factory()->create();

        $this->postJson('/api/landlord/api-keys', [
            'user_id' => $user->id,
            'name' => 'TTL key',
        ])
            ->assertCreated()
            ->assertJsonPath('data.expires_at', '2026-10-02T12:00:00.000000Z');
    });
});

describe('show', function () {
    it('returns a single API key without the plaintext token', function () {
        $apiKey = ApiKey::factory()->create(['name' => 'CI deploy']);

        $this->getJson("/api/landlord/api-keys/{$apiKey->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $apiKey->id)
            ->assertJsonPath('data.name', 'CI deploy')
            ->assertJsonMissingPath('data.token')
            ->assertJsonMissingPath('data.key_hash')
            ->assertJsonMissingPath('data.user');
    });

    it('returns 404 when the API key does not exist', function () {
        $this->getJson('/api/landlord/api-keys/999')
            ->assertNotFound();
    });
});

describe('update', function () {
    it('updates the name on an active API key', function () {
        $apiKey = ApiKey::factory()->create(['name' => 'CI deploy']);

        $this->putJson("/api/landlord/api-keys/{$apiKey->id}", [
            'name' => 'Staging deploy',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Staging deploy')
            ->assertJsonMissingPath('data.token');

        $this->assertDatabaseHas('api_keys', [
            'id' => $apiKey->id,
            'name' => 'Staging deploy',
        ]);
    });

    it('returns 422 when updating a revoked API key', function () {
        $apiKey = ApiKey::factory()->revoked()->create();

        $this->putJson("/api/landlord/api-keys/{$apiKey->id}", [
            'name' => 'Too late',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    });
});

describe('revoke', function () {
    it('revokes an active API key', function () {
        $this->travelTo('2026-08-29 20:00:00');

        $apiKey = ApiKey::factory()->create();

        $this->postJson("/api/landlord/api-keys/{$apiKey->id}/revoke")
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked')
            ->assertJsonPath('data.revoked_at', '2026-08-29T20:00:00.000000Z');

        $this->assertDatabaseHas('api_keys', [
            'id' => $apiKey->id,
            'status' => ApiKeyStatus::Revoked->value,
        ]);
    });

    it('returns 422 when the API key is already revoked', function () {
        $apiKey = ApiKey::factory()->revoked()->create();

        $this->postJson("/api/landlord/api-keys/{$apiKey->id}/revoke")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    });
});

describe('destroy', function () {
    it('soft deletes an API key', function () {
        $apiKey = ApiKey::factory()->create();

        $this->deleteJson("/api/landlord/api-keys/{$apiKey->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($apiKey);
    });

    it('returns 404 when showing a soft-deleted API key', function () {
        $apiKey = ApiKey::factory()->create();
        $apiKey->delete();

        $this->getJson("/api/landlord/api-keys/{$apiKey->id}")
            ->assertNotFound();
    });
});

describe('restore', function () {
    it('restores a soft-deleted API key', function () {
        $apiKey = ApiKey::factory()->create(['name' => 'CI deploy']);
        $apiKey->delete();

        $this->postJson("/api/landlord/api-keys/{$apiKey->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.name', 'CI deploy')
            ->assertJsonMissingPath('data.token');

        $this->assertNotSoftDeleted($apiKey);
    });

    it('returns 404 when the API key is not soft deleted', function () {
        $apiKey = ApiKey::factory()->create();

        $this->postJson("/api/landlord/api-keys/{$apiKey->id}/restore")
            ->assertNotFound();
    });
});

describe('destroyMany', function () {
    it('soft deletes the given API keys', function () {
        $first = ApiKey::factory()->create();
        $second = ApiKey::factory()->create();

        $this->deleteJson('/api/landlord/api-keys/destroy-many', [
            'ids' => [$first->id, $second->id],
        ])->assertNoContent();

        $this->assertSoftDeleted($first);
        $this->assertSoftDeleted($second);
    });

    it('returns 422 when ids are missing', function () {
        $this->deleteJson('/api/landlord/api-keys/destroy-many', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids']);
    });
});

describe('restoreMany', function () {
    it('restores the given soft-deleted API keys', function () {
        $first = ApiKey::factory()->create();
        $second = ApiKey::factory()->create();
        $first->delete();
        $second->delete();

        $this->postJson('/api/landlord/api-keys/restore-many', [
            'ids' => [$first->id, $second->id],
        ])->assertNoContent();

        $this->assertNotSoftDeleted($first);
        $this->assertNotSoftDeleted($second);
    });

    it('returns 422 when an id is not soft deleted', function () {
        $apiKey = ApiKey::factory()->create();

        $this->postJson('/api/landlord/api-keys/restore-many', [
            'ids' => [$apiKey->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids.0']);
    });
});
