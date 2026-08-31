<?php

use App\Enums\Landlord\InvoiceStatus;
use App\Enums\Landlord\PaymentStatus;
use App\Enums\Landlord\SubscriptionStatus;
use App\Models\Landlord\Invoice;
use App\Models\Landlord\Payment;
use App\Models\Landlord\PaymentWebhookEvent;
use App\Models\Landlord\Subscription;
use App\Services\Landlord\Payments\Exceptions\PaymentException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    configureFlutterwaveForTests();
});

it('rejects webhooks with invalid signatures', function () {
    $this->withoutExceptionHandling();

    expect(fn () => $this->postJson('/api/webhooks/flutterwave', [
        'event' => 'charge.completed',
        'data' => ['id' => '1', 'tx_ref' => 'mercora_test', 'status' => 'successful'],
    ], ['verif-hash' => 'wrong']))
        ->toThrow(PaymentException::class);

    expect(PaymentWebhookEvent::query()->count())->toBe(0);
});

it('processes valid webhooks idempotently', function () {
    $subscription = Subscription::factory()->create([
        'status' => SubscriptionStatus::PendingPayment,
        'ends_at' => now(),
    ]);

    $invoice = Invoice::factory()->for($subscription)->create([
        'amount' => 2900,
        'currency' => 'USD',
        'period_ends_at' => now()->addMonth(),
    ]);

    $payment = Payment::factory()->create([
        'tenant_id' => $subscription->tenant_id,
        'subscription_id' => $subscription->id,
        'invoice_id' => $invoice->id,
        'amount' => 2900,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
    ]);

    fakeFlutterwaveVerify($payment->reference, 2900, 'USD');

    $payload = [
        'event' => 'charge.completed',
        'data' => [
            'id' => 'event-123',
            'tx_ref' => $payment->reference,
            'status' => 'successful',
            'amount' => 29,
            'currency' => 'USD',
        ],
    ];

    $this->postJson('/api/webhooks/flutterwave', $payload, ['verif-hash' => 'test-hash'])
        ->assertNoContent();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Successful)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);

    $this->postJson('/api/webhooks/flutterwave', $payload, ['verif-hash' => 'test-hash'])
        ->assertNoContent();

    expect(PaymentWebhookEvent::query()->count())->toBe(1)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('does not renew subscription when webhook verification fails', function () {
    $subscription = Subscription::factory()->create([
        'status' => SubscriptionStatus::PendingPayment,
        'ends_at' => now(),
    ]);

    $invoice = Invoice::factory()->for($subscription)->create([
        'amount' => 2900,
        'currency' => 'USD',
    ]);

    $payment = Payment::factory()->create([
        'tenant_id' => $subscription->tenant_id,
        'subscription_id' => $subscription->id,
        'invoice_id' => $invoice->id,
        'amount' => 2900,
        'currency' => 'USD',
        'status' => PaymentStatus::Pending,
    ]);

    Http::fake([
        'https://api.flutterwave.com/v3/transactions/verify_by_reference*' => Http::response([
            'status' => 'success',
            'data' => [
                'tx_ref' => $payment->reference,
                'status' => 'failed',
                'amount' => 29,
                'currency' => 'USD',
            ],
        ]),
    ]);

    $this->postJson('/api/webhooks/flutterwave', [
        'event' => 'charge.completed',
        'data' => [
            'id' => 'event-fail',
            'tx_ref' => $payment->reference,
            'status' => 'successful',
            'amount' => 29,
            'currency' => 'USD',
        ],
    ], ['verif-hash' => 'test-hash'])->assertNoContent();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Open)
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::PendingPayment);
});
