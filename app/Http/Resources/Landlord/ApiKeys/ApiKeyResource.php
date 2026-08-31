<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\ApiKeys;

use App\Http\Resources\Landlord\Auth\UserResource;
use App\Models\Landlord\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ApiKey $resource
 */
class ApiKeyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'prefix' => $this->prefix,
            'status' => $this->status,
            'last_used_at' => $this->last_used_at,
            'expires_at' => $this->expires_at,
            'revoked_at' => $this->revoked_at,
            'token' => $this->when($this->plainTextToken !== null, $this->plainTextToken),
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
