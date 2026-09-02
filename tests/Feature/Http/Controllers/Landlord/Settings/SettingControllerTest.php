<?php

use App\Enums\Landlord\SettingType;
use App\Models\Landlord\Setting;
use App\Services\Landlord\Settings\SettingService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(LazilyRefreshDatabase::class);

describe('index', function () {
    it('lists all settings domains with defaults', function () {
        $response = $this->getJson('/api/landlord/settings')->assertOk()->assertJsonCount(7, 'data');

        $byDomain = collect($response->json('data'))->keyBy('domain');

        expect($byDomain->keys()->all())->toEqualCanonicalizing([
            'platform',
            'registration',
            'localization',
            'billing',
            'mail',
            'security',
            'tenancy',
        ])
            ->and($byDomain['platform']['settings']['platform.name'])->toBe('Mercora')
            ->and($byDomain['registration']['settings']['registration.tenant_registration_enabled'])->toBeTrue()
            ->and($byDomain['localization']['settings']['localization.default_currency'])->toBe('USD')
            ->and($byDomain['billing']['settings']['billing.invoice_prefix'])->toBe('INV')
            ->and($byDomain['mail']['settings']['mail.from_name'])->toBe('Mercora')
            ->and($byDomain['security']['settings']['security.password_min_length'])->toBe(8)
            ->and($byDomain['tenancy']['settings']['tenancy.allow_custom_domains'])->toBeTrue();
    });
});

describe('show', function () {
    it('returns a domain with schema defaults when unset', function () {
        $settings = $this->getJson('/api/landlord/settings/platform')
            ->assertOk()
            ->assertJsonPath('data.domain', 'platform')
            ->json('data.settings');

        expect($settings['platform.name'])->toBe('Mercora')
            ->and($settings['platform.maintenance_mode'])->toBeFalse()
            ->and($settings['platform.support_email'])->toBeNull();
    });

    it('returns persisted values over defaults', function () {
        Setting::query()->create([
            'group' => 'platform',
            'key' => 'platform.name',
            'type' => SettingType::String,
            'value' => 'Acme Cloud',
        ]);

        $settings = $this->getJson('/api/landlord/settings/platform')
            ->assertOk()
            ->json('data.settings');

        expect($settings['platform.name'])->toBe('Acme Cloud');
    });

    it('returns 404 for an unknown domain', function () {
        $this->getJson('/api/landlord/settings/unknown')
            ->assertNotFound();
    });
});

describe('update', function () {
    it('updates allowlisted domain settings', function () {
        $settings = $this->putJson('/api/landlord/settings/platform', [
            'platform.name' => 'Mercora Cloud',
            'platform.maintenance_mode' => true,
            'platform.support_email' => 'support@mercora.test',
        ])
            ->assertOk()
            ->json('data.settings');

        expect($settings['platform.name'])->toBe('Mercora Cloud')
            ->and($settings['platform.maintenance_mode'])->toBeTrue()
            ->and($settings['platform.support_email'])->toBe('support@mercora.test');

        $this->assertDatabaseHas('settings', [
            'key' => 'platform.name',
            'group' => 'platform',
            'type' => SettingType::String->value,
            'value' => 'Mercora Cloud',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'platform.maintenance_mode',
            'value' => '1',
        ]);
    });

    it('updates allowlisted billing domain settings', function () {
        $settings = $this->putJson('/api/landlord/settings/billing', [
            'billing.invoice_prefix' => 'MRC',
            'billing.grace_days' => 7,
            'billing.tax_enabled' => true,
        ])
            ->assertOk()
            ->json('data.settings');

        expect($settings['billing.invoice_prefix'])->toBe('MRC')
            ->and($settings['billing.grace_days'])->toBe(7)
            ->and($settings['billing.tax_enabled'])->toBeTrue();
    });

    it('rejects unknown keys for the domain', function () {
        $this->putJson('/api/landlord/settings/platform', [
            'platform.name' => 'Ok',
            'platform.unknown' => 'nope',
        ])
            ->assertUnprocessable();
    });

    it('returns 422 when a boolean value is invalid', function () {
        $this->putJson('/api/landlord/settings/platform', [
            'platform.maintenance_mode' => 'yes',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['platform.maintenance_mode']);
    });

    it('returns 404 for an unknown domain', function () {
        $this->putJson('/api/landlord/settings/unknown', [
            'foo' => 'bar',
        ])->assertNotFound();
    });

    it('invalidates cached values after update', function () {
        Setting::query()->create([
            'group' => 'platform',
            'key' => 'platform.name',
            'type' => SettingType::String,
            'value' => 'Cached Name',
        ]);

        Cache::put('settings.platform.name', 'Cached Name', now()->addHour());

        $this->putJson('/api/landlord/settings/platform', [
            'platform.name' => 'Fresh Name',
        ])->assertOk();

        expect(Cache::get('settings.platform.name'))->toBeNull()
            ->and(app(SettingService::class)->value('platform.name'))
            ->toBe('Fresh Name');
    });
});
