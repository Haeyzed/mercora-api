<?php

use App\Exports\Shared\World\LanguagesExport;
use App\Imports\Shared\World\LanguagesImport;
use App\Models\Shared\Language;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

uses(LazilyRefreshDatabase::class);

describe('index', function () {
    it('filters languages by a partial name', function () {
        Language::factory()->create([
            'code' => 'en',
            'name' => 'English',
            'name_native' => 'English',
            'dir' => 'ltr',
        ]);
        Language::factory()->create([
            'code' => 'yo',
            'name' => 'Yoruba',
            'name_native' => 'Yorùbá',
            'dir' => 'ltr',
        ]);

        $this->getJson('/api/landlord/world/languages?filter[name]=Yor')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'yo');
    });

    it('searches languages across name and code', function () {
        Language::factory()->create([
            'code' => 'en',
            'name' => 'English',
            'name_native' => 'English',
            'dir' => 'ltr',
        ]);
        Language::factory()->create([
            'code' => 'yo',
            'name' => 'Yoruba',
            'name_native' => 'Yorùbá',
            'dir' => 'ltr',
        ]);

        $this->getJson('/api/landlord/world/languages?search=yo')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'yo');
    });
});

describe('options', function () {
    it('returns language options as label and value pairs', function () {
        $language = Language::factory()->create([
            'code' => 'en',
            'name' => 'English',
            'name_native' => 'English',
            'dir' => 'ltr',
        ]);

        $this->getJson('/api/landlord/world/languages/options')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'English')
            ->assertJsonPath('data.0.value', $language->id);
    });
});

describe('store', function () {
    it('creates a language', function () {
        $this->postJson('/api/landlord/world/languages', [
            'code' => 'en',
            'name' => 'English',
            'name_native' => 'English',
            'dir' => 'ltr',
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'en');
    });

    it('returns 422 when the language direction is invalid', function () {
        $this->postJson('/api/landlord/world/languages', [
            'code' => 'en',
            'name' => 'English',
            'name_native' => 'English',
            'dir' => 'sideways',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['dir']);
    });
});

describe('update', function () {
    it('updates a language', function () {
        $language = Language::factory()->create([
            'code' => 'en',
            'name' => 'English',
            'name_native' => 'English',
            'dir' => 'ltr',
        ]);

        $this->putJson("/api/landlord/world/languages/{$language->id}", [
            'name' => 'British English',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'British English');
    });
});

describe('destroy', function () {
    it('deletes a language', function () {
        $language = Language::factory()->create([
            'code' => 'en',
            'name' => 'English',
            'name_native' => 'English',
            'dir' => 'ltr',
        ]);

        $this->deleteJson("/api/landlord/world/languages/{$language->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($language);
    });
});

describe('restore', function () {
    it('restores a soft-deleted language', function () {
        $language = Language::factory()->create([
            'code' => 'en',
            'name' => 'English',
            'name_native' => 'English',
            'dir' => 'ltr',
        ]);
        $language->delete();

        $this->postJson("/api/landlord/world/languages/{$language->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.name', 'English');

        $this->assertNotSoftDeleted($language);
    });
});

describe('destroyMany', function () {
    it('soft deletes the given languages', function () {
        $language = Language::factory()->create([
            'code' => 'en',
            'name' => 'English',
            'name_native' => 'English',
            'dir' => 'ltr',
        ]);

        $this->deleteJson('/api/landlord/world/languages/destroy-many', [
            'ids' => [$language->id],
        ])->assertNoContent();

        $this->assertSoftDeleted($language);
    });
});

describe('restoreMany', function () {
    it('restores the given soft-deleted languages', function () {
        $language = Language::factory()->create([
            'code' => 'en',
            'name' => 'English',
            'name_native' => 'English',
            'dir' => 'ltr',
        ]);
        $language->delete();

        $this->postJson('/api/landlord/world/languages/restore-many', [
            'ids' => [$language->id],
        ])->assertNoContent();

        $this->assertNotSoftDeleted($language);
    });
});

describe('import', function () {
    it('imports a spreadsheet of languages', function () {
        Excel::fake();

        $this->post('/api/landlord/world/languages/import', [
            'file' => UploadedFile::fake()->create(
                'languages.xlsx',
                10,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ),
        ], [
            'Accept' => 'application/json',
        ])->assertNoContent();

        Excel::assertImported('languages.xlsx', fn (LanguagesImport $import): bool => true);
    });
});

describe('template', function () {
    it('downloads a language import template', function () {
        Excel::fake();

        $this->get('/api/landlord/world/languages/template')->assertOk();

        Excel::assertDownloaded('languages-template.xlsx');
    });
});

describe('export', function () {
    it('downloads a languages spreadsheet', function () {
        Excel::fake();

        $this->get('/api/landlord/world/languages/export')->assertOk();

        Excel::assertDownloaded('languages.xlsx', fn (LanguagesExport $export): bool => true);
    });
});
