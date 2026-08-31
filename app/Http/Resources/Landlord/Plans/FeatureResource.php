<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\Plans;

use App\Models\Landlord\Feature;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes a catalog feature attached to a plan.
 *
 * @property Feature $resource
 */
class FeatureResource extends JsonResource
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
            'type' => $this->type,
            'value' => $this->whenPivotLoaded('plan_features', fn () => $this->pivot->value),
            'is_active' => $this->is_active,
        ];
    }
}
