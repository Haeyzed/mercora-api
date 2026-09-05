<?php

declare(strict_types=1);

namespace App\Models\Landlord;

use Database\Factories\Landlord\NotificationTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'key',
    'name',
    'description',
    'channels',
    'variables',
    'title',
    'body',
    'email_subject',
    'email_body',
    'push_title',
    'push_body',
    'sms_body',
    'is_mandatory',
    'is_active',
])]
class NotificationTemplate extends Model
{
    /** @use HasFactory<NotificationTemplateFactory> */
    use HasFactory;

    protected static function newFactory(): NotificationTemplateFactory
    {
        return NotificationTemplateFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'variables' => 'array',
            'is_mandatory' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Apply list filters for key and active state.
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
            ->when(array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '', function (Builder $query) use ($filters): void {
                $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
            })
            ->when(filled($filters['key'] ?? null), fn (Builder $query): Builder => $query->where('key', $filters['key']));
    }

    /**
     * Partial-match key, name, or description.
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
            $query->where('key', 'like', $like)
                ->orWhere('name', 'like', $like)
                ->orWhere('description', 'like', $like);
        });
    }

    /**
     * Default list order by template key.
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('key');
    }
}
