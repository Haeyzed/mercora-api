<?php

declare(strict_types=1);

namespace App\Models\Shared;

use App\Models\Concerns\AllowsIncludes;
use Database\Factories\Shared\CityFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Nnjeim\World\Models\City as WorldCity;

class City extends WorldCity
{
    /** @use HasFactory<CityFactory> */
    use AllowsIncludes, HasFactory, SoftDeletes;

    protected static function newFactory(): CityFactory
    {
        return CityFactory::new();
    }

    /**
     * @return list<string>
     */
    protected function allowedIncludes(): array
    {
        return ['country', 'state'];
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
            ->when(filled($filters['country_id'] ?? null), fn (Builder $query): Builder => $query->where('country_id', $filters['country_id']))
            ->when(filled($filters['state_id'] ?? null), fn (Builder $query): Builder => $query->where('state_id', $filters['state_id']))
            ->when(filled($filters['country_code'] ?? null), fn (Builder $query): Builder => $query->where('country_code', $filters['country_code']))
            ->when(filled($filters['state_code'] ?? null), fn (Builder $query): Builder => $query->where('state_code', $filters['state_code']));
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
                ->orWhere('country_code', 'like', $like)
                ->orWhere('state_code', 'like', $like);
        });
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('name')->orderBy('id');
    }
}
