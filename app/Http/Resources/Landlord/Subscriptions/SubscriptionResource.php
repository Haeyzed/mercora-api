<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\Subscriptions;

use App\Http\Resources\Landlord\Billing\InvoiceResource;
use App\Http\Resources\Landlord\Plans\PlanResource;
use App\Http\Resources\Landlord\Tenants\TenantResource;
use App\Models\Landlord\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Subscription $resource
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'plan_id' => $this->plan_id,
            'plan_price_id' => $this->plan_price_id,
            'plan_name' => $this->plan_name,
            'price' => $this->price,
            'currency' => $this->currency,
            'interval' => $this->interval,
            'interval_count' => $this->interval_count,
            'status' => $this->status,
            'is_current' => (bool) $this->is_current,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'trial_ends_at' => $this->trial_ends_at,
            'canceled_at' => $this->canceled_at,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),
            'plan' => new PlanResource($this->whenLoaded('plan')),
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),
        ];
    }
}
