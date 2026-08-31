<?php

declare(strict_types=1);

namespace App\Http\Requests\Shared\World;

use App\Models\Shared\Country;
use App\Models\Shared\State;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a new shared city.
 */
class StoreCityRequest extends FormRequest
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
             * Parent country id.
             *
             * @example 1
             */
            'country_id' => ['required', 'integer', Rule::exists(Country::class, 'id')],
            /**
             * Parent state id.
             *
             * @example 1
             */
            'state_id' => ['required', 'integer', Rule::exists(State::class, 'id')],
            /**
             * City name.
             *
             * @example Ikeja
             */
            'name' => ['required', 'string', 'max:255'],
            /**
             * Country ISO code. Filled from the country when omitted.
             *
             * @example NG
             */
            'country_code' => ['sometimes', 'nullable', 'string', 'max:3'],
            /**
             * State code. Filled from the state when omitted.
             *
             * @example LA
             */
            'state_code' => ['sometimes', 'nullable', 'string', 'max:5'],
            /**
             * Latitude as stored by Laravel World.
             *
             * @example 6.6018
             */
            'latitude' => ['required', 'string', 'max:255'],
            /**
             * Longitude as stored by Laravel World.
             *
             * @example 3.3515
             */
            'longitude' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'country_id' => 'country',
            'state_id' => 'state',
            'country_code' => 'country code',
            'state_code' => 'state code',
        ];
    }
}
