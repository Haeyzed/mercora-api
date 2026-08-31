<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\ApiKeys;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate an update to an active landlord API key.
 */
class UpdateApiKeyRequest extends FormRequest
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
             * Display name for the key.
             *
             * @example Staging deploy
             */
            'name' => ['sometimes', 'string', 'max:255'],
            /**
             * Optional expiry. Null clears the expiry.
             *
             * @example 2027-08-29T20:00:00Z
             */
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'name',
            'expires_at' => 'expiry date',
        ];
    }
}
