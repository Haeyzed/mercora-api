<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\Tenants;

use App\Http\Resources\Landlord\Billing\InvoiceResource;
use App\Http\Resources\Landlord\Subscriptions\SubscriptionResource;
use App\Models\Landlord\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Tenant $resource
 */
class TenantResource extends JsonResource
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
            'status' => $this->status,
            'provisioned_at' => $this->provisioned_at,
            'domains' => DomainResource::collection($this->whenLoaded('domains')),
            'subscriptions' => SubscriptionResource::collection($this->whenLoaded('subscriptions')),
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),
        ];
    }
}
