<?php

declare(strict_types=1);

namespace App\Models\Landlord;

use App\Enums\Landlord\PlanInterval;
use Database\Factories\Landlord\PlanPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Currency-specific price for a catalog plan.
 *
 * Amount is stored in minor units (integer). Subscriptions snapshot price and
 * currency from an active plan price at creation time; changes here do not
 * retroactively alter existing subscriptions or issued invoices.
 *
 * @property int $amount Price in minor currency units.
 * @property PlanInterval $interval Billing cadence (e.g. month, year).
 */
#[Fillable([
    'plan_id',
    'currency',
    'amount',
    'interval',
    'interval_count',
    'trial_days',
    'is_active',
])]
class PlanPrice extends Model
{
    /** @use HasFactory<PlanPriceFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): PlanPriceFactory
    {
        return PlanPriceFactory::new();
    }

    /**
     * Attribute cast definitions for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'interval' => PlanInterval::class,
            'interval_count' => 'integer',
            'trial_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Parent plan this price belongs to.
     *
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Subscriptions created from this price snapshot.
     *
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Order prices with active ones first, then by currency.
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderByDesc('is_active')->orderBy('currency')->orderBy('id');
    }
}
