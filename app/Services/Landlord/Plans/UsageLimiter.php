<?php

declare(strict_types=1);

namespace App\Services\Landlord\Plans;

use App\Models\Landlord\Tenant;
use App\Models\Tenant\User;
use Illuminate\Validation\ValidationException;

/**
 * Enforces plan usage limits against live tenant resource counts.
 */
class UsageLimiter
{
    public const string FEATURE_USERS_MAX = 'users.max';

    public function __construct(private FeatureGate $gate) {}

    /**
     * Assert the tenant may create another unit of the given limited resource.
     *
     * Entitlements are resolved on the central connection; usage is counted on the tenant DB.
     *
     * @throws ValidationException
     */
    public function assertCanCreate(Tenant $tenant, string $featureKey, int $quantity = 1): void
    {
        $limit = $this->entitledLimit($tenant, $featureKey);

        if ($limit === null) {
            return;
        }

        $usage = $this->currentUsage($featureKey);

        if (($usage + $quantity) > $limit) {
            throw ValidationException::withMessages([
                'feature' => "Plan limit reached for [{$featureKey}] ({$usage}/{$limit}).",
            ]);
        }
    }

    /**
     * Whether the tenant can create another unit of the resource.
     */
    public function canCreate(Tenant $tenant, string $featureKey, int $quantity = 1): bool
    {
        try {
            $this->assertCanCreate($tenant, $featureKey, $quantity);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    /**
     * Remaining units before the plan limit (null when unlimited).
     *
     * Returns 0 when the feature is not entitled.
     */
    public function remaining(Tenant $tenant, string $featureKey): ?int
    {
        if (! $this->onCentral(fn (): bool => $this->gate->allows($tenant, $featureKey))) {
            return 0;
        }

        $limit = $this->onCentral(fn (): ?int => $this->gate->limit($tenant, $featureKey));

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $this->currentUsage($featureKey));
    }

    /**
     * Current usage count for a feature key (tenant DB).
     */
    public function currentUsage(string $featureKey): int
    {
        return match ($featureKey) {
            self::FEATURE_USERS_MAX => User::query()->count(),
            default => 0,
        };
    }

    /**
     * Assert the feature is entitled and return its numeric limit (null = unlimited).
     *
     * @throws ValidationException
     */
    private function entitledLimit(Tenant $tenant, string $featureKey): ?int
    {
        return $this->onCentral(function () use ($tenant, $featureKey): ?int {
            $this->gate->assert($tenant, $featureKey);

            return $this->gate->limit($tenant, $featureKey);
        });
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function onCentral(callable $callback): mixed
    {
        if (tenancy()->initialized) {
            return tenancy()->central($callback);
        }

        return $callback();
    }
}
