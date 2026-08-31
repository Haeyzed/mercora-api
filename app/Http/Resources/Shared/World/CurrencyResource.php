<?php

declare(strict_types=1);

namespace App\Http\Resources\Shared\World;

use App\Models\Shared\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Currency $resource
 */
class CurrencyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'country_id' => $this->country_id,
            'name' => $this->name,
            'code' => $this->code,
            'precision' => $this->precision,
            'symbol' => $this->symbol,
            'symbol_native' => $this->symbol_native,
            'symbol_first' => $this->symbol_first,
            'decimal_mark' => $this->decimal_mark,
            'thousands_separator' => $this->thousands_separator,
            'country' => new CountryResource($this->whenLoaded('country')),
        ];
    }
}
