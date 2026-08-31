<?php

declare(strict_types=1);

namespace App\Services\Landlord\Plans;

use App\Enums\Landlord\FeatureType;
use App\Models\Landlord\Feature;
use App\Models\Landlord\Plan;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\Tenant;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves plan feature entitlements for tenants based on their current subscription.
 *
 * Domain: feature gating derived from the tenant's active subscription plan.
 *
 * Invariants:
 * - Entitlements are read from the tenant's current subscription and its plan features.
 * - Feature values are typed according to {@see FeatureType} (boolean, integer, unlimited, string).
 * - Missing subscription, plan, or feature key yields null/false for access checks.
 *
 * Side effects: reads and invalidates cache entries keyed by tenant and feature.
 */
class EntitlementService
{
    /**
     * Determine whether a tenant is entitled to a feature by key.
     *
     * Returns true for unlimited or truthy boolean values, positive integers, and other truthy scalars.
     */
    public function allows(Tenant $tenant, string $key): bool
    {
        $value = $this->value($tenant, $key);

        if ($value === null) {
            return false;
        }

        if ($value === 'unlimited' || $value === true) {
            return true;
        }

        if (is_numeric($value)) {
            return (int) $value > 0;
        }

        return (bool) $value;
    }

    /**
     * Resolve the typed feature value for a tenant, or null when not entitled.
     *
     * Results are cached for one hour per tenant and feature key.
     */
    public function value(Tenant $tenant, string $key): mixed
    {
        return Cache::remember(
            "tenant.{$tenant->id}.feature.{$key}",
            now()->addHour(),
            function () use ($tenant, $key): mixed {
                $subscription = Subscription::query()
                    ->where('tenant_id', $tenant->id)
                    ->current()
                    ->with('plan')
                    ->first();

                if ($subscription?->plan === null) {
                    return null;
                }

                $feature = $subscription->plan->features()->where('key', $key)->first();

                if (! $feature instanceof Feature) {
                    return null;
                }

                $raw = $feature->pivot->value ?? null;

                return match ($feature->type) {
                    FeatureType::Boolean => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
                    FeatureType::Integer => is_numeric($raw) ? (int) $raw : null,
                    FeatureType::Unlimited => 'unlimited',
                    default => $raw,
                };
            },
        );
    }

    /**
     * Invalidate cached feature values for all features on the tenant's current plan.
     *
     * No-op when the tenant has no current subscription or plan.
     */
    public function forget(Tenant $tenant): void
    {
        $subscription = Subscription::query()
            ->where('tenant_id', $tenant->id)
            ->current()
            ->with('plan')
            ->first();

        if ($subscription?->plan === null) {
            return;
        }

        foreach ($subscription->plan->features()->get() as $feature) {
            Cache::forget("tenant.{$tenant->id}.feature.{$feature->key}");
        }
    }
}
