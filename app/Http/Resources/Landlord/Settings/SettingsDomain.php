<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\Settings;

/**
 * Settings domain payload wrapped by {@see SettingsDomainResource}.
 *
 * Domain: transport DTO for a landlord settings domain response.
 */
final readonly class SettingsDomain
{
    /**
     * @param  array<string, mixed>  $settings  Absolute dotted keys mapped to decoded values.
     */
    public function __construct(
        public string $domain,
        public array $settings,
    ) {}
}
