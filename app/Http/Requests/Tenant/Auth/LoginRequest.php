<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate tenant login credentials.
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
             * Tenant account email.
             *
             * @example admin@acme.test
             */
            'email' => ['required', 'email'],
            /**
             * Tenant account password.
             *
             * @example password
             */
            'password' => ['required', 'string'],
            /**
             * Label stored on the issued API token.
             *
             * @example tenant-admin
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
