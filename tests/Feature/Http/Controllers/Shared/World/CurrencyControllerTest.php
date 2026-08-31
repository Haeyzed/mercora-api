<?php

use App\Exports\Shared\World\CurrenciesExport;
use App\Imports\Shared\World\CurrenciesImport;
use App\Models\Shared\Country;
use App\Models\Shared\Currency;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

uses(LazilyRefreshDatabase::class);

describe('index', function () {
    it('filters currencies by exact code', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        Currency::factory()->forCountry($nigeria)->create([
            'name' => 'Nigerian naira',
            'code' => 'NGN',
        ]);

        $this->getJson('/api/landlord/world/currencies?filter[code]=NGN')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'NGN');
    });

    it('searches currencies across name and code', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $ghana = Country::factory()->create([
            'name' => 'Ghana',
            'iso2' => 'GH',
            'iso3' => 'GHA',
        ]);
        Currency::factory()->forCountry($nigeria)->create([
            'name' => 'Nigerian naira',
            'code' => 'NGN',
        ]);
        Currency::factory()->forCountry($ghana)->create([
            'name' => 'Ghanaian cedi',
            'code' => 'GHS',
        ]);

        $this->getJson('/api/landlord/world/currencies?search=NGN')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'NGN');
    });
});

describe('options', function () {
    it('returns currency options as label and value pairs', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $currency = Currency::factory()->forCountry($nigeria)->create([
            'name' => 'Nigerian naira',
            'code' => 'NGN',
        ]);

        $this->getJson('/api/landlord/world/currencies/options')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'NGN — Nigerian naira')
            ->assertJsonPath('data.0.value', $currency->id);
    });
});

describe('store', function () {
    it('creates a world currency', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);

        $this->postJson('/api/landlord/world/currencies', [
            'country_id' => $nigeria->id,
            'name' => 'Nigerian naira',
            'code' => 'NGN',
            'symbol' => '₦',
            'symbol_native' => '₦',
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'NGN');
    });
});

describe('update', function () {
    it('updates a world currency', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $currency = Currency::factory()->forCountry($nigeria)->create([
            'name' => 'Nigerian naira',
            'code' => 'NGN',
        ]);

        $this->putJson("/api/landlord/world/currencies/{$currency->id}", [
            'name' => 'Naira',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Naira');
    });
});

describe('destroy', function () {
    it('deletes a world currency', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $currency = Currency::factory()->forCountry($nigeria)->create([
            'name' => 'Nigerian naira',
            'code' => 'NGN',
        ]);

        $this->deleteJson("/api/landlord/world/currencies/{$currency->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($currency);
    });
});

describe('restore', function () {
    it('restores a soft-deleted world currency', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $currency = Currency::factory()->forCountry($nigeria)->create([
            'name' => 'Nigerian naira',
            'code' => 'NGN',
        ]);
        $currency->delete();

        $this->postJson("/api/landlord/world/currencies/{$currency->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.code', 'NGN');

        $this->assertNotSoftDeleted($currency);
    });
});

describe('destroyMany', function () {
    it('soft deletes the given world currencies', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $currency = Currency::factory()->forCountry($nigeria)->create([
            'name' => 'Nigerian naira',
            'code' => 'NGN',
        ]);

        $this->deleteJson('/api/landlord/world/currencies/destroy-many', [
            'ids' => [$currency->id],
        ])->assertNoContent();

        $this->assertSoftDeleted($currency);
    });
});

describe('restoreMany', function () {
    it('restores the given soft-deleted world currencies', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $currency = Currency::factory()->forCountry($nigeria)->create([
            'name' => 'Nigerian naira',
            'code' => 'NGN',
        ]);
        $currency->delete();

        $this->postJson('/api/landlord/world/currencies/restore-many', [
            'ids' => [$currency->id],
        ])->assertNoContent();

        $this->assertNotSoftDeleted($currency);
    });
});

describe('import', function () {
    it('imports a spreadsheet of world currencies', function () {
        Excel::fake();

        $this->post('/api/landlord/world/currencies/import', [
            'file' => UploadedFile::fake()->create(
                'currencies.xlsx',
                10,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
        ], [
            'Accept' => 'application/json',
        ])->assertNoContent();

        Excel::assertImported('currencies.xlsx', fn (CurrenciesImport $import): bool => true);
    });
});

describe('template', function () {
    it('downloads a world currency import template', function () {
        Excel::fake();

        $this->get('/api/landlord/world/currencies/template')->assertOk();

        Excel::assertDownloaded('currencies-template.xlsx');
    });
});

describe('export', function () {
    it('downloads a world currencies spreadsheet', function () {
        Excel::fake();

        $this->get('/api/landlord/world/currencies/export')->assertOk();

        Excel::assertDownloaded('currencies.xlsx', fn (CurrenciesExport $export): bool => true);
    });
});
