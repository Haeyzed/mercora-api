<?php

declare(strict_types=1);

namespace App\Imports\Shared\World;

use App\Models\Shared\Country;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class CountriesImport implements ToModel, WithBatchInserts, WithChunkReading, WithHeadingRow, WithValidation
{
    /**
     * @param  array<string, mixed>  $row
     */
    public function model(array $row): Country
    {
        return new Country([
            'iso2' => $row['iso2'],
            'name' => $row['name'],
            'status' => $row['status'] ?? 1,
            'phone_code' => $row['phone_code'],
            'iso3' => $row['iso3'],
            'native' => $row['native'],
            'region' => $row['region'],
            'subregion' => $row['subregion'],
            'latitude' => $row['latitude'],
            'longitude' => $row['longitude'],
            'emoji' => $row['emoji'],
            'emojiU' => $row['emoji_u'] ?? $row['emojiu'] ?? $row['emojiU'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            '*.iso2' => ['required', 'string', 'size:2'],
            '*.name' => ['required', 'string', 'max:255'],
            '*.status' => ['sometimes', 'integer'],
            '*.phone_code' => ['required', 'string', 'max:5'],
            '*.iso3' => ['required', 'string', 'size:3'],
            '*.native' => ['required', 'string', 'max:255'],
            '*.region' => ['required', 'string', 'max:255'],
            '*.subregion' => ['required', 'string', 'max:255'],
            '*.latitude' => ['required', 'string', 'max:255'],
            '*.longitude' => ['required', 'string', 'max:255'],
            '*.emoji' => ['required', 'string', 'max:255'],
            '*.emoji_u' => ['required', 'string', 'max:255'],
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
