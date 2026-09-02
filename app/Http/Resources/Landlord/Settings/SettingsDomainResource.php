<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property SettingsDomain $resource
 */
class SettingsDomainResource extends JsonResource
{
    /**
     * Transform the settings domain payload into an array.
     *
     * @return array{domain: string, settings: array<string, mixed>}
     */
    public function toArray(Request $request): array
    {
        return [
            'domain' => $this->resource->domain,
            'settings' => $this->resource->settings,
        ];
    }
}
