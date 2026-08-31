<?php

declare(strict_types=1);

namespace App\Http\Requests\Shared\World;

use App\Models\Shared\Country;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate an update to a shared world currency.
 */
class UpdateCurrencyRequest extends FormRequest
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
            'country_id' => ['sometimes', 'integer', Rule::exists(Country::class, 'id')],
            /**
             * Currency name.
             *
             * @example Nigerian naira
             */
            'name' => ['sometimes', 'string', 'max:255'],
            /**
             * ISO 4217 currency code.
             *
             * @example NGN
             */
            'code' => ['sometimes', 'string', 'max:10'],
            /**
             * Decimal precision.
             *
             * @example 2
             */
            'precision' => ['sometimes', 'integer'],
            /**
             * Currency symbol.
             *
             * @example ₦
             */
            'symbol' => ['sometimes', 'string', 'max:255'],
            /**
             * Native currency symbol.
             *
             * @example ₦
             */
            'symbol_native' => ['sometimes', 'string', 'max:255'],
            /**
             * Whether the symbol is displayed before the amount.
             *
             * @example true
             */
            'symbol_first' => ['sometimes', 'boolean'],
            /**
             * Decimal separator.
             *
             * @example .
             */
            'decimal_mark' => ['sometimes', 'string', 'size:1'],
            /**
             * Thousands separator.
             *
             * @example ,
             */
            'thousands_separator' => ['sometimes', 'string', 'size:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'country_id' => 'country',
            'code' => 'currency code',
            'symbol_native' => 'native symbol',
            'symbol_first' => 'symbol first',
            'decimal_mark' => 'decimal mark',
            'thousands_separator' => 'thousands separator',
        ];
    }
}
