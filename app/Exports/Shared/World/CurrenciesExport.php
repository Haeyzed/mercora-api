<?php

declare(strict_types=1);

namespace App\Exports\Shared\World;

use App\Models\Shared\Currency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CurrenciesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private Request $request) {}

    /**
     * @return Builder<Currency>
     */
    public function query(): Builder
    {
        return Currency::query()
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
            'code',
            'precision',
            'symbol',
            'symbol_native',
            'symbol_first',
            'decimal_mark',
            'thousands_separator',
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
     * @param  Currency  $currency
     * @return list<mixed>
     */
    public function map($currency): array
    {
        return [
            $currency->country_id,
            $currency->name,
            $currency->code,
            $currency->precision,
            $currency->symbol,
            $currency->symbol_native,
            $currency->symbol_first,
            $currency->decimal_mark,
            $currency->thousands_separator,
        ];
    }
}
