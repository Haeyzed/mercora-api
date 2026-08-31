<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\ApiKeys;

use App\Models\Landlord\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a new landlord API key.
 */
class StoreApiKeyRequest extends FormRequest
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
             * Landlord user the key belongs to.
             *
             * @example 1
             */
            'user_id' => ['required', 'integer', Rule::exists(User::class, 'id')],
            /**
             * Display name for the key.
             *
             * @example CI deploy
             */
            'name' => ['required', 'string', 'max:255'],
            /**
             * Optional expiry. The key remains active until revoked or this time.
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
            'user_id' => 'user',
            'name' => 'name',
            'expires_at' => 'expiry date',
        ];
    }
}
