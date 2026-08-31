<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\Notifications;

use App\Http\Resources\Landlord\Auth\UserResource;
use App\Models\Landlord\Notice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Notice $resource
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'body' => $this->body,
            'channel' => $this->channel,
            'status' => $this->status,
            'read_at' => $this->read_at,
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
