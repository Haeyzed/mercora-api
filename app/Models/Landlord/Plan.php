<?php

declare(strict_types=1);

namespace App\Models\Landlord;

use App\Enums\Landlord\PlanStatus;
use App\Models\Concerns\AllowsIncludes;
use App\Models\Concerns\LogsLandlordActivity;
use Database\Factories\Landlord\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * Landlord subscription catalog plan.
 *
 * Billing amounts are defined on related {@see PlanPrice} records. Entitlement
 * capabilities are attached through the {@see self::features()} relationship.
 * Marketing bullet points are stored in {@see self::$feature_highlights}.
 */
#[Fillable(['name', 'slug', 'description', 'status', 'feature_highlights'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use AllowsIncludes, HasFactory, HasSlug, LogsLandlordActivity, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): PlanFactory
    {
        return PlanFactory::new();
    }

    /**
     * Configure slug generation from the plan name.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    /**
     * Attribute cast definitions for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PlanStatus::class,
            'feature_highlights' => 'array',
        ];
    }

    /**
     * Relationship names allowed via Includes query parameters.
     *
     * @return list<string>
     */
    protected function allowedIncludes(): array
    {
        return ['subscriptions', 'prices', 'features'];
    }

    /**
     * Subscriptions created from this plan.
     *
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Currency-specific prices for this plan.
     *
     * @return HasMany<PlanPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class);
    }

    /**
     * The first active price for display and catalog summaries.
     *
     * @return HasOne<PlanPrice, $this>
     */
    public function primaryPrice(): HasOne
    {
        return $this->hasOne(PlanPrice::class)->ofMany(
            ['id' => 'min'],
            fn (Builder $query): Builder => $query->where('is_active', true),
        );
    }

    /**
     * Alias for scoped child route binding ({plan_price}).
     *
     * @return HasMany<PlanPrice, $this>
     */
    public function planPrices(): HasMany
    {
        return $this->prices();
    }

    /**
     * Entitlement features attached to this plan.
     *
     * @return BelongsToMany<Feature, $this>
     */
    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_features')
            ->withPivot('value')
            ->withTimestamps();
    }

    /**
     * Apply list filters for identity, status, interval, and currency.
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
            ->when(filled($filters['slug'] ?? null), fn (Builder $query): Builder => $query->where('slug', $filters['slug']))
            ->when(filled($filters['status'] ?? null), fn (Builder $query): Builder => $query->where('status', $filters['status']))
            ->when(filled($filters['interval'] ?? null), fn (Builder $query): Builder => $query->whereHas(
                'prices',
                fn (Builder $query): Builder => $query->where('interval', $filters['interval'])->where('is_active', true),
            ))
            ->when(filled($filters['currency'] ?? null), fn (Builder $query): Builder => $query->whereHas(
                'prices',
                fn (Builder $query): Builder => $query->where('currency', $filters['currency'])->where('is_active', true),
            ));
    }

    /**
     * Search plans by name, slug, or description.
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
                ->orWhere('slug', 'like', $like)
                ->orWhere('description', 'like', $like);
        });
    }

    /**
     * Order plans by name then id.
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('name')->orderBy('id');
    }
}
