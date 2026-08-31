<?php

declare(strict_types=1);

namespace App\Http\Resources\Shared\World;

use App\Models\Shared\State;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property State $resource
 */
class StateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'country_id' => $this->country_id,
            'country_code' => $this->country_code,
            'state_code' => $this->state_code,
            'type' => $this->type,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'country' => new CountryResource($this->whenLoaded('country')),
            'cities' => CityResource::collection($this->whenLoaded('cities')),
        ];
    }
}
