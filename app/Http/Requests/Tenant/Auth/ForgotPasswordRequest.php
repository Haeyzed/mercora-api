<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
