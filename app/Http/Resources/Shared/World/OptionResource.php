<?php

declare(strict_types=1);

namespace App\Http\Resources\Shared\World;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{label: string, value: int|string} $resource
 */
class OptionResource extends JsonResource
{
    /**
     * @return array{label: string, value: int|string}
     */
    public function toArray(Request $request): array
    {
        return [
            'label' => $this->resource['label'],
            'value' => $this->resource['value'],
        ];
    }
}
