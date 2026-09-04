<?php

use App\Enums\Landlord\InvoiceStatus;
use App\Enums\Landlord\PaymentStatus;
use App\Enums\Landlord\PlanInterval;
use App\Enums\Landlord\SubscriptionStatus;
use App\Enums\Landlord\TenantStatus;
use App\Models\Landlord\Invoice;
use App\Models\Landlord\Payment;
use App\Models\Landlord\Plan;
use App\Models\Landlord\PlanPrice;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\Tenant;
use App\Services\Landlord\Payments\Exceptions\PaymentException;
use App\Services\Landlord\Payments\PaymentService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    actingAsLandlord();
    configureFlutterwaveForTests();
});

describe('payment initialization', function () {
    it('initializes flutterwave payment for an open invoice', function () {
        fakeFlutterwaveInitialize();

        $invoice = Invoice::factory()->create(['amount' => 2900, 'currency' => 'USD']);

        $this->postJson("/api/landlord/invoices/{$invoice->id}/pay")
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.provider', 'flutterwave')
            ->assertJsonPath('data.checkout_url', 'https://checkout.test/pay');

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount' => 2900,
            'currency' => 'USD',
            'status' => PaymentStatus::Pending->value,
        ]);
    });

    it('rejects unsupported currencies before calling the provider', function () {
        $invoice = Invoice::factory()->create(['amount' => 1000, 'currency' => 'XYZ']);

        $this->postJson("/api/landlord/invoices/{$invoice->id}/pay")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment']);

        Http::assertNothingSent();
    });

    it('derives payable amount from the invoice not the client', function () {
        fakeFlutterwaveInitialize();

        $invoice = Invoice::factory()->create(['amount' => 5000, 'currency' => 'USD']);

        Http::fake([
            'https://api.flutterwave.com/v3/payments' => function ($request) {
                expect($request['amount'])->toBe(50.0);

                return Http::response([
                    'status' => 'success',
                    'data' => ['id' => '1', 'link' => 'https://checkout.test/pay'],
                ]);
            },
        ]);

        $this->postJson("/api/landlord/invoices/{$invoice->id}/pay", [
            'amount' => 1,
            'currency' => 'NGN',
        ])->assertCreated();

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount' => 5000,
            'currency' => 'USD',
        ]);
    });
});

describe('payment verification', function () {
    it('marks invoice paid and renews subscription after successful verification', function () {
        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::PendingPayment,
            'ends_at' => now(),
        ]);

        $invoice = Invoice::factory()->for($subscription)->create([
            'amount' => $subscription->price,
            'currency' => $subscription->currency,
            'period_ends_at' => now()->addMonth(),
        ]);

        $payment = Payment::factory()->create([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'amount' => $invoice->amount,
            'currency' => $invoice->currency,
            'status' => PaymentStatus::Pending,
        ]);

        fakeFlutterwaveVerify($payment->reference, $payment->amount, $payment->currency);

        app(PaymentService::class)->verify($payment);

        expect($payment->fresh()->status)->toBe(PaymentStatus::Successful)
            ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
            ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
            ->and($subscription->fresh()->ends_at->equalTo($invoice->period_ends_at))->toBeTrue();
    });

    it('reactivates a suspended tenant after successful payment verification', function () {
        $tenant = Tenant::factory()->suspended()->create();

        $subscription = Subscription::factory()->for($tenant)->create([
            'status' => SubscriptionStatus::PastDue,
            'ends_at' => now()->subDays(20),
        ]);

        $invoice = Invoice::factory()->for($subscription)->create([
            'tenant_id' => $tenant->id,
            'amount' => $subscription->price,
            'currency' => $subscription->currency,
            'period_ends_at' => now()->addMonth(),
        ]);

        $payment = Payment::factory()->create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'amount' => $invoice->amount,
            'currency' => $invoice->currency,
            'status' => PaymentStatus::Pending,
        ]);

        fakeFlutterwaveVerify($payment->reference, $payment->amount, $payment->currency);

        app(PaymentService::class)->verify($payment);

        expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
            ->and($tenant->fresh()->status)->toBe(TenantStatus::Active);
    });

    it('rejects amount mismatches during verification', function () {
        $payment = Payment::factory()->create([
            'amount' => 2900,
            'currency' => 'USD',
            'status' => PaymentStatus::Pending,
        ]);

        fakeFlutterwaveVerify($payment->reference, 100, 'USD');

        expect(fn () => app(PaymentService::class)->verify($payment))
            ->toThrow(PaymentException::class);
    });
});

describe('plan price selection', function () {
    it('allows multiple active prices per plan', function () {
        $plan = Plan::factory()->active()->create();

        $monthly = PlanPrice::factory()->for($plan)->create([
            'currency' => 'NGN',
            'amount' => 2500000,
            'interval' => PlanInterval::Monthly,
        ]);

        $yearly = PlanPrice::factory()->for($plan)->create([
            'currency' => 'NGN',
            'amount' => 25000000,
            'interval' => PlanInterval::Yearly,
        ]);

        expect($plan->prices)->toHaveCount(3)
            ->and($monthly->amount)->toBe(2500000)
            ->and($yearly->interval)->toBe(PlanInterval::Yearly);
    });

    it('snapshots selected plan price on subscription creation', function () {
        $this->travelTo('2026-08-29 20:00:00');

        $plan = Plan::factory()->active()->create();
        $price = $plan->prices()->firstOrFail();

        $tenant = Tenant::factory()->create();

        $this->postJson('/api/landlord/subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'plan_price_id' => $price->id,
        ])->assertCreated()
            ->assertJsonPath('data.plan_price_id', $price->id)
            ->assertJsonPath('data.price', $price->amount);
    });
});
