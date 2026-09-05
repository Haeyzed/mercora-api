<?php

use App\Enums\Landlord\InvoiceStatus;
use App\Enums\Landlord\PaymentStatus;
use App\Enums\Landlord\SubscriptionStatus;
use App\Enums\Landlord\TenantStatus;
use App\Models\Landlord\Invoice;
use App\Models\Landlord\Notice;
use App\Models\Landlord\Payment;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\Tenant;
use App\Services\Landlord\Payments\PaymentService;
use App\Services\Landlord\SettingService;
use App\Services\Landlord\SubscriptionService;
use App\Services\Landlord\Tenants\TenantService;
use Database\Seeders\Landlord\NotificationTemplateSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    actingAsLandlord();
    configureFlutterwaveForTests();
    $this->seed(NotificationTemplateSeeder::class);
});

it('creates billing notices when a payment succeeds', function () {
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

    expect(Notice::query()->where('title', 'Payment successful')->count())->toBeGreaterThan(0);
});

it('skips payment success notices when billing alerts are disabled', function () {
    app(SettingService::class)->updateDomain('notifications', [
        'notifications.billing_alerts' => false,
    ]);

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

    expect(Notice::query()->where('title', 'Payment successful')->count())->toBe(0)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('creates a past due billing notice once when a period ends', function () {
    $this->travelTo('2026-09-29 20:00:00');

    $subscription = Subscription::factory()->create([
        'status' => SubscriptionStatus::Active,
        'ends_at' => now(),
    ]);

    Artisan::call('landlord:process-subscriptions');

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::PastDue)
        ->and(Notice::query()->where('title', 'Subscription past due')->count())->toBeGreaterThan(0);

    $noticeCount = Notice::query()->where('title', 'Subscription past due')->count();

    Artisan::call('landlord:process-subscriptions');

    expect(Notice::query()->where('title', 'Subscription past due')->count())->toBe($noticeCount);
});

it('creates a tenant lifecycle notice when a tenant is suspended', function () {
    $tenant = Tenant::factory()->active()->create();

    app(TenantService::class)->suspend($tenant);

    expect($tenant->fresh()->status)->toBe(TenantStatus::Suspended)
        ->and(Notice::query()->where('title', 'Tenant suspended')->count())->toBeGreaterThan(0);
});

it('skips suspend notices when tenant lifecycle alerts are disabled', function () {
    app(SettingService::class)->updateDomain('notifications', [
        'notifications.tenant_lifecycle_alerts' => false,
    ]);

    $tenant = Tenant::factory()->active()->create();

    app(TenantService::class)->suspend($tenant);

    expect($tenant->fresh()->status)->toBe(TenantStatus::Suspended)
        ->and(Notice::query()->where('title', 'Tenant suspended')->count())->toBe(0);
});

it('creates a billing notice when a subscription is canceled immediately', function () {
    app(SettingService::class)->updateDomain('subscriptions', [
        'subscriptions.cancel_at_period_end' => false,
        'subscriptions.allow_immediate_cancel' => true,
    ]);

    $subscription = Subscription::factory()->create([
        'status' => SubscriptionStatus::Active,
    ]);

    app(SubscriptionService::class)->cancel($subscription);

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Canceled)
        ->and(Notice::query()->where('title', 'Subscription canceled')->count())->toBeGreaterThan(0);
});
