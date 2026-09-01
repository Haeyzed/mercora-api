<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\Plans;

use App\Models\Landlord\PlanPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property PlanPrice $resource
 */
class PlanPriceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_id' => $this->plan_id,
            'currency' => $this->currency,
            'amount' => $this->amount,
            'interval' => $this->interval,
            'interval_count' => $this->interval_count,
            'trial_days' => $this->trial_days,
            'is_active' => $this->is_active,
        ];
    }
}
