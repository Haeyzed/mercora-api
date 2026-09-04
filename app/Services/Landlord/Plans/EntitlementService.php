<?php

declare(strict_types=1);

namespace App\Services\Landlord\Plans;

use App\Enums\Landlord\FeatureType;
use App\Models\Landlord\Feature;
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
 * - Cache entries are versioned per tenant so {@see forget()} invalidates without knowing feature keys.
 *
 * Side effects: reads and invalidates cache entries keyed by tenant, version, and feature.
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
     * Results are cached for one hour per tenant, entitlement version, and feature key.
     */
    public function value(Tenant $tenant, string $key): mixed
    {
        $version = $this->version($tenant);

        return Cache::remember(
            $this->cacheKey($tenant, $version, $key),
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
     * Invalidate cached feature values for the tenant by bumping the entitlement version.
     */
    public function forget(Tenant $tenant): void
    {
        $versionKey = $this->versionKey($tenant);
        $current = (int) Cache::get($versionKey, 0);

        Cache::forever($versionKey, $current + 1);
    }

    private function version(Tenant $tenant): int
    {
        return (int) Cache::get($this->versionKey($tenant), 0);
    }

    private function versionKey(Tenant $tenant): string
    {
        return "tenant.{$tenant->id}.entitlements_version";
    }

    private function cacheKey(Tenant $tenant, int $version, string $key): string
    {
        return "tenant.{$tenant->id}.entitlements.v{$version}.{$key}";
    }
}
