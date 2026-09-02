<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Settings;

use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validate a domain settings update payload.
 *
 * Domain: landlord PUT /settings/{domain} body validation from registered schemas.
 *
 * Invariants:
 * - Unknown domains abort with 404 before validation.
 * - Unknown keys for the domain fail validation.
 * - When Scramble evaluates rules() without a route domain, all registered keys are documented.
 */
class UpdateSettingsDomainRequest extends FormRequest
{
    /**
     * Authorization is enforced in the controller policy.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Abort when the route domain is set but not registered.
     */
    protected function prepareForValidation(): void
    {
        $domain = (string) $this->route('domain');

        if ($domain === '') {
            return;
        }

        abort_unless(
            app(SettingsManager::class)->hasDomain($domain),
            404,
            "Unknown settings domain [{$domain}].",
        );
    }

    /**
     * Build validation rules from the domain schema (or all domains for docs).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $domain = (string) $this->route('domain');
        $manager = app(SettingsManager::class);

        if ($domain === '' || ! $manager->hasDomain($domain)) {
            return $this->documentableRules($manager);
        }

        return $this->rulesForDomain($manager, $domain);
    }

    /**
     * Reject keys that are not allowlisted for the domain.
     *
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $domain = (string) $this->route('domain');
                $manager = app(SettingsManager::class);

                if ($domain === '' || ! $manager->hasDomain($domain)) {
                    return;
                }

                $allowed = array_keys($manager->definitions($domain));
                $unknown = array_diff(array_keys($this->all()), $allowed);

                if ($unknown === []) {
                    return;
                }

                $validator->errors()->add(
                    'settings',
                    'Unknown setting keys for domain ['.$domain.']: '.implode(', ', $unknown).'.',
                );
            },
        ];
    }

    /**
     * Validated settings keyed by absolute setting name.
     *
     * @return array<string, mixed>
     */
    public function settingsPayload(): array
    {
        return $this->validated();
    }

    /**
     * Validation rules for a single registered domain.
     *
     * @return array<string, mixed>
     */
    private function rulesForDomain(SettingsManager $manager, string $domain): array
    {
        $rules = [];

        foreach ($manager->definitions($domain) as $key => $definition) {
            $rules[str_replace('.', '\.', $key)] = $definition->rules !== []
                ? $definition->rules
                : ['sometimes'];
        }

        return $rules;
    }

    /**
     * Fallback rule set when Scramble evaluates rules() without a route domain.
     *
     * @return array<string, mixed>
     */
    private function documentableRules(SettingsManager $manager): array
    {
        $rules = [];

        foreach ($manager->domains() as $domain) {
            $rules = [...$rules, ...$this->rulesForDomain($manager, $domain)];
        }

        return $rules;
    }
}
