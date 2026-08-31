<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\Settings;

use App\Models\Landlord\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Setting $resource
 */
class SettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group' => $this->group,
            'key' => $this->key,
            'type' => $this->type,
            'value' => $this->decoded(),
            'description' => $this->description,
        ];
    }
}
