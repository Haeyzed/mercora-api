<?php

declare(strict_types=1);

namespace App\Imports\Shared\World;

use App\Models\Shared\Country;
use App\Models\Shared\State;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class StatesImport implements ToModel, WithBatchInserts, WithChunkReading, WithHeadingRow, WithValidation
{
    /**
     * @param  array<string, mixed>  $row
     */
    public function model(array $row): State
    {
        $countryId = (int) $row['country_id'];

        return new State([
            'country_id' => $countryId,
            'name' => $row['name'],
            'country_code' => $row['country_code'] ?? Country::query()->whereKey($countryId)->value('iso2'),
            'state_code' => $row['state_code'] ?? null,
            'type' => $row['type'] ?? null,
            'latitude' => $row['latitude'] ?? null,
            'longitude' => $row['longitude'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            '*.country_id' => ['required', 'integer', Rule::exists(Country::class, 'id')],
            '*.name' => ['required', 'string', 'max:255'],
            '*.country_code' => ['sometimes', 'nullable', 'string', 'max:3'],
            '*.state_code' => ['sometimes', 'nullable', 'string', 'max:5'],
            '*.type' => ['sometimes', 'nullable', 'string', 'max:255'],
            '*.latitude' => ['sometimes', 'nullable', 'string', 'max:255'],
            '*.longitude' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function batchSize(): int
    {
        return 1000;
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
