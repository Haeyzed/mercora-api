<?php

declare(strict_types=1);

namespace App\Services\Landlord\Payments\Drivers\Paystack;

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
 * Paystack payment driver.
 *
 * Amounts stay in minor units (kobo/cents) for both the application and Paystack APIs.
 * Webhook signatures use HMAC SHA-512 of the raw body with the secret key.
 */
class PaystackDriver implements PaymentDriver
{
    /**
     * @param  array<string, mixed>  $config  Driver config from {@see config('payments.drivers.paystack')}.
     */
    public function __construct(private array $config) {}

    public function provider(): PaymentProvider
    {
        return PaymentProvider::Paystack;
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
        return in_array($method, ['card', 'bank_transfer', 'ussd', 'qr', 'mobile_money'], true);
    }

    /**
     * @throws PaymentException
     */
    public function initialize(PaymentInitializationData $data): PaymentInitializationResult
    {
        if (! $this->supportsCurrency($data->currency)) {
            throw PaymentException::unsupportedCurrency($data->currency);
        }

        $payload = [
            'email' => $data->email,
            'amount' => $data->amount,
            'currency' => strtoupper($data->currency),
            'reference' => $data->reference,
            'callback_url' => $data->redirectUrl,
            'metadata' => [
                ...$data->metadata,
                'customer_name' => $data->name,
                'custom_fields' => array_values(array_filter([
                    $data->title !== null && $data->title !== ''
                        ? ['display_name' => 'Description', 'variable_name' => 'description', 'value' => $data->title]
                        : null,
                ])),
            ],
        ];

        if ($data->paymentMethod !== null) {
            $payload['channels'] = [$this->mapPaymentMethod($data->paymentMethod)];
        }

        $response = $this->client()->post('/transaction/initialize', $payload);

        if (! $response->successful() || $response->json('status') !== true) {
            throw PaymentException::initializationFailed($this->safeMessage($response->json('message')));
        }

        $authorizationUrl = $response->json('data.authorization_url');

        return new PaymentInitializationResult(
            reference: $data->reference,
            providerReference: (string) ($response->json('data.access_code') ?? $data->reference),
            status: PaymentStatus::Pending,
            checkoutUrl: is_string($authorizationUrl) ? $authorizationUrl : null,
            providerResponse: $response->json() ?? [],
        );
    }

    /**
     * @throws PaymentException
     */
    public function verify(string $reference, int $expectedAmount, string $expectedCurrency): PaymentVerificationResult
    {
        $response = $this->client()->get('/transaction/verify/'.rawurlencode($reference));

        if (! $response->successful() || $response->json('status') !== true) {
            throw PaymentException::verificationFailed('Payment could not be verified.');
        }

        $data = $response->json('data') ?? [];

        if (! is_array($data)) {
            throw PaymentException::verificationFailed('Payment could not be verified.');
        }

        return $this->normalizeTransaction($data, $expectedAmount, $expectedCurrency);
    }

    /**
     * @throws PaymentException
     */
    public function refund(string $reference, int $amount, string $currency, ?string $reason = null): PaymentVerificationResult
    {
        try {
            $response = $this->client()->post('/refund', array_filter([
                'transaction' => $reference,
                'amount' => $amount,
                'currency' => strtoupper($currency),
                'merchant_note' => $reason,
            ], fn (mixed $value): bool => $value !== null && $value !== ''));
        } catch (ConnectionException $exception) {
            throw PaymentException::verificationFailed($exception->getMessage());
        }

        if (! $response->successful() || $response->json('status') !== true) {
            throw PaymentException::verificationFailed((string) ($response->json('message') ?? 'Paystack refund failed.'));
        }

        $data = $response->json('data', []);

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
        $secret = $this->config['secret_key'] ?? null;

        if (! is_string($secret) || $secret === '' || $signature === '') {
            return false;
        }

        $computed = hash_hmac('sha512', $payload, $secret);

        return hash_equals($computed, $signature);
    }

    /**
     * @throws PaymentException
     */
    public function normalizeWebhook(WebhookPayload $payload): PaymentVerificationResult
    {
        $data = $payload->body['data'] ?? null;

        if (! is_array($data)) {
            throw PaymentException::verificationFailed('Invalid webhook payload.');
        }

        $reference = (string) ($data['reference'] ?? '');
        $amount = (int) ($data['amount'] ?? 0);
        $currency = strtoupper((string) ($data['currency'] ?? ''));

        return $this->normalizeTransaction($data, $amount, $currency, $reference);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws PaymentException
     */
    private function normalizeTransaction(array $data, int $expectedAmount, string $expectedCurrency, ?string $reference = null): PaymentVerificationResult
    {
        $reference ??= (string) ($data['reference'] ?? '');
        $status = strtolower((string) ($data['status'] ?? ''));
        $amountMinor = (int) ($data['amount'] ?? 0);
        $currency = strtoupper((string) ($data['currency'] ?? ''));

        $paymentStatus = match ($status) {
            'success' => PaymentStatus::Successful,
            'failed' => PaymentStatus::Failed,
            'abandoned', 'reversed' => PaymentStatus::Cancelled,
            default => PaymentStatus::Pending,
        };

        if ($paymentStatus === PaymentStatus::Successful) {
            if ($amountMinor !== $expectedAmount || $currency !== strtoupper($expectedCurrency)) {
                throw PaymentException::verificationFailed('Payment amount or currency mismatch.');
            }
        }

        $channel = $data['channel'] ?? null;

        return new PaymentVerificationResult(
            reference: $reference,
            providerReference: isset($data['id']) ? (string) $data['id'] : null,
            status: $paymentStatus,
            amount: $amountMinor,
            currency: $currency,
            paymentMethod: is_string($channel) ? $channel : null,
            providerResponse: $data,
        );
    }

    private function mapPaymentMethod(string $method): string
    {
        return match ($method) {
            'bank_transfer' => 'bank_transfer',
            'ussd' => 'ussd',
            'qr' => 'qr',
            'mobile_money' => 'mobile_money',
            default => 'card',
        };
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
