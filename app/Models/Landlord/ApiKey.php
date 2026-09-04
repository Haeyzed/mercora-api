<?php

declare(strict_types=1);

namespace App\Models\Landlord;

use App\Enums\Landlord\ApiKeyStatus;
use App\Models\Concerns\AllowsIncludes;
use App\Models\Concerns\LogsLandlordActivity;
use Database\Factories\Landlord\ApiKeyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'name', 'prefix', 'key_hash', 'status', 'last_used_at', 'expires_at', 'revoked_at'])]
#[Hidden(['key_hash'])]
class ApiKey extends Model
{
    /** @use HasFactory<ApiKeyFactory> */
    use AllowsIncludes, HasFactory, LogsLandlordActivity, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    public ?string $plainTextToken = null;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ApiKeyFactory
    {
        return ApiKeyFactory::new();
    }

    /**
     * Attributes excluded from Spatie activity logs.
     *
     * @return list<string>
     */
    protected function activitylogExcept(): array
    {
        return ['key_hash'];
    }

    /**
     * Attribute cast definitions for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApiKeyStatus::class,
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * User that owns this API key.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship names allowed via Includes query parameters.
     *
     * @return list<string>
     */
    protected function allowedIncludes(): array
    {
        return ['user'];
    }

    /**
     * Apply list filters for user, status, and prefix.
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
            ->when(filled($filters['user_id'] ?? null), fn (Builder $query): Builder => $query->where('user_id', $filters['user_id']))
            ->when(filled($filters['status'] ?? null), fn (Builder $query): Builder => $query->where('status', $filters['status']))
            ->when(filled($filters['prefix'] ?? null), fn (Builder $query): Builder => $query->where('prefix', $filters['prefix']));
    }

    /**
     * Search API keys by name, prefix, or owner identity.
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
                ->orWhere('prefix', 'like', $like)
                ->orWhereHas('user', function (Builder $query) use ($like): void {
                    $query->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
        });
    }

    /**
     * Order API keys by newest first.
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
