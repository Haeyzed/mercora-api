<?php

use App\Exports\Shared\World\StatesExport;
use App\Imports\Shared\World\StatesImport;
use App\Models\Shared\Country;
use App\Models\Shared\State;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

uses(LazilyRefreshDatabase::class);

describe('index', function () {
    it('filters states by country_id', function () {
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
        State::factory()->forCountry($nigeria)->create([
            'name' => 'Lagos',
            'state_code' => 'LA',
        ]);
        State::factory()->forCountry($ghana)->create([
            'name' => 'Accra',
            'state_code' => 'AA',
        ]);

        $this->getJson("/api/landlord/world/states?filter[country_id]={$nigeria->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Lagos');
    });

    it('filters states by country_code', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        State::factory()->forCountry($nigeria)->create([
            'name' => 'Lagos',
            'state_code' => 'LA',
        ]);

        $this->getJson('/api/landlord/world/states?filter[country_code]=NG')
            ->assertOk()
            ->assertJsonPath('data.0.country_code', 'NG');
    });

    it('searches states across name and codes', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        State::factory()->forCountry($nigeria)->create([
            'name' => 'Lagos',
            'state_code' => 'LA',
        ]);
        State::factory()->forCountry($nigeria)->create([
            'name' => 'Rivers',
            'state_code' => 'RI',
        ]);

        $this->getJson('/api/landlord/world/states?search=LA')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Lagos');
    });
});

describe('options', function () {
    it('returns state options as label and value pairs', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $state = State::factory()->forCountry($nigeria)->create([
            'name' => 'Lagos',
            'state_code' => 'LA',
        ]);

        $this->getJson("/api/landlord/world/states/options?filter[country_id]={$nigeria->id}")
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Lagos')
            ->assertJsonPath('data.0.value', $state->id);
    });
});

describe('store', function () {
    it('creates a state and fills country_code from the country', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);

        $this->postJson('/api/landlord/world/states', [
            'country_id' => $nigeria->id,
            'name' => 'Lagos',
            'state_code' => 'LA',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Lagos')
            ->assertJsonPath('data.country_code', 'NG');
    });

    it('returns 422 when the country_id is missing', function () {
        $this->postJson('/api/landlord/world/states', [
            'name' => 'Lagos',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['country_id']);
    });
});

describe('update', function () {
    it('updates a state', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $state = State::factory()->forCountry($nigeria)->create([
            'name' => 'Lagos',
            'state_code' => 'LA',
        ]);

        $this->putJson("/api/landlord/world/states/{$state->id}", [
            'name' => 'Lagos State',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Lagos State');
    });
});

describe('destroy', function () {
    it('deletes a state', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $state = State::factory()->forCountry($nigeria)->create([
            'name' => 'Lagos',
            'state_code' => 'LA',
        ]);

        $this->deleteJson("/api/landlord/world/states/{$state->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($state);
    });
});

describe('restore', function () {
    it('restores a soft-deleted state', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $state = State::factory()->forCountry($nigeria)->create([
            'name' => 'Lagos',
            'state_code' => 'LA',
        ]);
        $state->delete();

        $this->postJson("/api/landlord/world/states/{$state->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.name', 'Lagos');

        $this->assertNotSoftDeleted($state);
    });
});

describe('destroyMany', function () {
    it('soft deletes the given states', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $lagos = State::factory()->forCountry($nigeria)->create([
            'name' => 'Lagos',
            'state_code' => 'LA',
        ]);
        $abuja = State::factory()->forCountry($nigeria)->create([
            'name' => 'Abuja',
            'state_code' => 'FC',
        ]);

        $this->deleteJson('/api/landlord/world/states/destroy-many', [
            'ids' => [$lagos->id, $abuja->id],
        ])->assertNoContent();

        $this->assertSoftDeleted($lagos);
        $this->assertSoftDeleted($abuja);
    });
});

describe('restoreMany', function () {
    it('restores the given soft-deleted states', function () {
        $nigeria = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $state = State::factory()->forCountry($nigeria)->create([
            'name' => 'Lagos',
            'state_code' => 'LA',
        ]);
        $state->delete();

        $this->postJson('/api/landlord/world/states/restore-many', [
            'ids' => [$state->id],
        ])->assertNoContent();

        $this->assertNotSoftDeleted($state);
    });
});

describe('import', function () {
    it('imports a spreadsheet of states', function () {
        Excel::fake();

        $this->post('/api/landlord/world/states/import', [
            'file' => UploadedFile::fake()->create(
                'states.xlsx',
                10,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
        ], [
            'Accept' => 'application/json',
        ])->assertNoContent();

        Excel::assertImported('states.xlsx', fn (StatesImport $import): bool => true);
    });
});

describe('template', function () {
    it('downloads a state import template', function () {
        Excel::fake();

        $this->get('/api/landlord/world/states/template')->assertOk();

        Excel::assertDownloaded('states-template.xlsx');
    });
});

describe('export', function () {
    it('downloads a states spreadsheet', function () {
        Excel::fake();

        $this->get('/api/landlord/world/states/export')->assertOk();

        Excel::assertDownloaded('states.xlsx', fn (StatesExport $export): bool => true);
    });
});
