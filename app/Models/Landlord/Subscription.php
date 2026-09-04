<?php

declare(strict_types=1);

namespace App\Models\Landlord;

use App\Enums\Landlord\PlanInterval;
use App\Enums\Landlord\SubscriptionStatus;
use App\Models\Concerns\AllowsIncludes;
use App\Models\Concerns\LogsLandlordActivity;
use Database\Factories\Landlord\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['tenant_id', 'plan_id', 'plan_price_id', 'plan_name', 'price', 'currency', 'interval', 'interval_count', 'status', 'is_current', 'starts_at', 'ends_at', 'trial_ends_at', 'canceled_at'])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use AllowsIncludes, HasFactory, LogsLandlordActivity, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): SubscriptionFactory
    {
        return SubscriptionFactory::new();
    }

    /**
     * Attribute cast definitions for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'interval' => PlanInterval::class,
            'status' => SubscriptionStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    /**
     * Tenant that holds this subscription.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Catalog plan this subscription is based on.
     *
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Plan price snapshotted when the subscription was created.
     *
     * @return BelongsTo<PlanPrice, $this>
     */
    public function planPrice(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class);
    }

    /**
     * Relationship names allowed via Includes query parameters.
     *
     * @return list<string>
     */
    protected function allowedIncludes(): array
    {
        return ['tenant', 'plan', 'planPrice', 'invoices'];
    }

    /**
     * Invoices generated for this subscription.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Apply list filters for tenant, plan, and status.
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
            ->when(filled($filters['tenant_id'] ?? null), fn (Builder $query): Builder => $query->where('tenant_id', $filters['tenant_id']))
            ->when(filled($filters['plan_id'] ?? null), fn (Builder $query): Builder => $query->where('plan_id', $filters['plan_id']))
            ->when(filled($filters['status'] ?? null), fn (Builder $query): Builder => $query->where('status', $filters['status']));
    }

    /**
     * Search subscriptions by tenant or plan identity.
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
            $query->whereHas('tenant', function (Builder $query) use ($like): void {
                $query->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like);
            })->orWhereHas('plan', function (Builder $query) use ($like): void {
                $query->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like);
            });
        });
    }

    /**
     * Limit results to subscriptions in a current (active-like) status.
     */
    #[Scope]
    protected function current(Builder $query): void
    {
        $query->whereIn('status', SubscriptionStatus::currentCases());
    }

    /**
     * Order subscriptions by start date, newest first.
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderByDesc('starts_at')->orderByDesc('id');
    }
}
