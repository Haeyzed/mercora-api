<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Auth;

use App\Models\Landlord\User;
use App\Support\Media\MediaValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
        /** @var User|null $user */
        $user = $this->user();

        return [
            /**
             * Display name.
             *
             * @example Ada Lovelace
             */
            'name' => ['sometimes', 'string', 'max:255'],
            /**
             * Unique login email.
             *
             * @example ada@mercora.test
             */
            'email' => ['sometimes', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($user?->id)],
            /**
             * Optional profile image. Replaces the current avatar when provided.
             */
            'avatar' => MediaValidation::image(required: false),
        ];
    }
}
