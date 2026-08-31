<?php

declare(strict_types=1);

namespace Database\Factories\Landlord;

use App\Enums\Landlord\InvoiceStatus;
use App\Models\Landlord\Invoice;
use App\Models\Landlord\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterMaking(function (Invoice $invoice): void {
            if ($invoice->tenant_id !== null || $invoice->subscription_id === null) {
                return;
            }

            $invoice->tenant_id = Subscription::query()->whereKey($invoice->subscription_id)->value('tenant_id');
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'number' => 'INV-'.fake()->unique()->numerify('######'),
            'status' => InvoiceStatus::Open,
            'amount' => 2900,
            'currency' => 'USD',
            'issued_at' => now(),
            'due_at' => null,
            'paid_at' => null,
            'voided_at' => null,
            'notes' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function void(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InvoiceStatus::Void,
            'voided_at' => now(),
        ]);
    }
}
