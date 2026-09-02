<?php

declare(strict_types=1);

namespace App\Support\Settings;

use InvalidArgumentException;

/**
 * Registry of named {@see SettingsSchema} domains.
 *
 * Domain: in-memory map of settings schemas registered at boot.
 *
 * Invariants:
 * - Domain names are unique; later registrations overwrite earlier ones for the same name.
 * - Lookup of an unknown domain throws.
 *
 * Side effects: none beyond mutating the in-memory map.
 */
class SettingsRegistry
{
    /**
     * @var array<string, SettingsSchema>
     */
    private array $schemas = [];

    /**
     * Register or replace a settings domain schema.
     */
    public function register(SettingsSchema $schema): void
    {
        $this->schemas[$schema->name()] = $schema;
    }

    /**
     * Whether a domain slug is registered.
     */
    public function has(string $domain): bool
    {
        return array_key_exists($domain, $this->schemas);
    }

    /**
     * Resolve a registered domain schema.
     *
     * @throws InvalidArgumentException When the domain is unknown.
     */
    public function get(string $domain): SettingsSchema
    {
        if (! $this->has($domain)) {
            throw new InvalidArgumentException("Unknown settings domain [{$domain}].");
        }

        return $this->schemas[$domain];
    }

    /**
     * Registered domain slugs in registration order.
     *
     * @return list<string>
     */
    public function domains(): array
    {
        return array_keys($this->schemas);
    }

    /**
     * All registered schemas keyed by domain slug.
     *
     * @return array<string, SettingsSchema>
     */
    public function all(): array
    {
        return $this->schemas;
    }
}
