<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
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
             * Password reset token from the email link.
             */
            'token' => ['required', 'string'],
            /**
             * Landlord account email.
             *
             * @example admin@mercora.test
             */
            'email' => ['required', 'string', 'email', 'max:255'],
            /**
             * New password.
             */
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }
}
