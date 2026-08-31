<?php

declare(strict_types=1);

namespace App\Exports\Shared\World;

use App\Models\Shared\Language;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LanguagesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private Request $request) {}

    /**
     * @return Builder<Language>
     */
    public function query(): Builder
    {
        return Language::query()
            ->filter($this->request->input('filter', []))
            ->search($this->request->query('search'))
            ->ordered();
    }

    /**
     * @return list<string>
     */
    public static function columns(): array
    {
        return [
            'code',
            'name',
            'name_native',
            'dir',
        ];
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return self::columns();
    }

    /**
     * @param  Language  $language
     * @return list<mixed>
     */
    public function map($language): array
    {
        return [
            $language->code,
            $language->name,
            $language->name_native,
            $language->dir,
        ];
    }
}
