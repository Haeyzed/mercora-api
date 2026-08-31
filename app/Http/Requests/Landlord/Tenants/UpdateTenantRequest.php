<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Tenants;

use App\Models\Landlord\Tenant;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate an update to a landlord tenant.
 */
class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->route('tenant');

        return $tenant instanceof Tenant && ($this->user()?->can('update', $tenant) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Tenant display name. Updating this regenerates the slug.
             *
             * @example Acme Stores
             */
            'name' => ['sometimes', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'name',
        ];
    }
}
