<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\Notifications;

use App\Models\Landlord\NotificationTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes a landlord notification template.
 *
 * @property NotificationTemplate $resource
 */
class NotificationTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'channels' => $this->channels,
            'variables' => $this->variables,
            'title' => $this->title,
            'body' => $this->body,
            'email_subject' => $this->email_subject,
            'email_body' => $this->email_body,
            'push_title' => $this->push_title,
            'push_body' => $this->push_body,
            'sms_body' => $this->sms_body,
            'is_mandatory' => $this->is_mandatory,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
