<?php

declare(strict_types=1);

namespace App\Models\Landlord;

use App\Enums\Landlord\NoticeChannel;
use App\Enums\Landlord\NoticeStatus;
use App\Models\Concerns\AllowsIncludes;
use App\Models\Concerns\LogsLandlordActivity;
use Database\Factories\Landlord\NoticeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'title', 'body', 'channel', 'status', 'read_at'])]
class Notice extends Model
{
    /** @use HasFactory<NoticeFactory> */
    use AllowsIncludes, HasFactory, LogsLandlordActivity, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'channel' => 'in_app',
        'status' => 'unread',
    ];

    protected static function newFactory(): NoticeFactory
    {
        return NoticeFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => NoticeChannel::class,
            'status' => NoticeStatus::class,
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return list<string>
     */
    protected function allowedIncludes(): array
    {
        return ['user'];
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
            ->when(filled($filters['user_id'] ?? null), fn (Builder $query): Builder => $query->where('user_id', $filters['user_id']))
            ->when(filled($filters['status'] ?? null), fn (Builder $query): Builder => $query->where('status', $filters['status']))
            ->when(filled($filters['channel'] ?? null), fn (Builder $query): Builder => $query->where('channel', $filters['channel']));
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
            $query->where('title', 'like', $like)
                ->orWhere('body', 'like', $like)
                ->orWhereHas('user', function (Builder $query) use ($like): void {
                    $query->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
        });
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
