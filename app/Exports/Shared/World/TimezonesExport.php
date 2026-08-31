<?php

declare(strict_types=1);

namespace App\Exports\Shared\World;

use App\Models\Shared\Timezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TimezonesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private Request $request) {}

    /**
     * @return Builder<Timezone>
     */
    public function query(): Builder
    {
        return Timezone::query()
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
            'country_id',
            'name',
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
     * @param  Timezone  $timezone
     * @return list<mixed>
     */
    public function map($timezone): array
    {
        return [
            $timezone->country_id,
            $timezone->name,
        ];
    }
}
