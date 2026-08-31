<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Tenants;

use App\Models\Landlord\Domain;
use App\Models\Landlord\Tenant;
use App\Rules\Landlord\NotCentralDomain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a new landlord tenant and its first hostname.
 */
class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Tenant::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Tenant display name. The slug is generated from this value.
             *
             * @example Acme Stores
             */
            'name' => ['required', 'string', 'max:255'],
            /**
             * First hostname for the tenant. Cannot be a central domain.
             *
             * @example acme.mercora.test
             */
            'domain' => [
                'required',
                'string',
                'max:255',
                'lowercase',
                'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/',
                new NotCentralDomain,
                Rule::unique(Domain::class, 'domain'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'name',
            'domain' => 'domain',
        ];
    }
}
