<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate landlord login credentials.
 */
class LoginRequest extends FormRequest
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
             * Landlord account email.
             *
             * @example admin@mercora.test
             */
            'email' => ['required', 'email'],
            /**
             * Landlord account password.
             *
             * @example password
             */
            'password' => ['required', 'string'],
            /**
             * Label stored on the issued API token.
             *
             * @example landlord-admin
             */
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }

    /**
     * @return array{email: string, password: string, device_name?: string}
     */
    public function credentials(): array
    {
        return $this->safe()->only(['email', 'password', 'device_name']);
    }
}
