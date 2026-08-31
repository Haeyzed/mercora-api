<?php

declare(strict_types=1);

namespace App\Http\Resources\Shared\World;

use App\Models\Shared\Country;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Country $resource
 */
class CountryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'iso2' => $this->iso2,
            'iso3' => $this->iso3,
            'phone_code' => $this->phone_code,
            'native' => $this->native,
            'region' => $this->region,
            'subregion' => $this->subregion,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'emoji' => $this->emoji,
            'emojiU' => $this->emojiU,
            'status' => $this->status,
            'states' => StateResource::collection($this->whenLoaded('states')),
            'cities' => CityResource::collection($this->whenLoaded('cities')),
            'timezones' => TimezoneResource::collection($this->whenLoaded('timezones')),
            'currency' => new CurrencyResource($this->whenLoaded('currency')),
        ];
    }
}
