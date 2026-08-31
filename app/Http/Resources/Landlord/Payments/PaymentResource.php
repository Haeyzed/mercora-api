<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\Payments;

use App\Models\Landlord\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Payment $resource
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'provider' => $this->provider,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payment_method' => $this->payment_method,
            'checkout_url' => $this->when($this->status->value === 'pending', $this->checkout_url),
            'paid_at' => $this->paid_at,
            'failed_at' => $this->failed_at,
            'invoice_id' => $this->invoice_id,
            'subscription_id' => $this->subscription_id,
            'tenant_id' => $this->tenant_id,
        ];
    }
}
