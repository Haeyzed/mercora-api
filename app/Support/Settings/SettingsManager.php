<?php

declare(strict_types=1);

namespace App\Support\Settings;

use App\Enums\Landlord\SettingType;
use App\Models\Landlord\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Schema-backed typed key/value settings with cache-aside reads.
 *
 * Domain: reusable settings store for any registered {@see SettingsSchema}.
 *
 * Invariants:
 * - Domains are registered on {@see SettingsRegistry}; unknown domains fail validation.
 * - Values encode/decode according to each key's {@see SettingDefinition} type.
 * - Null is allowed only when the definition is nullable.
 *
 * Side effects: creates or updates {@see Setting} rows; forgets per-key cache entries on write.
 */
class SettingsManager
{
    public function __construct(private SettingsRegistry $registry) {}

    /**
     * Registered domain slugs in registration order.
     *
     * @return list<string>
     */
    public function domains(): array
    {
        return $this->registry->domains();
    }

    /**
     * Whether a domain slug is registered.
     */
    public function hasDomain(string $domain): bool
    {
        return $this->registry->has($domain);
    }

    /**
     * Read a decoded value by absolute key, falling back to schema default when registered.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember($this->cacheKey($key), now()->addHour(), function () use ($key, $default): mixed {
            $setting = Setting::query()->where('key', $key)->first();

            if ($setting instanceof Setting) {
                return $setting->decoded();
            }

            $definition = $this->definitionForKey($key);

            return $definition?->default ?? $default;
        });
    }

    /**
     * Known keys for a domain, cast with schema defaults.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException When the domain is unknown.
     */
    public function getDomain(string $domain): array
    {
        $schema = $this->schema($domain);
        $definitions = $schema->definitions();
        $keys = array_keys($definitions);

        $rows = Setting::query()
            ->whereIn('key', $keys)
            ->get()
            ->keyBy('key');

        $settings = [];

        foreach ($definitions as $key => $definition) {
            $row = $rows->get($key);
            $settings[$key] = $row instanceof Setting
                ? $row->decoded()
                : $definition->default;
        }

        return $settings;
    }

    /**
     * Validate against the domain allowlist and persist values.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     *
     * @throws ValidationException When the domain or keys are unknown, or a value fails its type.
     */
    public function updateDomain(string $domain, array $values): array
    {
        $schema = $this->schema($domain);
        $definitions = $schema->definitions();
        $unknown = array_diff(array_keys($values), array_keys($definitions));

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'settings' => ['Unknown setting keys for domain ['.$domain.']: '.implode(', ', $unknown).'.'],
            ]);
        }

        foreach ($values as $key => $value) {
            $this->put($domain, $key, $definitions[$key], $value);
        }

        return $this->getDomain($domain);
    }

    /**
     * Schema definitions for a registered domain.
     *
     * @return array<string, SettingDefinition>
     *
     * @throws ValidationException When the domain is unknown.
     */
    public function definitions(string $domain): array
    {
        return $this->schema($domain)->definitions();
    }

    /**
     * Persist one allowlisted key and invalidate its cache entry.
     *
     * @throws ValidationException When the value is null for a non-nullable key or fails type checks.
     */
    private function put(string $domain, string $key, SettingDefinition $definition, mixed $value): void
    {
        if ($value === null && ! $definition->nullable) {
            throw ValidationException::withMessages([
                $key => ['The '.$key.' field may not be null.'],
            ]);
        }

        if ($value !== null && ! SettingType::accepts($definition->type, $value)) {
            throw ValidationException::withMessages([
                $key => [$this->typeMessage($definition->type)],
            ]);
        }

        Setting::query()->updateOrCreate(
            ['key' => $key],
            [
                'group' => $domain,
                'type' => $definition->type,
                'value' => $value === null ? null : Setting::encode($definition->type, $value),
            ],
        );

        Cache::forget($this->cacheKey($key));
    }

    /**
     * Resolve a registered domain schema.
     *
     * @throws ValidationException When the domain is unknown.
     */
    private function schema(string $domain): SettingsSchema
    {
        if (! $this->registry->has($domain)) {
            throw ValidationException::withMessages([
                'domain' => ['Unknown settings domain ['.$domain.'].'],
            ]);
        }

        return $this->registry->get($domain);
    }

    /**
     * Find a definition for an absolute key across all registered domains.
     */
    private function definitionForKey(string $key): ?SettingDefinition
    {
        foreach ($this->registry->all() as $schema) {
            $definitions = $schema->definitions();

            if (array_key_exists($key, $definitions)) {
                return $definitions[$key];
            }
        }

        return null;
    }

    /**
     * Cache key for a single setting.
     */
    private function cacheKey(string $key): string
    {
        return 'settings.'.$key;
    }

    /**
     * Human-readable type mismatch message for validation.
     */
    private function typeMessage(SettingType $type): string
    {
        return match ($type) {
            SettingType::String => 'The value must be a string.',
            SettingType::Boolean => 'The value must be true or false.',
            SettingType::Integer => 'The value must be an integer.',
            SettingType::Json => 'The value must be a JSON object or array.',
        };
    }
}
