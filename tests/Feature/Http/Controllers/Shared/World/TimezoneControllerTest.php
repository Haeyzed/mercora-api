<?php

use App\Exports\Shared\World\TimezonesExport;
use App\Imports\Shared\World\TimezonesImport;
use App\Models\Shared\Country;
use App\Models\Shared\Timezone;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

uses(LazilyRefreshDatabase::class);

describe('index', function () {
    it('filters timezones by a partial name', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        Timezone::factory()->forCountry($nigeria)->create([
            'name' => 'Africa/Lagos',
        ]);
        Timezone::factory()->forCountry($nigeria)->create([
            'name' => 'Africa/Johannesburg',
        ]);

        $this->getJson('/api/landlord/world/timezones?filter[name]=Lagos')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Africa/Lagos');
    });

    it('searches timezones by a single term', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        Timezone::factory()->forCountry($nigeria)->create([
            'name' => 'Africa/Lagos',
        ]);
        Timezone::factory()->forCountry($nigeria)->create([
            'name' => 'Africa/Johannesburg',
        ]);

        $this->getJson('/api/landlord/world/timezones?search=Lagos')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Africa/Lagos');
    });
});

describe('options', function () {
    it('returns timezone options as label and value pairs', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $timezone = Timezone::factory()->forCountry($nigeria)->create([
            'name' => 'Africa/Lagos',
        ]);

        $this->getJson('/api/landlord/world/timezones/options')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Africa/Lagos')
            ->assertJsonPath('data.0.value', $timezone->id);
    });
});

describe('store', function () {
    it('creates a timezone', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);

        $this->postJson('/api/landlord/world/timezones', [
            'country_id' => $nigeria->id,
            'name' => 'Africa/Lagos',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Africa/Lagos');
    });
});

describe('update', function () {
    it('updates a timezone', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $timezone = Timezone::factory()->forCountry($nigeria)->create([
            'name' => 'Africa/Lagos',
        ]);

        $this->putJson("/api/landlord/world/timezones/{$timezone->id}", [
            'name' => 'Africa/Lagos/West',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Africa/Lagos/West');
    });
});

describe('destroy', function () {
    it('deletes a timezone', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $timezone = Timezone::factory()->forCountry($nigeria)->create([
            'name' => 'Africa/Lagos',
        ]);

        $this->deleteJson("/api/landlord/world/timezones/{$timezone->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($timezone);
    });
});

describe('restore', function () {
    it('restores a soft-deleted timezone', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $timezone = Timezone::factory()->forCountry($nigeria)->create([
            'name' => 'Africa/Lagos',
        ]);
        $timezone->delete();

        $this->postJson("/api/landlord/world/timezones/{$timezone->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.name', 'Africa/Lagos');

        $this->assertNotSoftDeleted($timezone);
    });
});

describe('destroyMany', function () {
    it('soft deletes the given timezones', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $timezone = Timezone::factory()->forCountry($nigeria)->create([
            'name' => 'Africa/Lagos',
        ]);

        $this->deleteJson('/api/landlord/world/timezones/destroy-many', [
            'ids' => [$timezone->id],
        ])->assertNoContent();

        $this->assertSoftDeleted($timezone);
    });
});

describe('restoreMany', function () {
    it('restores the given soft-deleted timezones', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $timezone = Timezone::factory()->forCountry($nigeria)->create([
            'name' => 'Africa/Lagos',
        ]);
        $timezone->delete();

        $this->postJson('/api/landlord/world/timezones/restore-many', [
            'ids' => [$timezone->id],
        ])->assertNoContent();

        $this->assertNotSoftDeleted($timezone);
    });
});

describe('import', function () {
    it('imports a spreadsheet of timezones', function () {
        Excel::fake();

        $this->post('/api/landlord/world/timezones/import', [
            'file' => UploadedFile::fake()->create(
                'timezones.xlsx',
                10,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
        ], [
            'Accept' => 'application/json',
        ])->assertNoContent();

        Excel::assertImported('timezones.xlsx', fn (TimezonesImport $import): bool => true);
    });
});

describe('template', function () {
    it('downloads a timezone import template', function () {
        Excel::fake();

        $this->get('/api/landlord/world/timezones/template')->assertOk();

        Excel::assertDownloaded('timezones-template.xlsx');
    });
});

describe('export', function () {
    it('downloads a timezones spreadsheet', function () {
        Excel::fake();

        $this->get('/api/landlord/world/timezones/export')->assertOk();

        Excel::assertDownloaded('timezones.xlsx', fn (TimezonesExport $export): bool => true);
    });
});
