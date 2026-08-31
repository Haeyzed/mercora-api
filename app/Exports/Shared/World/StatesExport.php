<?php

declare(strict_types=1);

namespace App\Exports\Shared\World;

use App\Models\Shared\State;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StatesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private Request $request) {}

    /**
     * @return Builder<State>
     */
    public function query(): Builder
    {
        return State::query()
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
            'country_code',
            'state_code',
            'type',
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
     * @param  State  $state
     * @return list<mixed>
     */
    public function map($state): array
    {
        return [
            $state->country_id,
            $state->name,
            $state->country_code,
            $state->state_code,
            $state->type,
            $state->latitude,
            $state->longitude,
        ];
    }
}
