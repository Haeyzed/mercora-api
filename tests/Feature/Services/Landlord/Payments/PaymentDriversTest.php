<?php

use App\Enums\Landlord\PaymentProvider;
use App\Enums\Landlord\PaymentStatus;
use App\Services\Landlord\Payments\DTOs\PaymentInitializationData;
use App\Services\Landlord\Payments\DTOs\WebhookPayload;
use App\Services\Landlord\Payments\Exceptions\PaymentException;
use App\Services\Landlord\Payments\PaymentManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    config([
        'payments.drivers.paystack.secret_key' => 'sk_test_paystack',
        'payments.drivers.paystack.public_key' => 'pk_test_paystack',
        'payments.drivers.paystack.base_url' => 'https://api.paystack.co',
        'payments.drivers.paystack.supported_currencies' => ['NGN', 'USD'],
        'payments.drivers.stripe.secret_key' => 'sk_test_stripe',
        'payments.drivers.stripe.public_key' => 'pk_test_stripe',
        'payments.drivers.stripe.webhook_secret' => 'whsec_test',
        'payments.drivers.stripe.base_url' => 'https://api.stripe.com/v1',
        'payments.drivers.stripe.supported_currencies' => ['USD', 'EUR'],
        'payments.drivers.paypal.client_id' => 'paypal-client',
        'payments.drivers.paypal.client_secret' => 'paypal-secret',
        'payments.drivers.paypal.webhook_id' => 'WH-test',
        'payments.drivers.paypal.base_url' => 'https://api-m.sandbox.paypal.com',
        'payments.drivers.paypal.supported_currencies' => ['USD', 'EUR'],
    ]);
});

describe('PaystackDriver', function () {
    it('initializes a paystack checkout session', function () {
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test',
                    'access_code' => 'access_123',
                    'reference' => 'mercora_paystack_1',
                ],
            ]),
        ]);

        $result = app(PaymentManager::class)->driver('paystack')->initialize(new PaymentInitializationData(
            reference: 'mercora_paystack_1',
            amount: 500000,
            currency: 'NGN',
            email: 'buyer@example.com',
            name: 'Buyer',
            redirectUrl: 'https://app.test/return',
        ));

        expect($result->checkoutUrl)->toBe('https://checkout.paystack.com/test')
            ->and($result->providerReference)->toBe('access_123')
            ->and(app(PaymentManager::class)->driver('paystack')->provider())->toBe(PaymentProvider::Paystack);
    });

    it('verifies paystack webhook signatures with hmac sha512', function () {
        $payload = '{"event":"charge.success","data":{"reference":"ref"}}';
        $signature = hash_hmac('sha512', $payload, 'sk_test_paystack');
        $driver = app(PaymentManager::class)->driver('paystack');

        expect($driver->verifyWebhookSignature($signature, $payload))->toBeTrue()
            ->and($driver->verifyWebhookSignature('bad', $payload))->toBeFalse();
    });
});

describe('StripeDriver', function () {
    it('initializes a stripe checkout session', function () {
        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_123',
                'url' => 'https://checkout.stripe.com/test',
                'payment_status' => 'unpaid',
            ]),
        ]);

        $result = app(PaymentManager::class)->driver('stripe')->initialize(new PaymentInitializationData(
            reference: 'mercora_stripe_1',
            amount: 2900,
            currency: 'USD',
            email: 'buyer@example.com',
            name: 'Buyer',
            redirectUrl: 'https://app.test/return',
            title: 'Invoice INV-1',
        ));

        expect($result->checkoutUrl)->toBe('https://checkout.stripe.com/test')
            ->and($result->providerReference)->toBe('cs_test_123')
            ->and(app(PaymentManager::class)->driver('stripe')->provider())->toBe(PaymentProvider::Stripe);
    });

    it('normalizes a paid checkout session webhook', function () {
        $driver = app(PaymentManager::class)->driver('stripe');

        $result = $driver->normalizeWebhook(new WebhookPayload(
            rawPayload: '{}',
            body: [
                'type' => 'checkout.session.completed',
                'data' => [
                    'object' => [
                        'id' => 'cs_test_123',
                        'client_reference_id' => 'mercora_stripe_1',
                        'payment_status' => 'paid',
                        'status' => 'complete',
                        'amount_total' => 2900,
                        'currency' => 'usd',
                    ],
                ],
            ],
            signature: null,
            eventType: 'checkout.session.completed',
            eventId: 'evt_1',
        ));

        expect($result->status)->toBe(PaymentStatus::Successful)
            ->and($result->reference)->toBe('mercora_stripe_1')
            ->and($result->amount)->toBe(2900)
            ->and($result->currency)->toBe('USD');
    });

    it('rejects stripe amount mismatches on success', function () {
        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions*' => Http::response([
                'data' => [[
                    'id' => 'cs_test_123',
                    'client_reference_id' => 'mercora_stripe_1',
                    'payment_status' => 'paid',
                    'status' => 'complete',
                    'amount_total' => 100,
                    'currency' => 'usd',
                ]],
            ]),
        ]);

        $driver = app(PaymentManager::class)->driver('stripe');

        expect(fn () => $driver->verify('mercora_stripe_1', 2900, 'USD'))
            ->toThrow(PaymentException::class);
    });
});

describe('PaypalDriver', function () {
    it('initializes a paypal checkout order', function () {
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-access-token',
                'expires_in' => 3600,
            ]),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'ORDER-123',
                'status' => 'PAYER_ACTION_REQUIRED',
                'links' => [
                    ['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER-123'],
                ],
            ]),
        ]);

        $result = app(PaymentManager::class)->driver('paypal')->initialize(new PaymentInitializationData(
            reference: 'mercora_paypal_1',
            amount: 2900,
            currency: 'USD',
            email: 'buyer@example.com',
            name: 'Buyer',
            redirectUrl: 'https://app.test/return',
            title: 'Invoice INV-1',
        ));

        expect($result->checkoutUrl)->toBe('https://www.sandbox.paypal.com/checkoutnow?token=ORDER-123')
            ->and($result->providerReference)->toBe('ORDER-123')
            ->and(app(PaymentManager::class)->driver('paypal')->provider())->toBe(PaymentProvider::Paypal);
    });

    it('normalizes a completed paypal order webhook', function () {
        $driver = app(PaymentManager::class)->driver('paypal');

        $result = $driver->normalizeWebhook(new WebhookPayload(
            rawPayload: '{}',
            body: [
                'event_type' => 'CHECKOUT.ORDER.COMPLETED',
                'resource' => [
                    'id' => 'ORDER-123',
                    'status' => 'COMPLETED',
                    'purchase_units' => [[
                        'custom_id' => 'mercora_paypal_1',
                        'amount' => [
                            'currency_code' => 'USD',
                            'value' => '29.00',
                        ],
                    ]],
                ],
            ],
            signature: null,
            eventType: 'CHECKOUT.ORDER.COMPLETED',
            eventId: 'WH-1',
        ));

        expect($result->status)->toBe(PaymentStatus::Successful)
            ->and($result->reference)->toBe('mercora_paypal_1')
            ->and($result->amount)->toBe(2900)
            ->and($result->currency)->toBe('USD');
    });
});

it('accepts the unified payment webhook route for paystack', function () {
    $payload = ['event' => 'charge.success', 'data' => ['id' => 1, 'reference' => 'x']];
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha512', $raw, 'sk_test_paystack');

    $this->call(
        'POST',
        '/api/webhooks/payments/paystack',
        $payload,
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-PAYSTACK-SIGNATURE' => $signature,
        ],
        $raw,
    )->assertNoContent();
});
