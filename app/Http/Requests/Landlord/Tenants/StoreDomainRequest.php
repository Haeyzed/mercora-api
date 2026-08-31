<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Tenants;

use App\Models\Landlord\Domain;
use App\Rules\Landlord\NotCentralDomain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a hostname for an existing tenant.
 */
class StoreDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Hostname for the tenant. Cannot be a central domain.
             *
             * @example shop.acme.test
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
            'domain' => 'domain',
        ];
    }
}
