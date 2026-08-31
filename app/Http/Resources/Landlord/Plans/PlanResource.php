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
            'price' => $this->price,
            'currency' => $this->currency,
            'interval' => $this->interval,
            'trial_days' => $this->trial_days,
            'status' => $this->status,
            'feature_highlights' => $this->feature_highlights ?? [],
            'features' => FeatureResource::collection($this->whenLoaded('features')),
            'subscriptions' => SubscriptionResource::collection($this->whenLoaded('subscriptions')),
        ];
    }
}
