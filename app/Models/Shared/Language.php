<?php

declare(strict_types=1);

namespace App\Models\Shared;

use Database\Factories\Shared\LanguageFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Nnjeim\World\Models\Language as WorldLanguage;

class Language extends WorldLanguage
{
    /** @use HasFactory<LanguageFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): LanguageFactory
    {
        return LanguageFactory::new();
    }

    /**
     * Apply list filters for name, native name, code, and direction.
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
            ->when(filled($filters['name_native'] ?? null), fn (Builder $query): Builder => $query->where('name_native', 'like', '%'.$filters['name_native'].'%'))
            ->when(filled($filters['code'] ?? null), fn (Builder $query): Builder => $query->where('code', $filters['code']))
            ->when(filled($filters['dir'] ?? null), fn (Builder $query): Builder => $query->where('dir', $filters['dir']));
    }

    /**
     * Search languages by name, native name, or code.
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
                ->orWhere('name_native', 'like', $like)
                ->orWhere('code', 'like', $like);
        });
    }

    /**
     * Order languages by name then id.
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('name')->orderBy('id');
    }
}
