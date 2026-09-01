<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\Plans;

use App\Http\Resources\Landlord\Subscriptions\SubscriptionResource;
use App\Models\Landlord\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Plan $resource
 */
class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'status' => $this->status,
            'feature_highlights' => $this->feature_highlights ?? [],
            'primary_price' => new PlanPriceResource($this->whenLoaded('primaryPrice')),
            'features' => FeatureResource::collection($this->whenLoaded('features')),
            'prices' => PlanPriceResource::collection($this->whenLoaded('prices')),
            'subscriptions' => SubscriptionResource::collection($this->whenLoaded('subscriptions')),
        ];
    }
}
