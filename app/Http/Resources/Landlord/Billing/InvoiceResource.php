<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\Billing;

use App\Http\Resources\Landlord\Subscriptions\SubscriptionResource;
use App\Http\Resources\Landlord\Tenants\TenantResource;
use App\Models\Landlord\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Invoice $resource
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'subscription_id' => $this->subscription_id,
            'number' => $this->number,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'issued_at' => $this->issued_at,
            'period_starts_at' => $this->period_starts_at,
            'period_ends_at' => $this->period_ends_at,
            'due_at' => $this->due_at,
            'paid_at' => $this->paid_at,
            'voided_at' => $this->voided_at,
            'notes' => $this->notes,
            'tenant' => new TenantResource($this->whenLoaded('tenant')),
            'subscription' => new SubscriptionResource($this->whenLoaded('subscription')),
        ];
    }
}
