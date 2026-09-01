<?php

declare(strict_types=1);

namespace App\Models\Landlord;

use App\Enums\Landlord\FeatureType;
use Database\Factories\Landlord\FeatureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Entitlement definition attachable to catalog plans.
 *
 * Features describe what a plan grants (e.g. seat limits, module access).
 * The pivot {@see value} is interpreted according to {@see FeatureType}.
 * Entitlement resolution for tenants flows through plan assignments, not
 * direct feature lookups at payment time.
 */
#[Fillable(['key', 'name', 'description', 'type', 'is_active'])]
class Feature extends Model
{
    /** @use HasFactory<FeatureFactory> */
    use HasFactory, SoftDeletes;

    protected static function newFactory(): FeatureFactory
    {
        return FeatureFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FeatureType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Plans that include this feature with a typed pivot value.
     *
     * @return BelongsToMany<Plan, $this>
     */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_features')
            ->withPivot('value')
            ->withTimestamps();
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
            ->when(filled($filters['key'] ?? null), fn (Builder $query): Builder => $query->where('key', $filters['key']))
            ->when(filled($filters['type'] ?? null), fn (Builder $query): Builder => $query->where('type', $filters['type']))
            ->when(array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '', fn (Builder $query): Builder => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)));
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
                ->orWhere('key', 'like', $like)
                ->orWhere('description', 'like', $like);
        });
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('name')->orderBy('id');
    }
}
