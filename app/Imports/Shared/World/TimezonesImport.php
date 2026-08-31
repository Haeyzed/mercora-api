<?php

declare(strict_types=1);

namespace App\Imports\Shared\World;

use App\Models\Shared\Country;
use App\Models\Shared\Timezone;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class TimezonesImport implements ToModel, WithBatchInserts, WithChunkReading, WithHeadingRow, WithValidation
{
    /**
     * @param  array<string, mixed>  $row
     */
    public function model(array $row): Timezone
    {
        return new Timezone([
            'country_id' => $row['country_id'],
            'name' => $row['name'],
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
