<?php

declare(strict_types=1);

namespace App\Services\Landlord\Plans;

use App\Enums\Landlord\FeatureType;
use App\Models\Landlord\Feature;
use App\Models\Landlord\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Centralized plan feature checks for a tenant.
 *
 * Wraps {@see EntitlementService} so controllers and services share one entry point.
 */
class FeatureGate
{
    public function __construct(private EntitlementService $entitlements) {}

    /**
     * Whether the tenant's plan includes the feature.
     */
    public function allows(Tenant $tenant, string $featureKey): bool
    {
        return $this->entitlements->allows($tenant, $featureKey);
    }

    /**
     * Assert the feature is enabled or throw a validation exception.
     *
     * @throws ValidationException
     */
    public function assert(Tenant $tenant, string $featureKey): void
    {
        if (! $this->allows($tenant, $featureKey)) {
            throw ValidationException::withMessages([
                'feature' => "Your current plan does not include the [{$featureKey}] feature.",
            ]);
        }
    }

    /**
     * Resolve a numeric limit for an integer feature.
     *
     * Returns null when unlimited, missing, or not an integer entitlement.
     */
    public function limit(Tenant $tenant, string $featureKey): ?int
    {
        $value = $this->entitlements->value($tenant, $featureKey);

        if ($value === null || $value === 'unlimited' || $value === true) {
            return null;
        }

        if ($value === false) {
            return 0;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    /**
     * Whether the tenant can use a feature given the current usage count.
     */
    public function canUse(Tenant $tenant, string $featureKey, int $currentUsage = 0): bool
    {
        if (! $this->allows($tenant, $featureKey)) {
            return false;
        }

        $limit = $this->limit($tenant, $featureKey);

        if ($limit === null) {
            return true;
        }

        return $currentUsage < $limit;
    }

    /**
     * Assert usage is within the plan limit.
     *
     * @throws ValidationException
     */
    public function assertCanUse(Tenant $tenant, string $featureKey, int $currentUsage = 0): void
    {
        $this->assert($tenant, $featureKey);

        if ($this->canUse($tenant, $featureKey, $currentUsage)) {
            return;
        }

        $limit = $this->limit($tenant, $featureKey);

        throw ValidationException::withMessages([
            'feature' => "Your current plan limit for [{$featureKey}] is {$limit}.",
        ]);
    }

    /**
     * Features attached to the tenant's current plan.
     *
     * @return Collection<int, Feature>
     */
    public function features(Tenant $tenant): Collection
    {
        return $this->entitlements->featuresForTenant($tenant);
    }

    /**
     * Typed feature value for the tenant.
     */
    public function value(Tenant $tenant, string $featureKey): mixed
    {
        return $this->entitlements->value($tenant, $featureKey);
    }

    /**
     * Whether the resolved value is an unlimited entitlement.
     */
    public function isUnlimited(Tenant $tenant, string $featureKey): bool
    {
        $value = $this->entitlements->value($tenant, $featureKey);

        return $value === 'unlimited' || $value === FeatureType::Unlimited->value;
    }
}
