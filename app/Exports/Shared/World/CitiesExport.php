<?php

declare(strict_types=1);

namespace App\Exports\Shared\World;

use App\Models\Shared\City;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CitiesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private Request $request) {}

    /**
     * @return Builder<City>
     */
    public function query(): Builder
    {
        return City::query()
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
            'state_id',
            'name',
            'country_code',
            'state_code',
            'latitude',
            'longitude',
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
     * @param  City  $city
     * @return list<mixed>
     */
    public function map($city): array
    {
        return [
            $city->country_id,
            $city->state_id,
            $city->name,
            $city->country_code,
            $city->state_code,
            $city->latitude,
            $city->longitude,
        ];
    }
}
