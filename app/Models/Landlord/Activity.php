<?php

declare(strict_types=1);

namespace App\Models\Landlord;

use App\Models\Concerns\AllowsIncludes;
use Database\Factories\Landlord\ActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Models\Activity as BaseActivity;

class Activity extends BaseActivity
{
    /** @use HasFactory<ActivityFactory> */
    use AllowsIncludes, HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ActivityFactory
    {
        return ActivityFactory::new();
    }

    /**
     * Relationship names allowed via Includes query parameters.
     *
     * @return list<string>
     */
    protected function allowedIncludes(): array
    {
        return ['causer', 'subject'];
    }

    /**
     * Apply list filters for event, log name, subject, and causer.
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
            ->when(filled($filters['event'] ?? null), fn (Builder $query): Builder => $query->where('event', $filters['event']))
            ->when(filled($filters['log_name'] ?? null), fn (Builder $query): Builder => $query->where('log_name', $filters['log_name']))
            ->when(filled($filters['subject_type'] ?? null), fn (Builder $query): Builder => $query->where('subject_type', $filters['subject_type']))
            ->when(filled($filters['subject_id'] ?? null), fn (Builder $query): Builder => $query->where('subject_id', $filters['subject_id']))
            ->when(filled($filters['causer_id'] ?? null), fn (Builder $query): Builder => $query->where('causer_id', $filters['causer_id']));
    }

    /**
     * Search activities by description.
     */
    #[Scope]
    protected function search(Builder $query, mixed $term): void
    {
        $term = is_string($term) ? trim($term) : '';

        if ($term === '') {
            return;
        }

        $query->where('description', 'like', '%'.$term.'%');
    }

    /**
     * Order activities by creation date, newest first.
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
