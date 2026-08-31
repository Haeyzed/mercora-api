<?php

declare(strict_types=1);

namespace App\Models\Shared;

use App\Models\Concerns\AllowsIncludes;
use Database\Factories\Shared\CountryFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Nnjeim\World\Models\Country as WorldCountry;

class Country extends WorldCountry
{
    /** @use HasFactory<CountryFactory> */
    use AllowsIncludes, HasFactory, SoftDeletes;

    protected static function newFactory(): CountryFactory
    {
        return CountryFactory::new();
    }

    /**
     * @return list<string>
     */
    protected function allowedIncludes(): array
    {
        return ['states', 'cities', 'timezones', 'currency'];
    }

    /**
     * @param  array<string, mixed>|mixed  $filters
     */
    #[Scope]
    protected function filter(Builder $query, mixed $filters): void
    {
        if (! is_array($filters)) {
            return;
        }

        $query
            ->when(filled($filters['name'] ?? null), fn (Builder $query): Builder => $query->where('name', 'like', '%'.$filters['name'].'%'))
            ->when(filled($filters['iso2'] ?? null), fn (Builder $query): Builder => $query->where('iso2', $filters['iso2']))
            ->when(filled($filters['iso3'] ?? null), fn (Builder $query): Builder => $query->where('iso3', $filters['iso3']))
            ->when(filled($filters['region'] ?? null), fn (Builder $query): Builder => $query->where('region', $filters['region']))
            ->when(filled($filters['status'] ?? null), fn (Builder $query): Builder => $query->where('status', $filters['status']));
    }

    #[Scope]
    protected function search(Builder $query, mixed $term): void
    {
        $term = is_string($term) ? trim($term) : '';

        if ($term === '') {
            return;
        }

        $like = '%'.$term.'%';

        $query->where(function (Builder $query) use ($like): void {
            $query->where('name', 'like', $like)
                ->orWhere('iso2', 'like', $like)
                ->orWhere('iso3', 'like', $like)
                ->orWhere('native', 'like', $like)
                ->orWhere('phone_code', 'like', $like);
        });
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('name')->orderBy('id');
    }
}
