<?php

declare(strict_types=1);

namespace App\Models\Shared;

use App\Models\Concerns\AllowsIncludes;
use Database\Factories\Shared\StateFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Nnjeim\World\Models\State as WorldState;

class State extends WorldState
{
    /** @use HasFactory<StateFactory> */
    use AllowsIncludes, HasFactory, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): StateFactory
    {
        return StateFactory::new();
    }

    /**
     * Relationship names allowed via Includes query parameters.
     *
     * @return list<string>
     */
    protected function allowedIncludes(): array
    {
        return ['country', 'cities'];
    }

    /**
     * Apply list filters for name, country, and state code.
     *
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
            ->when(filled($filters['country_code'] ?? null), fn (Builder $query): Builder => $query->where('country_code', $filters['country_code']))
            ->when(filled($filters['state_code'] ?? null), fn (Builder $query): Builder => $query->where('state_code', $filters['state_code']));
    }

    /**
     * Search states by name or location codes.
     */
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

    /**
     * Order states by name then id.
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('name')->orderBy('id');
    }
}
