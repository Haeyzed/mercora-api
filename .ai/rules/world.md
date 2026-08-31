---
paths:
  - 'app/Services/Shared/World/**'
---

# World

## World data is shared landlord reference data
Laravel World (nnjeim/world) lives in the landlord/central database only. Put World services under App\Services\Shared\World\{Entity}, extend package models in App\Models\Shared, and expose read-only endpoints at /api/landlord/world/*. Do not duplicate World tables in tenant databases or replace this reference Currency with a future tenant payment currency model.

## World services stay flat under Shared/World
Keep one service file per entity directly in App\Services\Shared\World (CountryService, StateService, etc.). Controllers live in App\Http\Controllers\Shared\World. Do not add a WorldQuery helper; query with Eloquent in each service. Options returns {label, value} pairs.

## World CRUD includes restore, destroyMany, import, and export
World endpoints are full CRUD plus options, restore, destroyMany, restoreMany, import, and export. Use those Laravel names (not deleteMany or bulkRestore). Destroy is a soft delete; restore routes use withTrashed() and 404 unless the record is trashed. Spreadsheet work lives in App\Exports\Shared\World and App\Imports\Shared\World (FromQuery + ToModel with chunked batch inserts). Export uses the same filter/search as index. Import validates file as xlsx/xls/csv. Static paths (options, export, import, destroy-many, restore-many) register before apiResource.

## World import templates are heading-only downloads
World also has template(): GET /{resource}/template downloads an empty xlsx whose headings match that entity's import/export columns. Register it with the other static paths before apiResource. Filename is {resource}-template.xlsx.
