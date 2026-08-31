<?php

declare(strict_types=1);

namespace App\Http\Requests\Shared\World;

use App\Models\Shared\Country;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a new shared country.
 */
class StoreCountryRequest extends FormRequest
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
             * ISO 3166-1 alpha-2 country code.
             *
             * @example NG
             */
            'iso2' => ['required', 'string', 'size:2', Rule::unique(Country::class, 'iso2')],
            /**
             * Country name.
             *
             * @example Nigeria
             */
            'name' => ['required', 'string', 'max:255'],
            /**
             * Active status flag. Defaults to 1 when omitted.
             *
             * @example 1
             */
            'status' => ['sometimes', 'integer'],
            /**
             * International calling code without a plus sign.
             *
             * @example 234
             */
            'phone_code' => ['required', 'string', 'max:5'],
            /**
             * ISO 3166-1 alpha-3 country code.
             *
             * @example NGA
             */
            'iso3' => ['required', 'string', 'size:3', Rule::unique(Country::class, 'iso3')],
            /**
             * Native country name.
             *
             * @example Nigeria
             */
            'native' => ['required', 'string', 'max:255'],
            /**
             * Geographic region.
             *
             * @example Africa
             */
            'region' => ['required', 'string', 'max:255'],
            /**
             * Geographic subregion.
             *
             * @example Western Africa
             */
            'subregion' => ['required', 'string', 'max:255'],
            /**
             * Latitude as stored by Laravel World.
             *
             * @example 9.08200000
             */
            'latitude' => ['required', 'string', 'max:255'],
            /**
             * Longitude as stored by Laravel World.
             *
             * @example 8.67530000
             */
            'longitude' => ['required', 'string', 'max:255'],
            /**
             * Country flag emoji.
             *
             * @example 🇳🇬
             */
            'emoji' => ['required', 'string', 'max:255'],
            /**
             * Unicode code points for the flag emoji.
             *
             * @example U+1F1F3 U+1F1EC
             */
            'emojiU' => ['required', 'string', 'max:255'],
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
