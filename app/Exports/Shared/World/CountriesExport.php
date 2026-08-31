<?php

declare(strict_types=1);

namespace App\Exports\Shared\World;

use App\Models\Shared\Country;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CountriesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private Request $request) {}

    /**
     * @return Builder<Country>
     */
    public function query(): Builder
    {
        return Country::query()
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
            'iso2',
            'name',
            'status',
            'phone_code',
            'iso3',
            'native',
            'region',
            'subregion',
            'latitude',
            'longitude',
            'emoji',
            'emoji_u',
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
     * @param  Country  $country
     * @return list<mixed>
     */
    public function map($country): array
    {
        return [
            $country->iso2,
            $country->name,
            $country->status,
            $country->phone_code,
            $country->iso3,
            $country->native,
            $country->region,
            $country->subregion,
            $country->latitude,
            $country->longitude,
            $country->emoji,
            $country->emojiU,
        ];
    }
}
