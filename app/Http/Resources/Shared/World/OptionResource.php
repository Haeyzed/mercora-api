<?php

declare(strict_types=1);

namespace App\Http\Resources\Shared\World;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Option $resource
 */
class OptionResource extends JsonResource
{
    /**
     * @return array{label: string, value: int|string}
     */
    public function toArray(Request $request): array
    {
        /** @var Option|array{label: string, value: int|string} $option */
        $option = $this->resource;

        if ($option instanceof Option) {
            return [
                'label' => $option->label,
                'value' => $option->value,
            ];
        }

        return [
            'label' => $option['label'],
            'value' => $option['value'],
        ];
    }
}
