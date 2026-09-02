<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\Audit;

use App\Http\Resources\Landlord\Auth\UserResource;
use App\Http\Resources\Landlord\Tenants\TenantResource;
use App\Models\Landlord\Activity;
use App\Models\Landlord\Tenant;
use App\Models\Landlord\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Activity $resource
 */
class ActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'log_name' => $this->log_name,
            'description' => $this->description,
            'event' => $this->event,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'causer_type' => $this->causer_type,
            'causer_id' => $this->causer_id,
            'attribute_changes' => $this->attribute_changes,
            'properties' => $this->properties,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'causer' => $this->when($this->relationLoaded('causer'), fn (): mixed => $this->morphResource($this->causer)),
            'subject' => $this->when($this->relationLoaded('subject'), fn (): mixed => $this->morphResource($this->subject)),
        ];
    }

    /**
     * @return UserResource|TenantResource|array{id: mixed, type: string}|null
     */
    private function morphResource(?Model $model): UserResource|TenantResource|array|null
    {
        return match (true) {
            $model instanceof User => new UserResource($model),
            $model instanceof Tenant => new TenantResource($model),
            $model instanceof Model => [
                'id' => $model->getKey(),
                'type' => $model->getMorphClass(),
            ],
            default => null,
        };
    }
}
