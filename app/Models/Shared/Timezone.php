<?php

declare(strict_types=1);

namespace App\Models\Shared;

use App\Models\Concerns\AllowsIncludes;
use Database\Factories\Shared\TimezoneFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Nnjeim\World\Models\Timezone as WorldTimezone;

class Timezone extends WorldTimezone
{
    /** @use HasFactory<TimezoneFactory> */
    use AllowsIncludes, HasFactory, SoftDeletes;

    protected static function newFactory(): TimezoneFactory
    {
        return TimezoneFactory::new();
    }

    /**
     * @return list<string>
     */
    protected function allowedIncludes(): array
    {
        return ['country'];
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
            ->when(filled($filters['country_id'] ?? null), fn (Builder $query): Builder => $query->where('country_id', $filters['country_id']));
    }

    #[Scope]
    protected function search(Builder $query, mixed $term): void
    {
        $term = is_string($term) ? trim($term) : '';

        if ($term === '') {
            return;
        }

        $query->where('name', 'like', '%'.$term.'%');
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('name')->orderBy('id');
    }
}
