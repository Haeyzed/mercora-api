<?php

declare(strict_types=1);

namespace Database\Factories\Landlord;

use App\Enums\Landlord\PaymentProvider;
use App\Enums\Landlord\PaymentStatus;
use App\Models\Landlord\Invoice;
use App\Models\Landlord\Payment;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'subscription_id' => Subscription::factory(),
            'invoice_id' => Invoice::factory(),
            'provider' => PaymentProvider::Flutterwave,
            'reference' => 'mercora_'.Str::lower(Str::random(20)),
            'amount' => 2900,
            'currency' => 'USD',
            'status' => PaymentStatus::Pending,
        ];
    }

    public function successful(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Successful,
            'paid_at' => now(),
        ]);
    }
}
