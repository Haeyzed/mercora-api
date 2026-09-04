<?php

declare(strict_types=1);

namespace App\Services\Landlord\Payments\Drivers\Flutterwave;

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
 * Flutterwave v3 payment driver.
 *
 * Provider boundary: all HTTP interaction with Flutterwave lives here. Amounts are
 * converted between application minor units (integer) and Flutterwave major units
 * (float with two decimal places). Successful transactions must match expected
 * amount and currency before returning {@see PaymentStatus::Successful}.
 *
 * Webhook signatures are validated against {@see config('payments.drivers.flutterwave.secret_hash')}
 * using constant-time comparison.
 */
class FlutterwaveDriver implements PaymentDriver
{
    /**
     * @param  array<string, mixed>  $config  Driver config from {@see config('payments.drivers.flutterwave')}.
     */
    public function __construct(private array $config) {}

    /**
     * {@inheritDoc}
     */
    public function provider(): PaymentProvider
    {
        return PaymentProvider::Flutterwave;
    }

    /**
     * {@inheritDoc}
     */
    public function supportedCurrencies(): array
    {
        return $this->config['supported_currencies'] ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function supportsCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), $this->supportedCurrencies(), true);
    }

    /**
     * {@inheritDoc}
     */
    public function supportsPaymentMethod(string $method): bool
    {
        return in_array($method, ['card', 'bank_transfer', 'ussd', 'account', 'opay'], true);
    }

    /**
     * Create a Flutterwave hosted payment link for the given checkout data.
     *
     * @throws PaymentException When currency is unsupported or Flutterwave returns a non-success response.
     */
    public function initialize(PaymentInitializationData $data): PaymentInitializationResult
    {
        if (! $this->supportsCurrency($data->currency)) {
            throw PaymentException::unsupportedCurrency($data->currency);
        }

        $payload = [
            'tx_ref' => $data->reference,
            'amount' => $this->toProviderAmount($data->amount, $data->currency),
            'currency' => strtoupper($data->currency),
            'redirect_url' => $data->redirectUrl,
            'customer' => [
                'email' => $data->email,
                'name' => $data->name,
            ],
            'meta' => $data->metadata,
        ];

        if ($data->title !== null && $data->title !== '') {
            $payload['customizations'] = [
                'title' => $data->title,
            ];
        }

        if ($data->paymentMethod !== null) {
            $payload['payment_options'] = $this->mapPaymentMethod($data->paymentMethod);
        }

        $response = $this->client()->post('/payments', $payload);

        if (! $response->successful() || $response->json('status') !== 'success') {
            throw PaymentException::initializationFailed($this->safeMessage($response->json('message')));
        }

        $link = $response->json('data.link');

        return new PaymentInitializationResult(
            reference: $data->reference,
            providerReference: (string) $response->json('data.id'),
            status: PaymentStatus::Pending,
            checkoutUrl: is_string($link) ? $link : null,
            providerResponse: $response->json() ?? [],
        );
    }

    /**
     * Verify transaction status by merchant reference via Flutterwave API.
     *
     * @throws PaymentException|ConnectionException When the API call fails or successful status has amount/currency mismatch.
     */
    public function verify(string $reference, int $expectedAmount, string $expectedCurrency): PaymentVerificationResult
    {
        $response = $this->client()->get('/transactions/verify_by_reference', [
            'tx_ref' => $reference,
        ]);

        if (! $response->successful()) {
            throw PaymentException::verificationFailed('Payment could not be verified.');
        }

        return $this->normalizeTransaction($response->json('data') ?? [], $expectedAmount, $expectedCurrency);
    }

    /**
     * Validate the Flutterwave secret hash header against configured secret_hash.
     *
     * Returns false when secret_hash is not configured; never accepts unsigned webhooks in that case.
     */
    public function verifyWebhookSignature(string $signature, string $payload): bool
    {
        $secretHash = $this->config['secret_hash'] ?? null;

        if (! is_string($secretHash) || $secretHash === '') {
            return false;
        }

        return hash_equals($secretHash, $signature);
    }

    /**
     * Extract transaction fields from a webhook body and normalize to verification result.
     *
     * @throws PaymentException When the payload structure is invalid.
     */
    public function normalizeWebhook(WebhookPayload $payload): PaymentVerificationResult
    {
        $data = $payload->body['data'] ?? $payload->body;

        if (! is_array($data)) {
            throw PaymentException::verificationFailed('Invalid webhook payload.');
        }

        $reference = (string) ($data['tx_ref'] ?? $data['reference'] ?? '');
        $amount = (int) round(((float) ($data['amount'] ?? 0)) * 100);
        $currency = strtoupper((string) ($data['currency'] ?? ''));

        return $this->normalizeTransaction($data, $amount, $currency, $reference);
    }

    /**
     * Map Flutterwave transaction payload to a normalized verification result.
     *
     * Enforces amount and currency match when status is successful.
     *
     * @param  array<string, mixed>  $data  Flutterwave transaction or webhook data node.
     *
     * @throws PaymentException When a successful status reports mismatched amount or currency.
     */
    private function normalizeTransaction(array $data, int $expectedAmount, string $expectedCurrency, ?string $reference = null): PaymentVerificationResult
    {
        $reference ??= (string) ($data['tx_ref'] ?? '');
        $status = strtolower((string) ($data['status'] ?? ''));
        $amountMinor = (int) round(((float) ($data['amount'] ?? 0)) * 100);
        $currency = strtoupper((string) ($data['currency'] ?? ''));

        $paymentStatus = match ($status) {
            'successful' => PaymentStatus::Successful,
            'failed' => PaymentStatus::Failed,
            'cancelled' => PaymentStatus::Cancelled,
            default => PaymentStatus::Pending,
        };

        if ($paymentStatus === PaymentStatus::Successful) {
            if ($amountMinor !== $expectedAmount || $currency !== strtoupper($expectedCurrency)) {
                throw PaymentException::verificationFailed('Payment amount or currency mismatch.');
            }
        }

        return new PaymentVerificationResult(
            reference: $reference,
            providerReference: isset($data['id']) ? (string) $data['id'] : null,
            status: $paymentStatus,
            amount: $amountMinor,
            currency: $currency,
            paymentMethod: isset($data['payment_type']) ? (string) $data['payment_type'] : null,
            providerResponse: $data,
        );
    }

    /**
     * Convert minor-unit amount to Flutterwave major-unit float.
     */
    private function toProviderAmount(int $minorAmount, string $currency): float
    {
        return round($minorAmount / 100, 2);
    }

    /**
     * Map application payment method slug to Flutterwave payment_options value.
     */
    private function mapPaymentMethod(string $method): string
    {
        return match ($method) {
            'bank_transfer' => 'banktransfer',
            'account' => 'account',
            'ussd' => 'ussd',
            'opay' => 'opay',
            default => 'card',
        };
    }

    /**
     * Configured HTTP client for Flutterwave v3 API requests.
     */
    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) ($this->config['base_url'] ?? ''), '/'))
            ->withToken((string) ($this->config['secret_key'] ?? ''))
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 100, fn (\Throwable $e) => $e instanceof ConnectionException);
    }

    /**
     * Sanitize provider error message for exception surfacing.
     */
    private function safeMessage(mixed $message): string
    {
        return is_string($message) && $message !== ''
            ? $message
            : 'Payment could not be initialized.';
    }
}
