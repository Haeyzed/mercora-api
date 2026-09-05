<?php

declare(strict_types=1);

namespace App\Services\Landlord\Payments\Drivers\Stripe;

use App\Enums\Landlord\PaymentProvider;
use App\Enums\Landlord\PaymentStatus;
use App\Services\Landlord\Payments\Contracts\PaymentDriver;
use App\Services\Landlord\Payments\DTOs\PaymentInitializationData;
use App\Services\Landlord\Payments\DTOs\PaymentInitializationResult;
use App\Services\Landlord\Payments\DTOs\PaymentVerificationResult;
use App\Services\Landlord\Payments\DTOs\WebhookPayload;
use App\Services\Landlord\Payments\Exceptions\PaymentException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Stripe Checkout Session payment driver.
 *
 * Uses Stripe's REST API via HTTP (no SDK). Application amounts are already in
 * minor units, matching Stripe's unit_amount. Webhook signatures follow the
 * Stripe-Signature scheme (t=...,v1=...).
 */
class StripeDriver implements PaymentDriver
{
    /**
     * @param  array<string, mixed>  $config  Driver config from {@see config('payments.drivers.stripe')}.
     */
    public function __construct(private array $config) {}

    public function provider(): PaymentProvider
    {
        return PaymentProvider::Stripe;
    }

    /**
     * @return list<string>
     */
    public function supportedCurrencies(): array
    {
        return $this->config['supported_currencies'] ?? [];
    }

    public function supportsCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), $this->supportedCurrencies(), true);
    }

    public function supportsPaymentMethod(string $method): bool
    {
        return in_array($method, ['card', 'bank_transfer'], true);
    }

    /**
     * @throws PaymentException
     */
    public function initialize(PaymentInitializationData $data): PaymentInitializationResult
    {
        if (! $this->supportsCurrency($data->currency)) {
            throw PaymentException::unsupportedCurrency($data->currency);
        }

        $productName = ($data->title !== null && $data->title !== '')
            ? $data->title
            : 'Invoice payment';

        $payload = [
            'mode' => 'payment',
            'success_url' => $this->redirectWithPlaceholder($data->redirectUrl, 'success'),
            'cancel_url' => $this->redirectWithPlaceholder($data->redirectUrl, 'cancel'),
            'client_reference_id' => $data->reference,
            'customer_email' => $data->email,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($data->currency),
                    'unit_amount' => $data->amount,
                    'product_data' => [
                        'name' => $productName,
                    ],
                ],
            ]],
            'metadata' => [
                ...$this->stringifyMetadata($data->metadata),
                'merchant_reference' => $data->reference,
                'customer_name' => $data->name,
            ],
            'payment_intent_data' => [
                'metadata' => [
                    'merchant_reference' => $data->reference,
                ],
            ],
        ];

        if ($data->paymentMethod === 'card') {
            $payload['payment_method_types'] = ['card'];
        }

        $response = $this->client()->asForm()->post('/checkout/sessions', $this->flatten($payload));

        if (! $response->successful()) {
            throw PaymentException::initializationFailed($this->safeMessage($response->json('error.message')));
        }

        $url = $response->json('url');

        return new PaymentInitializationResult(
            reference: $data->reference,
            providerReference: (string) ($response->json('id') ?? ''),
            status: PaymentStatus::Pending,
            checkoutUrl: is_string($url) ? $url : null,
            providerResponse: $response->json() ?? [],
        );
    }

    /**
     * @throws PaymentException
     */
    public function verify(string $reference, int $expectedAmount, string $expectedCurrency): PaymentVerificationResult
    {
        $response = $this->client()->get('/checkout/sessions', [
            'client_reference_id' => $reference,
            'limit' => 1,
        ]);

        if (! $response->successful()) {
            throw PaymentException::verificationFailed('Payment could not be verified.');
        }

        $session = $response->json('data.0');

        if (! is_array($session)) {
            throw PaymentException::verificationFailed('Payment could not be verified.');
        }

        return $this->normalizeSession($session, $expectedAmount, $expectedCurrency, $reference);
    }

    /**
     * @throws PaymentException
     */
    public function refund(string $reference, int $amount, string $currency, ?string $reason = null): PaymentVerificationResult
    {
        $response = $this->client()->asForm()->post('/refunds', array_filter([
            'payment_intent' => $reference,
            'amount' => $amount,
            'reason' => $reason !== null && $reason !== '' ? 'requested_by_customer' : null,
            'metadata[merchant_note]' => $reason,
        ], fn (mixed $value): bool => $value !== null && $value !== ''));

        if (! $response->successful()) {
            throw PaymentException::verificationFailed((string) ($response->json('error.message') ?? 'Stripe refund failed.'));
        }

        $data = $response->json() ?? [];

        return new PaymentVerificationResult(
            reference: $reference,
            providerReference: isset($data['id']) ? (string) $data['id'] : $reference,
            status: PaymentStatus::Refunded,
            amount: $amount,
            currency: strtoupper($currency),
            paymentMethod: null,
            providerResponse: is_array($data) ? $data : [],
        );
    }

    public function verifyWebhookSignature(string $signature, string $payload): bool
    {
        $secret = $this->config['webhook_secret'] ?? null;

        if (! is_string($secret) || $secret === '' || $signature === '') {
            return false;
        }

        $parts = [];

        foreach (explode(',', $signature) as $item) {
            [$key, $value] = array_pad(explode('=', trim($item), 2), 2, null);

            if ($key !== null && $value !== null) {
                $parts[$key][] = $value;
            }
        }

        $timestamp = $parts['t'][0] ?? null;
        $signatures = $parts['v1'] ?? [];

        if ($timestamp === null || $signatures === []) {
            return false;
        }

        $tolerance = (int) ($this->config['webhook_tolerance'] ?? 300);

        if (abs(time() - (int) $timestamp) > $tolerance) {
            return false;
        }

        $signedPayload = $timestamp.'.'.$payload;
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        foreach ($signatures as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws PaymentException
     */
    public function normalizeWebhook(WebhookPayload $payload): PaymentVerificationResult
    {
        $object = $payload->body['data']['object'] ?? null;

        if (! is_array($object)) {
            throw PaymentException::verificationFailed('Invalid webhook payload.');
        }

        $type = (string) ($payload->eventType ?? $payload->body['type'] ?? '');

        if (str_starts_with($type, 'checkout.session.')) {
            $reference = (string) ($object['client_reference_id'] ?? $object['metadata']['merchant_reference'] ?? '');
            $amount = (int) ($object['amount_total'] ?? 0);
            $currency = strtoupper((string) ($object['currency'] ?? ''));

            return $this->normalizeSession($object, $amount, $currency, $reference);
        }

        if (str_starts_with($type, 'payment_intent.')) {
            $reference = (string) ($object['metadata']['merchant_reference'] ?? '');
            $amount = (int) ($object['amount_received'] ?? $object['amount'] ?? 0);
            $currency = strtoupper((string) ($object['currency'] ?? ''));

            return $this->normalizePaymentIntent($object, $amount, $currency, $reference);
        }

        throw PaymentException::verificationFailed('Unsupported Stripe webhook event.');
    }

    /**
     * @param  array<string, mixed>  $session
     *
     * @throws PaymentException
     */
    private function normalizeSession(array $session, int $expectedAmount, string $expectedCurrency, string $reference): PaymentVerificationResult
    {
        $paymentStatus = match ((string) ($session['payment_status'] ?? '')) {
            'paid' => PaymentStatus::Successful,
            'unpaid' => PaymentStatus::Pending,
            'no_payment_required' => PaymentStatus::Successful,
            default => match ((string) ($session['status'] ?? '')) {
                'complete' => PaymentStatus::Successful,
                'expired' => PaymentStatus::Cancelled,
                default => PaymentStatus::Pending,
            },
        };

        $amountMinor = (int) ($session['amount_total'] ?? $expectedAmount);
        $currency = strtoupper((string) ($session['currency'] ?? $expectedCurrency));

        if ($paymentStatus === PaymentStatus::Successful) {
            if ($amountMinor !== $expectedAmount || $currency !== strtoupper($expectedCurrency)) {
                throw PaymentException::verificationFailed('Payment amount or currency mismatch.');
            }
        }

        return new PaymentVerificationResult(
            reference: $reference !== '' ? $reference : (string) ($session['client_reference_id'] ?? ''),
            providerReference: isset($session['id']) ? (string) $session['id'] : null,
            status: $paymentStatus,
            amount: $amountMinor,
            currency: $currency,
            paymentMethod: 'card',
            providerResponse: $session,
        );
    }

    /**
     * @param  array<string, mixed>  $intent
     *
     * @throws PaymentException
     */
    private function normalizePaymentIntent(array $intent, int $expectedAmount, string $expectedCurrency, string $reference): PaymentVerificationResult
    {
        $paymentStatus = match ((string) ($intent['status'] ?? '')) {
            'succeeded' => PaymentStatus::Successful,
            'canceled' => PaymentStatus::Cancelled,
            'requires_payment_method', 'requires_confirmation', 'requires_action', 'processing' => PaymentStatus::Pending,
            default => PaymentStatus::Failed,
        };

        $amountMinor = (int) ($intent['amount_received'] ?? $intent['amount'] ?? 0);
        $currency = strtoupper((string) ($intent['currency'] ?? ''));

        if ($paymentStatus === PaymentStatus::Successful) {
            if ($amountMinor !== $expectedAmount || $currency !== strtoupper($expectedCurrency)) {
                throw PaymentException::verificationFailed('Payment amount or currency mismatch.');
            }
        }

        return new PaymentVerificationResult(
            reference: $reference,
            providerReference: isset($intent['id']) ? (string) $intent['id'] : null,
            status: $paymentStatus,
            amount: $amountMinor,
            currency: $currency,
            paymentMethod: isset($intent['payment_method_types'][0]) ? (string) $intent['payment_method_types'][0] : null,
            providerResponse: $intent,
        );
    }

    private function redirectWithPlaceholder(?string $url, string $status): string
    {
        $base = is_string($url) && $url !== ''
            ? $url
            : (string) config('app.url');

        $separator = str_contains($base, '?') ? '&' : '?';

        return $base.$separator.'checkout='.$status.'&session_id={CHECKOUT_SESSION_ID}';
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, string>
     */
    private function stringifyMetadata(array $metadata): array
    {
        $result = [];

        foreach ($metadata as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $result[(string) $key] = (string) $value;
            }
        }

        return $result;
    }

    /**
     * Flatten nested arrays into Stripe form-encoded keys (e.g. line_items[0][price_data][currency]).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function flatten(array $payload, string $prefix = ''): array
    {
        $result = [];

        foreach ($payload as $key => $value) {
            $formKey = $prefix === '' ? (string) $key : $prefix.'['.$key.']';

            if (is_array($value)) {
                $result += $this->flatten($value, $formKey);
            } else {
                $result[$formKey] = $value;
            }
        }

        return $result;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) ($this->config['base_url'] ?? ''), '/'))
            ->withToken((string) ($this->config['secret_key'] ?? ''))
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 100, fn (\Throwable $e) => $e instanceof ConnectionException);
    }

    private function safeMessage(mixed $message): string
    {
        return is_string($message) && $message !== ''
            ? $message
            : 'Payment could not be initialized.';
    }
}
