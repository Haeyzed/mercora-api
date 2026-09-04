<?php

declare(strict_types=1);

namespace App\Models\Landlord;

use App\Enums\Media\MediaCollection;
use App\Enums\Media\MediaConversion;
use App\Models\Concerns\AllowsIncludes;
use App\Models\Concerns\LogsLandlordActivity;
use App\Services\Landlord\Settings\SettingService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasMedia
{
    /** @use HasFactory<UserFactory> */
    use AllowsIncludes, HasApiTokens, HasFactory, HasRoles, InteractsWithMedia, LogsLandlordActivity, Notifiable;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * Attribute cast definitions for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Attributes excluded from Spatie activity logs.
     *
     * @return list<string>
     */
    protected function activitylogExcept(): array
    {
        return ['password', 'remember_token'];
    }

    /**
     * Notices addressed to this user.
     *
     * @return HasMany<Notice, $this>
     */
    public function notices(): HasMany
    {
        return $this->hasMany(Notice::class);
    }

    /**
     * API keys owned by this user.
     *
     * @return HasMany<ApiKey, $this>
     */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    /**
     * Register media collections for the user avatar.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(MediaCollection::Avatar->value)
            ->singleFile()
            ->acceptsMimeTypes(config('media.mimes.image', []));
    }

    /**
     * Register image conversions for avatar thumbnails.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        if (! $this->shouldGenerateThumbnails()) {
            return;
        }

        $thumb = config('media.conversions.thumb');

        $this->addMediaConversion(MediaConversion::Thumb->value)
            ->fit(Fit::Max, (int) $thumb['width'], (int) $thumb['height'])
            ->nonQueued()
            ->performOnCollections(MediaCollection::Avatar->value);
    }

    /**
     * Whether thumbnail conversions should run based on storage settings.
     */
    private function shouldGenerateThumbnails(): bool
    {
        try {
            if (! Schema::hasTable('settings')) {
                return true;
            }

            return (bool) app(SettingService::class)->value('storage.generate_thumbnails', true);
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * Relationship names allowed via Includes query parameters.
     *
     * @return list<string>
     */
    protected function allowedIncludes(): array
    {
        return ['roles'];
    }

    /**
     * Apply list filters for name, email, and active status.
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
            ->when(filled($filters['email'] ?? null), fn (Builder $query): Builder => $query->where('email', $filters['email']))
            ->when(array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '', fn (Builder $query): Builder => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)));
    }

    /**
     * Search users by name or email.
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
                ->orWhere('email', 'like', $like);
        });
    }

    /**
     * Order users by name then id.
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('name')->orderBy('id');
    }
}
