<?php

use App\Exports\Shared\World\CountriesExport;
use App\Exports\Shared\World\WorldTemplateExport;
use App\Imports\Shared\World\CountriesImport;
use App\Models\Shared\Country;
use App\Models\Shared\State;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

uses(LazilyRefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function countryPayload(array $overrides = []): array
{
    return [
        'iso2' => 'NG',
        'name' => 'Nigeria',
        'status' => 1,
        'phone_code' => '234',
        'iso3' => 'NGA',
        'native' => 'Nigeria',
        'region' => 'Africa',
        'subregion' => 'Western Africa',
        'latitude' => '9.08200000',
        'longitude' => '8.67530000',
        'emoji' => '🇳🇬',
        'emojiU' => 'U+1F1F3 U+1F1EC',
        ...$overrides,
    ];
}

describe('index', function () {
    it('returns a paginated list of countries', function () {
        Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        Country::factory()->create([
            'name' => 'Ghana',
            'iso2' => 'GH',
            'iso3' => 'GHA',
        ]);

        $this->getJson('/api/landlord/world/countries')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'name', 'iso2', 'iso3', 'status'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);
    });

    it('paginates countries using the per_page query parameter', function () {
        Country::factory()->count(3)->create();

        $this->getJson('/api/landlord/world/countries?per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonCount(2, 'data');
    });

    it('filters countries by a partial name', function () {
        Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        Country::factory()->create([
            'name' => 'Ghana',
            'iso2' => 'GH',
            'iso3' => 'GHA',
        ]);

        $this->getJson('/api/landlord/world/countries?filter[name]=iger')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Nigeria');
    });

    it('searches countries across name and iso codes', function (string $term) {
        Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
            'native' => 'Nigeria',
            'phone_code' => '234',
        ]);
        Country::factory()->create([
            'name' => 'Ghana',
            'iso2' => 'GH',
            'iso3' => 'GHA',
            'native' => 'Ghana',
            'phone_code' => '233',
        ]);

        $this->getJson('/api/landlord/world/countries?search='.$term)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Nigeria');
    })->with([
        'name' => 'Nigeria',
        'iso2' => 'NG',
    ]);

    it('returns all countries when search is blank', function () {
        Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        Country::factory()->create([
            'name' => 'Ghana',
            'iso2' => 'GH',
            'iso3' => 'GHA',
        ]);

        $this->getJson('/api/landlord/world/countries?search=')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    });

    it('ignores unknown filters instead of querying arbitrary columns', function () {
        Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);

        $this->getJson('/api/landlord/world/countries?filter[password]=secret')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    });
});

describe('options', function () {
    it('returns country options as label and value pairs', function () {
        $country = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);

        $this->getJson('/api/landlord/world/countries/options')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Nigeria')
            ->assertJsonPath('data.0.value', $country->id);
    });

    it('searches country options by a single term', function () {
        Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
            'native' => 'Nigeria',
            'phone_code' => '234',
        ]);
        Country::factory()->create([
            'name' => 'Ghana',
            'iso2' => 'GH',
            'iso3' => 'GHA',
            'native' => 'Ghana',
            'phone_code' => '233',
        ]);

        $this->getJson('/api/landlord/world/countries/options?search=NG')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.label', 'Nigeria');
    });
});

describe('store', function () {
    it('creates a country', function () {
        $this->postJson('/api/landlord/world/countries', countryPayload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Nigeria')
            ->assertJsonPath('data.iso2', 'NG');

        $this->assertDatabaseHas('countries', [
            'name' => 'Nigeria',
            'iso2' => 'NG',
        ]);
    });

    it('returns 422 when required country fields are missing', function () {
        $this->postJson('/api/landlord/world/countries', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['iso2', 'name', 'iso3']);
    });
});

describe('show', function () {
    it('returns a single country', function () {
        $country = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);

        $this->getJson("/api/landlord/world/countries/{$country->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Nigeria')
            ->assertJsonMissingPath('data.data');
    });

    it('returns 404 when the country does not exist', function () {
        $this->getJson('/api/landlord/world/countries/999')
            ->assertNotFound();
    });

    it('includes states when requested', function () {
        $country = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        State::factory()->forCountry($country)->create([
            'name' => 'Lagos',
            'state_code' => 'LA',
        ]);

        $this->getJson("/api/landlord/world/countries/{$country->id}?include=states")
            ->assertOk()
            ->assertJsonPath('data.states.0.name', 'Lagos');
    });
});

describe('update', function () {
    it('updates a country', function () {
        $country = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);

        $this->putJson("/api/landlord/world/countries/{$country->id}", [
            'name' => 'Federal Republic of Nigeria',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Federal Republic of Nigeria');

        $this->assertDatabaseHas('countries', [
            'id' => $country->id,
            'name' => 'Federal Republic of Nigeria',
        ]);
    });
});

describe('destroy', function () {
    it('deletes a country', function () {
        $country = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);

        $this->deleteJson("/api/landlord/world/countries/{$country->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($country);
    });

    it('returns 404 when showing a soft-deleted country', function () {
        $country = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $country->delete();

        $this->getJson("/api/landlord/world/countries/{$country->id}")
            ->assertNotFound();
    });
});

describe('restore', function () {
    it('restores a soft-deleted country', function () {
        $country = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);
        $country->delete();

        $this->postJson("/api/landlord/world/countries/{$country->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.name', 'Nigeria');

        $this->assertNotSoftDeleted($country);
    });

    it('returns 404 when the country is not soft deleted', function () {
        $country = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);

        $this->postJson("/api/landlord/world/countries/{$country->id}/restore")
            ->assertNotFound();
    });

    it('returns 404 when the country does not exist', function () {
        $this->postJson('/api/landlord/world/countries/999/restore')
            ->assertNotFound();
    });
});

describe('destroyMany', function () {
    it('soft deletes the given countries', function () {
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

        $this->deleteJson('/api/landlord/world/countries/destroy-many', [
            'ids' => [$nigeria->id, $ghana->id],
        ])->assertNoContent();

        $this->assertSoftDeleted($nigeria);
        $this->assertSoftDeleted($ghana);
    });

    it('returns 422 when ids are missing', function () {
        $this->deleteJson('/api/landlord/world/countries/destroy-many', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids']);
    });

    it('returns 422 when an id does not exist', function () {
        $this->deleteJson('/api/landlord/world/countries/destroy-many', [
            'ids' => [999],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids.0']);
    });
});

describe('restoreMany', function () {
    it('restores the given soft-deleted countries', function () {
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
        $nigeria->delete();
        $ghana->delete();

        $this->postJson('/api/landlord/world/countries/restore-many', [
            'ids' => [$nigeria->id, $ghana->id],
        ])->assertNoContent();

        $this->assertNotSoftDeleted($nigeria);
        $this->assertNotSoftDeleted($ghana);
    });

    it('returns 422 when an id is not soft deleted', function () {
        $country = Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);

        $this->postJson('/api/landlord/world/countries/restore-many', [
            'ids' => [$country->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids.0']);
    });
});

describe('import', function () {
    it('imports a spreadsheet of countries', function () {
        Excel::fake();

        $this->post('/api/landlord/world/countries/import', [
            'file' => UploadedFile::fake()->create(
                'countries.xlsx',
                10,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
        ], [
            'Accept' => 'application/json',
        ])->assertNoContent();

        Excel::assertImported('countries.xlsx', fn (CountriesImport $import): bool => true);
    });

    it('returns 422 when the file is missing', function () {
        $this->postJson('/api/landlord/world/countries/import', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    });
});

describe('template', function () {
    it('downloads a country import template with the import headings', function () {
        Excel::fake();

        $this->get('/api/landlord/world/countries/template')->assertOk();

        Excel::assertDownloaded('countries-template.xlsx', function (WorldTemplateExport $export): bool {
            return $export->headings() === CountriesExport::columns()
                && $export->array() === [];
        });
    });
});

describe('export', function () {
    it('downloads a countries spreadsheet', function () {
        Excel::fake();

        Country::factory()->create([
            'name' => 'Nigeria',
            'iso2' => 'NG',
            'iso3' => 'NGA',
        ]);

        $this->get('/api/landlord/world/countries/export')->assertOk();

        Excel::assertDownloaded('countries.xlsx', fn (CountriesExport $export): bool => true);
    });
});
