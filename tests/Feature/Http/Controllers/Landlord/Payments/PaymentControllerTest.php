<?php

use App\Enums\Landlord\InvoiceStatus;
use App\Enums\Landlord\PaymentStatus;
use App\Models\Landlord\Invoice;
use App\Models\Landlord\Payment;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    configureFlutterwaveForTests();
});

describe('show', function () {
    it('returns a payment without provider response payload', function () {
        $payment = Payment::factory()->create([
            'status' => PaymentStatus::Pending,
            'provider_response' => ['secret' => 'hidden'],
        ]);

        $response = $this->getJson("/api/landlord/payments/{$payment->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $payment->id);

        expect($response->json('data.provider_response'))->toBeNull();
    });
});

describe('verify', function () {
    it('verifies a pending payment and marks the invoice paid', function () {
        $invoice = Invoice::factory()->create([
            'status' => InvoiceStatus::Open,
            'amount' => 2900,
            'currency' => 'USD',
        ]);
        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'subscription_id' => $invoice->subscription_id,
            'tenant_id' => $invoice->tenant_id,
            'status' => PaymentStatus::Pending,
            'amount' => 2900,
            'currency' => 'USD',
        ]);

        fakeFlutterwaveVerify($payment->reference, 2900, 'USD');

        $this->postJson("/api/landlord/payments/{$payment->id}/verify")
            ->assertOk()
            ->assertJsonPath('data.status', 'successful');

        expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
    });
});
