<?php

declare(strict_types=1);

namespace App\Services\Landlord\Payments\Drivers\Paypal;

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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * PayPal Orders API v2 payment driver.
 *
 * Creates Checkout Orders and returns the payer-action approve URL. Application
 * amounts are minor units; PayPal receives major-unit strings. Webhook authenticity
 * is confirmed via PayPal's verify-webhook-signature endpoint.
 *
 * {@see verify()} expects the PayPal order id (stored as provider_reference), not
 * the merchant reference—PaymentService passes provider_reference for this provider.
 */
class PaypalDriver implements PaymentDriver
{
    /**
     * @param  array<string, mixed>  $config  Driver config from {@see config('payments.drivers.paypal')}.
     */
    public function __construct(private array $config) {}

    public function provider(): PaymentProvider
    {
        return PaymentProvider::Paypal;
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
        return in_array($method, ['card', 'paypal', 'bank_transfer'], true);
    }

    /**
     * @throws PaymentException
     */
    public function initialize(PaymentInitializationData $data): PaymentInitializationResult
    {
        if (! $this->supportsCurrency($data->currency)) {
            throw PaymentException::unsupportedCurrency($data->currency);
        }

        $description = ($data->title !== null && $data->title !== '')
            ? $data->title
            : 'Invoice payment';

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $data->reference,
                'invoice_id' => $data->reference,
                'custom_id' => $data->reference,
                'description' => $description,
                'amount' => [
                    'currency_code' => strtoupper($data->currency),
                    'value' => $this->toProviderAmount($data->amount),
                ],
            ]],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                        'user_action' => 'PAY_NOW',
                        'return_url' => $data->redirectUrl ?? (string) config('app.url'),
                        'cancel_url' => $data->redirectUrl ?? (string) config('app.url'),
                    ],
                ],
            ],
        ];

        $response = $this->client()->post('/v2/checkout/orders', $payload);

        if (! $response->successful()) {
            throw PaymentException::initializationFailed($this->safeMessage($response->json('message') ?? $response->json('error_description')));
        }

        $orderId = (string) ($response->json('id') ?? '');
        $approveUrl = $this->linkHref($response->json('links') ?? [], 'payer-action')
            ?? $this->linkHref($response->json('links') ?? [], 'approve');

        return new PaymentInitializationResult(
            reference: $data->reference,
            providerReference: $orderId,
            status: PaymentStatus::Pending,
            checkoutUrl: $approveUrl,
            providerResponse: $response->json() ?? [],
        );
    }

    /**
     * Verify by PayPal order id (provider_reference).
     *
     * @throws PaymentException
     */
    public function verify(string $reference, int $expectedAmount, string $expectedCurrency): PaymentVerificationResult
    {
        $response = $this->client()->get('/v2/checkout/orders/'.rawurlencode($reference));

        if (! $response->successful()) {
            throw PaymentException::verificationFailed('Payment could not be verified.');
        }

        $order = $response->json() ?? [];

        if (! is_array($order)) {
            throw PaymentException::verificationFailed('Payment could not be verified.');
        }

        if (strtoupper((string) ($order['status'] ?? '')) === 'APPROVED') {
            $capture = $this->client()->post('/v2/checkout/orders/'.rawurlencode($reference).'/capture');

            if ($capture->successful() && is_array($capture->json())) {
                $order = $capture->json();
            }
        }

        return $this->normalizeOrder($order, $expectedAmount, $expectedCurrency);
    }

    /**
     * Validate webhook via PayPal's verify-webhook-signature API.
     *
     * {@see $signature} must be a JSON object of PayPal transmission headers.
     */
    public function verifyWebhookSignature(string $signature, string $payload): bool
    {
        $webhookId = $this->config['webhook_id'] ?? null;

        if (! is_string($webhookId) || $webhookId === '' || $signature === '') {
            return false;
        }

        /** @var array<string, mixed>|null $headers */
        $headers = json_decode($signature, true);

        if (! is_array($headers)) {
            return false;
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            return false;
        }

        $response = $this->client()->post('/v1/notifications/verify-webhook-signature', [
            'auth_algo' => (string) ($headers['auth_algo'] ?? ''),
            'cert_url' => (string) ($headers['cert_url'] ?? ''),
            'transmission_id' => (string) ($headers['transmission_id'] ?? ''),
            'transmission_sig' => (string) ($headers['transmission_sig'] ?? ''),
            'transmission_time' => (string) ($headers['transmission_time'] ?? ''),
            'webhook_id' => $webhookId,
            'webhook_event' => $event,
        ]);

        return $response->successful()
            && strtoupper((string) $response->json('verification_status')) === 'SUCCESS';
    }

    /**
     * @throws PaymentException
     */
    public function normalizeWebhook(WebhookPayload $payload): PaymentVerificationResult
    {
        $resource = $payload->body['resource'] ?? null;

        if (! is_array($resource)) {
            throw PaymentException::verificationFailed('Invalid webhook payload.');
        }

        $eventType = (string) ($payload->eventType ?? $payload->body['event_type'] ?? '');

        if (str_contains($eventType, 'CHECKOUT.ORDER') || isset($resource['purchase_units'])) {
            $amount = $this->unitAmountMinor($resource);
            $currency = $this->unitCurrency($resource);

            return $this->normalizeOrder($resource, $amount, $currency);
        }

        if (str_contains($eventType, 'PAYMENT.CAPTURE')) {
            return $this->normalizeCapture($resource);
        }

        throw PaymentException::verificationFailed('Unsupported PayPal webhook event.');
    }

    /**
     * @param  array<string, mixed>  $order
     *
     * @throws PaymentException
     */
    private function normalizeOrder(array $order, int $expectedAmount, string $expectedCurrency): PaymentVerificationResult
    {
        $status = strtoupper((string) ($order['status'] ?? ''));
        $merchantReference = (string) (
            $order['purchase_units'][0]['custom_id']
            ?? $order['purchase_units'][0]['invoice_id']
            ?? $order['purchase_units'][0]['reference_id']
            ?? ''
        );

        $amountMinor = $this->unitAmountMinor($order);
        $currency = $this->unitCurrency($order) ?: strtoupper($expectedCurrency);

        $paymentStatus = match ($status) {
            'COMPLETED', 'APPROVED' => PaymentStatus::Successful,
            'VOIDED' => PaymentStatus::Cancelled,
            'CREATED', 'SAVED', 'PAYER_ACTION_REQUIRED' => PaymentStatus::Pending,
            default => PaymentStatus::Failed,
        };

        // APPROVED still needs capture in some flows; treat COMPLETED as settled success.
        if ($status === 'APPROVED') {
            $paymentStatus = PaymentStatus::Pending;
        }

        if ($paymentStatus === PaymentStatus::Successful) {
            if ($amountMinor !== $expectedAmount || $currency !== strtoupper($expectedCurrency)) {
                throw PaymentException::verificationFailed('Payment amount or currency mismatch.');
            }
        }

        return new PaymentVerificationResult(
            reference: $merchantReference !== '' ? $merchantReference : (string) ($order['id'] ?? ''),
            providerReference: isset($order['id']) ? (string) $order['id'] : null,
            status: $paymentStatus,
            amount: $amountMinor > 0 ? $amountMinor : $expectedAmount,
            currency: $currency,
            paymentMethod: 'paypal',
            providerResponse: $order,
        );
    }

    /**
     * @param  array<string, mixed>  $capture
     *
     * @throws PaymentException
     */
    private function normalizeCapture(array $capture): PaymentVerificationResult
    {
        $status = strtoupper((string) ($capture['status'] ?? ''));
        $amountMajor = (float) ($capture['amount']['value'] ?? 0);
        $currency = strtoupper((string) ($capture['amount']['currency_code'] ?? ''));
        $amountMinor = (int) round($amountMajor * 100);
        $merchantReference = (string) ($capture['custom_id'] ?? $capture['invoice_id'] ?? '');

        $paymentStatus = match ($status) {
            'COMPLETED' => PaymentStatus::Successful,
            'DECLINED', 'FAILED' => PaymentStatus::Failed,
            'REFUNDED' => PaymentStatus::Cancelled,
            default => PaymentStatus::Pending,
        };

        return new PaymentVerificationResult(
            reference: $merchantReference,
            providerReference: isset($capture['id']) ? (string) $capture['id'] : null,
            status: $paymentStatus,
            amount: $amountMinor,
            currency: $currency,
            paymentMethod: 'paypal',
            providerResponse: $capture,
        );
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function unitAmountMinor(array $order): int
    {
        $value = $order['purchase_units'][0]['amount']['value']
            ?? $order['amount']['value']
            ?? null;

        if ($value === null) {
            return 0;
        }

        return (int) round(((float) $value) * 100);
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function unitCurrency(array $order): string
    {
        return strtoupper((string) (
            $order['purchase_units'][0]['amount']['currency_code']
            ?? $order['amount']['currency_code']
            ?? ''
        ));
    }

    private function toProviderAmount(int $minorAmount): string
    {
        return number_format($minorAmount / 100, 2, '.', '');
    }

    /**
     * @param  list<mixed>|mixed  $links
     */
    private function linkHref(mixed $links, string $rel): ?string
    {
        if (! is_array($links)) {
            return null;
        }

        foreach ($links as $link) {
            if (is_array($link) && ($link['rel'] ?? null) === $rel && is_string($link['href'] ?? null)) {
                return $link['href'];
            }
        }

        return null;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) ($this->config['base_url'] ?? ''), '/'))
            ->withToken($this->accessToken())
            ->acceptJson()
            ->contentType('application/json')
            ->timeout(30)
            ->retry(2, 100, fn (\Throwable $e) => $e instanceof ConnectionException);
    }

    /**
     * @throws PaymentException
     */
    private function accessToken(): string
    {
        $clientId = (string) ($this->config['client_id'] ?? '');
        $clientSecret = (string) ($this->config['client_secret'] ?? '');
        $baseUrl = rtrim((string) ($this->config['base_url'] ?? ''), '/');

        if ($clientId === '' || $clientSecret === '') {
            throw PaymentException::driverNotConfigured('paypal');
        }

        $cacheKey = 'payments.paypal.access_token.'.hash('xxh128', $clientId.'|'.$baseUrl);

        /** @var string|null $cached */
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::baseUrl($baseUrl)
            ->withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->acceptJson()
            ->timeout(30)
            ->post('/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if (! $response->successful()) {
            throw PaymentException::initializationFailed('PayPal authentication failed.');
        }

        $token = (string) ($response->json('access_token') ?? '');
        $expiresIn = max(60, (int) ($response->json('expires_in') ?? 3600) - 60);

        if ($token === '') {
            throw PaymentException::initializationFailed('PayPal authentication failed.');
        }

        Cache::put($cacheKey, $token, now()->addSeconds($expiresIn));

        return $token;
    }

    private function safeMessage(mixed $message): string
    {
        return is_string($message) && $message !== ''
            ? $message
            : 'Payment could not be initialized.';
    }
}
