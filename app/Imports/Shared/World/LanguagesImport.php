<?php

declare(strict_types=1);

namespace App\Imports\Shared\World;

use App\Models\Shared\Language;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class LanguagesImport implements ToModel, WithBatchInserts, WithChunkReading, WithHeadingRow, WithValidation
{
    /**
     * @param  array<string, mixed>  $row
     */
    public function model(array $row): Language
    {
        return new Language([
            'code' => $row['code'],
            'name' => $row['name'],
            'name_native' => $row['name_native'],
            'dir' => $row['dir'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            '*.code' => ['required', 'string', 'size:2'],
            '*.name' => ['required', 'string', 'max:255'],
            '*.name_native' => ['required', 'string', 'max:255'],
            '*.dir' => ['required', 'string', Rule::in(['ltr', 'rtl'])],
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
