<?php

declare(strict_types=1);

namespace App\Services\Landlord;

use App\Http\Resources\Landlord\Settings\SettingsDomain;
use App\Models\Landlord\Setting;
use App\Settings\Landlord;
use App\Support\Settings\SettingsManager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Landlord platform settings: schema domains with typed values and defaults.
 *
 * Domain: central key/value configuration for the landlord application.
 *
 * Invariants:
 * - Domains and keys are defined in {@see Landlord} schemas; unknown keys are rejected.
 * - Missing rows resolve to schema defaults.
 * - Application code reads via {@see value()}, not direct model queries.
 *
 * Side effects: persists {@see Setting} rows through {@see SettingsManager}; invalidates cache on write.
 */
class SettingService
{
    public function __construct(private SettingsManager $settings) {}

    /**
     * Load a settings domain with schema defaults for unset keys.
     *
     * @throws NotFoundHttpException When the domain is not registered.
     */
    public function showDomain(string $domain): SettingsDomain
    {
        $this->ensureDomain($domain);

        return new SettingsDomain(
            domain: $domain,
            settings: $this->settings->getDomain($domain),
        );
    }

    /**
     * Persist allowlisted keys for a settings domain and return the full domain payload.
     *
     * @param  array<string, mixed>  $values
     *
     * @throws NotFoundHttpException When the domain is not registered.
     */
    public function updateDomain(string $domain, array $values): SettingsDomain
    {
        $this->ensureDomain($domain);

        return new SettingsDomain(
            domain: $domain,
            settings: $this->settings->updateDomain($domain, $values),
        );
    }

    /**
     * Read a decoded setting value by absolute key for application code.
     */
    public function value(string $key, mixed $default = null): mixed
    {
        return $this->settings->get($key, $default);
    }

    /**
     * Abort when the domain slug is not registered.
     *
     * @throws NotFoundHttpException
     */
    private function ensureDomain(string $domain): void
    {
        if (! $this->settings->hasDomain($domain)) {
            throw new NotFoundHttpException("Unknown settings domain [{$domain}].");
        }
    }
}
