<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property LoginPayload $resource
 */
class LoginResource extends JsonResource
{
    /**
     * @return array{token: string, token_type: string, user: UserResource}
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->resource->token,
            'token_type' => 'Bearer',
            'user' => new UserResource($this->resource->user),
        ];
    }
}
