<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Users;

use App\Models\Landlord\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate an update to a landlord user.
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user instanceof User && ($this->user()?->can('update', $user) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');

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
        ];
    }
}
