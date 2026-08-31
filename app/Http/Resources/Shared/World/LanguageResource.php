<?php

declare(strict_types=1);

namespace App\Http\Resources\Shared\World;

use App\Models\Shared\Language;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Language $resource
 */
class LanguageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'name_native' => $this->name_native,
            'dir' => $this->dir,
        ];
    }
}
