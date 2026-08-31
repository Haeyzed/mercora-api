<?php

use App\Exports\Shared\World\CitiesExport;
use App\Imports\Shared\World\CitiesImport;
use App\Models\Shared\City;
use App\Models\Shared\Country;
use App\Models\Shared\State;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

uses(LazilyRefreshDatabase::class);

describe('index', function () {
    it('filters cities by state_id', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $lagos = State::factory()->forCountry($nigeria)->create([
            'name' => 'Lagos',
            'state_code' => 'LA',
        ]);
        $rivers = State::factory()->forCountry($nigeria)->create([
            'name' => 'Rivers',
            'state_code' => 'RI',
        ]);
        City::factory()->forState($lagos)->create(['name' => 'Ikeja']);
        City::factory()->forState($rivers)->create(['name' => 'Port Harcourt']);

        $this->getJson("/api/landlord/world/cities?filter[state_id]={$lagos->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Ikeja');
    });

    it('filters cities by country_code', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $lagos = State::factory()->forCountry($nigeria)->create([
            'name' => 'Lagos',
            'state_code' => 'LA',
        ]);
        City::factory()->forState($lagos)->create(['name' => 'Ikeja']);

        $this->getJson('/api/landlord/world/cities?filter[country_code]=NG')
            ->assertOk()
            ->assertJsonPath('data.0.country_code', 'NG');
    });

    it('searches cities across name and codes', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $lagos = State::factory()->forCountry($nigeria)->create([
            'name' => 'Lagos',
            'state_code' => 'LA',
        ]);
        City::factory()->forState($lagos)->create(['name' => 'Ikeja']);
        City::factory()->forState($lagos)->create(['name' => 'Lekki']);

        $this->getJson('/api/landlord/world/cities?search=Ike')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Ikeja');
    });
});

describe('options', function () {
    it('returns city options as label and value pairs', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $lagos = State::factory()->forCountry($nigeria)->create([
            'name' => 'Lagos',
            'state_code' => 'LA',
        ]);
        $city = City::factory()->forState($lagos)->create(['name' => 'Ikeja']);

        $this->getJson("/api/landlord/world/cities/options?filter[state_id]={$lagos->id}")
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Ikeja')
            ->assertJsonPath('data.0.value', $city->id);
    });
});

describe('store', function () {
    it('creates a city', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $lagos = State::factory()->forCountry($nigeria)->create([
            'name' => 'Lagos',
            'state_code' => 'LA',
        ]);

        $this->postJson('/api/landlord/world/cities', [
            'country_id' => $nigeria->id,
            'state_id' => $lagos->id,
            'name' => 'Ikeja',
            'latitude' => '6.6018',
            'longitude' => '3.3515',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Ikeja')
            ->assertJsonPath('data.country_code', 'NG')
            ->assertJsonPath('data.state_code', 'LA');
    });
});

describe('update', function () {
    it('updates a city', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $lagos = State::factory()->forCountry($nigeria)->create([
            'name' => 'Lagos',
            'state_code' => 'LA',
        ]);
        $city = City::factory()->forState($lagos)->create(['name' => 'Ikeja']);

        $this->putJson("/api/landlord/world/cities/{$city->id}", [
            'name' => 'Ikeja City',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Ikeja City');
    });
});

describe('destroy', function () {
    it('deletes a city', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $lagos = State::factory()->forCountry($nigeria)->create([
            'name' => 'Lagos',
            'state_code' => 'LA',
        ]);
        $city = City::factory()->forState($lagos)->create(['name' => 'Ikeja']);

        $this->deleteJson("/api/landlord/world/cities/{$city->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($city);
    });
});

describe('restore', function () {
    it('restores a soft-deleted city', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $lagos = State::factory()->forCountry($nigeria)->create([
            'name' => 'Lagos',
            'state_code' => 'LA',
        ]);
        $city = City::factory()->forState($lagos)->create(['name' => 'Ikeja']);
        $city->delete();

        $this->postJson("/api/landlord/world/cities/{$city->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.name', 'Ikeja');

        $this->assertNotSoftDeleted($city);
    });
});

describe('destroyMany', function () {
    it('soft deletes the given cities', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $lagos = State::factory()->forCountry($nigeria)->create([
            'name' => 'Lagos',
            'state_code' => 'LA',
        ]);
        $city = City::factory()->forState($lagos)->create(['name' => 'Ikeja']);

        $this->deleteJson('/api/landlord/world/cities/destroy-many', [
            'ids' => [$city->id],
        ])->assertNoContent();

        $this->assertSoftDeleted($city);
    });
});

describe('restoreMany', function () {
    it('restores the given soft-deleted cities', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $lagos = State::factory()->forCountry($nigeria)->create([
            'name' => 'Lagos',
            'state_code' => 'LA',
        ]);
        $city = City::factory()->forState($lagos)->create(['name' => 'Ikeja']);
        $city->delete();

        $this->postJson('/api/landlord/world/cities/restore-many', [
            'ids' => [$city->id],
        ])->assertNoContent();

        $this->assertNotSoftDeleted($city);
    });
});

describe('import', function () {
    it('imports a spreadsheet of cities', function () {
        Excel::fake();

        $this->post('/api/landlord/world/cities/import', [
            'file' => UploadedFile::fake()->create(
                'cities.xlsx',
                10,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
        ], [
            'Accept' => 'application/json',
        ])->assertNoContent();

        Excel::assertImported('cities.xlsx', fn (CitiesImport $import): bool => true);
    });
});

describe('template', function () {
    it('downloads a city import template', function () {
        Excel::fake();

        $this->get('/api/landlord/world/cities/template')->assertOk();

        Excel::assertDownloaded('cities-template.xlsx');
    });
});

describe('export', function () {
    it('downloads a cities spreadsheet', function () {
        Excel::fake();

        $this->get('/api/landlord/world/cities/export')->assertOk();

        Excel::assertDownloaded('cities.xlsx', fn (CitiesExport $export): bool => true);
    });
});
