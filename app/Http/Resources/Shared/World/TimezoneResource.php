<?php

declare(strict_types=1);

namespace App\Http\Resources\Shared\World;

use App\Models\Shared\Timezone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Timezone $resource
 */
class TimezoneResource extends JsonResource
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
            'country' => new CountryResource($this->whenLoaded('country')),
        ];
    }
}
