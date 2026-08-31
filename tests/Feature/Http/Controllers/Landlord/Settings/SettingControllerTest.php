<?php

use App\Enums\Landlord\SettingType;
use App\Models\Landlord\Setting;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function settingPayload(array $overrides = []): array
{
    return [
        'group' => 'general',
        'key' => 'app.name',
        'type' => 'string',
        'value' => 'Mercora',
        'description' => 'Platform display name',
        ...$overrides,
    ];
}

describe('index', function () {
    it('returns a paginated list of settings', function () {
        Setting::factory()->create(['key' => 'app.name']);
        Setting::factory()->create(['key' => 'app.support_email']);

        $this->getJson('/api/landlord/settings')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'group', 'key', 'type', 'value', 'description'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);
    });

    it('paginates settings using the per_page query parameter', function () {
        Setting::factory()->count(3)->create();

        $this->getJson('/api/landlord/settings?per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonCount(2, 'data');
    });

    it('filters settings by group', function () {
        Setting::factory()->create(['group' => 'mail', 'key' => 'mail.from_address']);
        Setting::factory()->create(['group' => 'general', 'key' => 'app.name']);

        $this->getJson('/api/landlord/settings?filter[group]=mail')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.key', 'mail.from_address');
    });

    it('filters settings by type', function () {
        Setting::factory()->boolean()->create(['key' => 'app.registration_enabled']);
        Setting::factory()->create(['key' => 'app.name']);

        $this->getJson('/api/landlord/settings?filter[type]=boolean')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.key', 'app.registration_enabled')
            ->assertJsonPath('data.0.value', true);
    });

    it('searches settings by key', function () {
        Setting::factory()->create(['key' => 'app.name']);
        Setting::factory()->create(['key' => 'mail.from_address']);

        $this->getJson('/api/landlord/settings?search=from_address')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.key', 'mail.from_address');
    });

    it('returns all settings when search is blank', function () {
        Setting::factory()->count(2)->create();

        $this->getJson('/api/landlord/settings?search=')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    });

    it('ignores unknown filters instead of querying arbitrary columns', function () {
        Setting::factory()->create();

        $this->getJson('/api/landlord/settings?filter[password]=secret')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    });
});

describe('options', function () {
    it('returns setting options as label and value pairs', function () {
        $setting = Setting::factory()->create(['key' => 'app.name']);

        $this->getJson('/api/landlord/settings/options')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'app.name')
            ->assertJsonPath('data.0.value', $setting->id);
    });
});

describe('store', function () {
    it('creates a string setting', function () {
        $this->postJson('/api/landlord/settings', settingPayload())
            ->assertCreated()
            ->assertJsonPath('data.group', 'general')
            ->assertJsonPath('data.key', 'app.name')
            ->assertJsonPath('data.type', 'string')
            ->assertJsonPath('data.value', 'Mercora')
            ->assertJsonPath('data.description', 'Platform display name');

        $this->assertDatabaseHas('settings', [
            'key' => 'app.name',
            'type' => SettingType::String->value,
            'value' => 'Mercora',
        ]);
    });

    it('creates a boolean setting', function () {
        $this->postJson('/api/landlord/settings', settingPayload([
            'key' => 'app.registration_enabled',
            'type' => 'boolean',
            'value' => false,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.type', 'boolean')
            ->assertJsonPath('data.value', false);

        $this->assertDatabaseHas('settings', [
            'key' => 'app.registration_enabled',
            'value' => '0',
        ]);
    });

    it('creates an integer setting', function () {
        $this->postJson('/api/landlord/settings', settingPayload([
            'key' => 'billing.grace_days',
            'type' => 'integer',
            'value' => 14,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.type', 'integer')
            ->assertJsonPath('data.value', 14);
    });

    it('creates a json setting', function () {
        $this->postJson('/api/landlord/settings', settingPayload([
            'key' => 'app.locales',
            'type' => 'json',
            'value' => ['en', 'fr'],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.type', 'json')
            ->assertJsonPath('data.value.0', 'en')
            ->assertJsonPath('data.value.1', 'fr');
    });

    it('returns 422 when the key already exists', function () {
        Setting::factory()->create(['key' => 'app.name']);

        $this->postJson('/api/landlord/settings', settingPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['key']);
    });

    it('returns 422 when required setting fields are missing', function () {
        $this->postJson('/api/landlord/settings', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['group', 'key', 'type', 'value']);
    });

    it('returns 422 when the key format is invalid', function () {
        $this->postJson('/api/landlord/settings', settingPayload([
            'key' => 'App Name',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['key']);
    });

    it('returns 422 when the value does not match the type', function () {
        $this->postJson('/api/landlord/settings', settingPayload([
            'type' => 'boolean',
            'value' => 'yes',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['value']);
    });
});

describe('show', function () {
    it('returns a single setting', function () {
        $setting = Setting::factory()->create(['key' => 'app.name']);

        $this->getJson("/api/landlord/settings/{$setting->id}")
            ->assertOk()
            ->assertJsonPath('data.key', 'app.name')
            ->assertJsonPath('data.value', 'Mercora');
    });

    it('returns 404 when the setting does not exist', function () {
        $this->getJson('/api/landlord/settings/999')
            ->assertNotFound();
    });
});

describe('update', function () {
    it('updates a setting value and leaves the key unchanged', function () {
        $setting = Setting::factory()->create(['key' => 'app.name']);

        $this->putJson("/api/landlord/settings/{$setting->id}", [
            'value' => 'Mercora Cloud',
            'key' => 'injected.key',
        ])
            ->assertOk()
            ->assertJsonPath('data.key', 'app.name')
            ->assertJsonPath('data.value', 'Mercora Cloud');

        $this->assertDatabaseHas('settings', [
            'id' => $setting->id,
            'key' => 'app.name',
            'value' => 'Mercora Cloud',
        ]);
    });

    it('returns 422 when updating a boolean setting with a string value', function () {
        $setting = Setting::factory()->boolean()->create();

        $this->putJson("/api/landlord/settings/{$setting->id}", [
            'value' => 'yes',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['value']);
    });
});

describe('destroy', function () {
    it('soft deletes a setting', function () {
        $setting = Setting::factory()->create();

        $this->deleteJson("/api/landlord/settings/{$setting->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($setting);
    });

    it('returns 404 when showing a soft-deleted setting', function () {
        $setting = Setting::factory()->create();
        $setting->delete();

        $this->getJson("/api/landlord/settings/{$setting->id}")
            ->assertNotFound();
    });
});

describe('restore', function () {
    it('restores a soft-deleted setting', function () {
        $setting = Setting::factory()->create(['key' => 'app.name']);
        $setting->delete();

        $this->postJson("/api/landlord/settings/{$setting->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.key', 'app.name');

        $this->assertNotSoftDeleted($setting);
    });

    it('returns 404 when the setting is not soft deleted', function () {
        $setting = Setting::factory()->create();

        $this->postJson("/api/landlord/settings/{$setting->id}/restore")
            ->assertNotFound();
    });
});

describe('destroyMany', function () {
    it('soft deletes the given settings', function () {
        $first = Setting::factory()->create();
        $second = Setting::factory()->create();

        $this->deleteJson('/api/landlord/settings/destroy-many', [
            'ids' => [$first->id, $second->id],
        ])->assertNoContent();

        $this->assertSoftDeleted($first);
        $this->assertSoftDeleted($second);
    });

    it('returns 422 when ids are missing', function () {
        $this->deleteJson('/api/landlord/settings/destroy-many', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids']);
    });
});

describe('restoreMany', function () {
    it('restores the given soft-deleted settings', function () {
        $first = Setting::factory()->create();
        $second = Setting::factory()->create();
        $first->delete();
        $second->delete();

        $this->postJson('/api/landlord/settings/restore-many', [
            'ids' => [$first->id, $second->id],
        ])->assertNoContent();

        $this->assertNotSoftDeleted($first);
        $this->assertNotSoftDeleted($second);
    });

    it('returns 422 when an id is not soft deleted', function () {
        $setting = Setting::factory()->create();

        $this->postJson('/api/landlord/settings/restore-many', [
            'ids' => [$setting->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids.0']);
    });
});
