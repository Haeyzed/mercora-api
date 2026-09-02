<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
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
             * Current account password.
             */
            'current_password' => ['required', 'string'],
            /**
             * New password.
             */
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }
}
