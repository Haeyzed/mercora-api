<?php

declare(strict_types=1);

namespace App\Http\Requests\Shared\World;

use App\Http\Requests\Shared\World\Concerns\AuthorizesWorldManagement;
use App\Models\Shared\Country;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate an update to a shared country.
 */
class UpdateCountryRequest extends FormRequest
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
        $country = $this->route('country');

        return [
            /**
             * ISO 3166-1 alpha-2 country code.
             *
             * @example NG
             */
            'iso2' => ['sometimes', 'string', 'size:2', Rule::unique(Country::class, 'iso2')->ignore($country)],
            /**
             * Country name.
             *
             * @example Nigeria
             */
            'name' => ['sometimes', 'string', 'max:255'],
            /**
             * Active status flag.
             *
             * @example 1
             */
            'status' => ['sometimes', 'integer'],
            /**
             * International calling code without a plus sign.
             *
             * @example 234
             */
            'phone_code' => ['sometimes', 'string', 'max:5'],
            /**
             * ISO 3166-1 alpha-3 country code.
             *
             * @example NGA
             */
            'iso3' => ['sometimes', 'string', 'size:3', Rule::unique(Country::class, 'iso3')->ignore($country)],
            /**
             * Native country name.
             *
             * @example Nigeria
             */
            'native' => ['sometimes', 'string', 'max:255'],
            /**
             * Geographic region.
             *
             * @example Africa
             */
            'region' => ['sometimes', 'string', 'max:255'],
            /**
             * Geographic subregion.
             *
             * @example Western Africa
             */
            'subregion' => ['sometimes', 'string', 'max:255'],
            /**
             * Latitude as stored by Laravel World.
             *
             * @example 9.08200000
             */
            'latitude' => ['sometimes', 'string', 'max:255'],
            /**
             * Longitude as stored by Laravel World.
             *
             * @example 8.67530000
             */
            'longitude' => ['sometimes', 'string', 'max:255'],
            /**
             * Country flag emoji.
             *
             * @example 🇳🇬
             */
            'emoji' => ['sometimes', 'string', 'max:255'],
            /**
             * Unicode code points for the flag emoji.
             *
             * @example U+1F1F3 U+1F1EC
             */
            'emojiU' => ['sometimes', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'iso2' => 'ISO 3166-1 alpha-2 code',
            'iso3' => 'ISO 3166-1 alpha-3 code',
            'phone_code' => 'phone code',
            'emojiU' => 'emoji unicode',
        ];
    }
}
