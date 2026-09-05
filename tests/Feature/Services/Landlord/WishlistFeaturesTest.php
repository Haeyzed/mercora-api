<?php

use App\Enums\Landlord\PaymentStatus;
use App\Enums\Landlord\SubscriptionStatus;
use App\Models\Landlord\Payment;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\User;
use App\Services\Landlord\InvoiceService;
use App\Services\Landlord\Payments\PaymentService;
use App\Services\Landlord\SettingService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    actingAsLandlord();
    configureFlutterwaveForTests();
});

describe('payment refunds', function () {
    it('refunds a successful payment via the provider', function () {
        Http::fake([
            'https://api.flutterwave.com/v3/transactions/*/refund' => Http::response([
                'status' => 'success',
                'data' => ['id' => 'rf_1'],
            ]),
        ]);

        $payment = Payment::factory()->create([
            'provider' => 'flutterwave',
            'status' => PaymentStatus::Successful,
            'provider_reference' => '12345',
            'amount' => 2900,
            'currency' => 'USD',
            'paid_at' => now(),
        ]);

        $refunded = app(PaymentService::class)->refund($payment, 'Customer request');

        expect($refunded->status)->toBe(PaymentStatus::Refunded)
            ->and($refunded->refunded_at)->not->toBeNull();
    });

    it('rejects refunds for non-successful payments', function () {
        $payment = Payment::factory()->create([
            'status' => PaymentStatus::Pending,
        ]);

        $this->postJson("/api/landlord/payments/{$payment->id}/refund")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    });
});

describe('invoice tax', function () {
    it('adds exclusive tax when tax is enabled', function () {
        app(SettingService::class)->updateDomain('billing', [
            'billing.tax_enabled' => true,
            'billing.default_tax_rate' => '10',
            'billing.tax_inclusive' => false,
            'billing.company_name' => 'Mercora Inc',
        ]);

        $subscription = Subscription::factory()->create([
            'price' => 10000,
            'currency' => 'USD',
            'status' => SubscriptionStatus::Active,
        ]);

        $invoice = app(InvoiceService::class)->issueFor(
            $subscription,
            now(),
            now()->addMonth(),
        );

        expect($invoice->subtotal)->toBe(10000)
            ->and($invoice->tax_amount)->toBe(1000)
            ->and($invoice->amount)->toBe(11000)
            ->and($invoice->tax_inclusive)->toBeFalse()
            ->and($invoice->seller['name'] ?? null)->toBe('Mercora Inc');
    });
});

describe('self-serve registration', function () {
    it('registers a user and tenant when enabled', function () {
        app(SettingService::class)->updateDomain('registration', [
            'registration.tenant_registration_enabled' => true,
            'registration.require_terms_acceptance' => true,
            'registration.require_email_verification' => false,
            'registration.default_plan_slug' => null,
            'registration.send_welcome_email' => false,
        ]);

        $this->postJson('/api/landlord/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'tenant_name' => 'Analytical Engines',
            'domain' => 'analytical.example.test',
            'terms_accepted' => true,
        ])->assertOk()
            ->assertJsonPath('data.user.email', 'ada@example.com')
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
        $this->assertDatabaseHas('tenants', ['name' => 'Analytical Engines']);
    });

    it('rejects registration when disabled', function () {
        app(SettingService::class)->updateDomain('registration', [
            'registration.tenant_registration_enabled' => false,
        ]);

        $this->postJson('/api/landlord/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada2@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'tenant_name' => 'Blocked Co',
            'domain' => 'blocked.example.test',
            'terms_accepted' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });
});

describe('gdpr personal data', function () {
    it('exports personal data for the authenticated user', function () {
        $response = $this->getJson('/api/landlord/auth/personal-data')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['exported_at', 'profile', 'roles', 'notices', 'api_keys', 'activities'],
            ]);

        expect($response->json('data.profile.email'))->not->toBeEmpty();
    });

    it('erases and soft-deletes the authenticated user', function () {
        $user = User::factory()->create();
        actingAsLandlord($user, superAdmin: false);

        $this->deleteJson('/api/landlord/auth/personal-data')
            ->assertNoContent();

        expect(User::withTrashed()->find($user->id)?->trashed())->toBeTrue()
            ->and(User::withTrashed()->find($user->id)?->email)->toContain('@erased.invalid');
    });
});
