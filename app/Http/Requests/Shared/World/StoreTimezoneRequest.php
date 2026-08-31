<?php

declare(strict_types=1);

namespace App\Http\Requests\Shared\World;

use App\Http\Requests\Shared\World\Concerns\AuthorizesWorldManagement;
use App\Models\Shared\Country;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a new shared timezone.
 */
class StoreTimezoneRequest extends FormRequest
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
             * IANA timezone name.
             *
             * @example Africa/Lagos
             */
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'country_id' => 'country',
            'name' => 'timezone name',
        ];
    }
}
