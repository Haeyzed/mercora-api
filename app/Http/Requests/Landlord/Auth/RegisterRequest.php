<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Auth;

use App\Models\Landlord\Domain;
use App\Rules\Landlord\NotCentralDomain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'tenant_name' => ['required', 'string', 'max:255'],
            'domain' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Domain::class, 'domain'),
                new NotCentralDomain,
            ],
            'terms_accepted' => ['sometimes', 'boolean'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
