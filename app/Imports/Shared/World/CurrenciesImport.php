<?php

declare(strict_types=1);

namespace App\Imports\Shared\World;

use App\Models\Shared\Country;
use App\Models\Shared\Currency;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class CurrenciesImport implements ToModel, WithBatchInserts, WithChunkReading, WithHeadingRow, WithValidation
{
    /**
     * @param  array<string, mixed>  $row
     */
    public function model(array $row): Currency
    {
        return new Currency([
            'country_id' => $row['country_id'],
            'name' => $row['name'],
            'code' => $row['code'],
            'precision' => $row['precision'] ?? 2,
            'symbol' => $row['symbol'],
            'symbol_native' => $row['symbol_native'],
            'symbol_first' => $row['symbol_first'] ?? true,
            'decimal_mark' => $row['decimal_mark'] ?? '.',
            'thousands_separator' => $row['thousands_separator'] ?? ',',
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
            '*.code' => ['required', 'string', 'max:10'],
            '*.precision' => ['sometimes', 'integer'],
            '*.symbol' => ['required', 'string', 'max:255'],
            '*.symbol_native' => ['required', 'string', 'max:255'],
            '*.symbol_first' => ['sometimes', 'boolean'],
            '*.decimal_mark' => ['sometimes', 'string', 'size:1'],
            '*.thousands_separator' => ['sometimes', 'string', 'size:1'],
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
