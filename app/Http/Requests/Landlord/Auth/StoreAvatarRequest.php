<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Auth;

use App\Support\Media\MediaValidation;
use Illuminate\Foundation\Http\FormRequest;

class StoreAvatarRequest extends FormRequest
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
             * Profile image file.
             */
            'avatar' => MediaValidation::avatar(required: true),
        ];
    }
}
