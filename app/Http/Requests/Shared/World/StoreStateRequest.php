<?php

declare(strict_types=1);

namespace App\Http\Requests\Shared\World;

use App\Http\Requests\Shared\World\Concerns\AuthorizesWorldManagement;
use App\Models\Shared\Country;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a new shared state or region.
 */
class StoreStateRequest extends FormRequest
{
    use AuthorizesWorldManagement;

    public function authorize(): bool
    {
        return $this->authorizeWorldManagement();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Parent country id.
             *
             * @example 1
             */
            'country_id' => ['required', 'integer', Rule::exists(Country::class, 'id')],
            /**
             * State or region name.
             *
             * @example Lagos
             */
            'name' => ['required', 'string', 'max:255'],
            /**
             * Country ISO code. Filled from the country when omitted.
             *
             * @example NG
             */
            'country_code' => ['sometimes', 'nullable', 'string', 'max:3'],
            /**
             * State code.
             *
             * @example LA
             */
            'state_code' => ['sometimes', 'nullable', 'string', 'max:5'],
            /**
             * Administrative type, such as state or province.
             *
             * @example state
             */
            'type' => ['sometimes', 'nullable', 'string', 'max:255'],
            /**
             * Latitude as stored by Laravel World.
             *
             * @example 6.5244
             */
            'latitude' => ['sometimes', 'nullable', 'string', 'max:255'],
            /**
             * Longitude as stored by Laravel World.
             *
             * @example 3.3792
             */
            'longitude' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'country_id' => 'country',
            'country_code' => 'country code',
            'state_code' => 'state code',
        ];
    }
}
