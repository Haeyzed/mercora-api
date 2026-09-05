<?php

declare(strict_types=1);

namespace App\Services\Landlord\Payments\Contracts;

use App\Enums\Landlord\PaymentProvider;
use App\Services\Landlord\Payments\DTOs\PaymentInitializationData;
use App\Services\Landlord\Payments\DTOs\PaymentInitializationResult;
use App\Services\Landlord\Payments\DTOs\PaymentVerificationResult;
use App\Services\Landlord\Payments\DTOs\WebhookPayload;
use App\Services\Landlord\Payments\Exceptions\PaymentException;

/**
 * Provider adapter contract for payment initialization, verification, and webhooks.
 *
 * Each implementation encapsulates a single {@see PaymentProvider} and translates
 * between application DTOs and provider APIs. Callers must treat returned amounts
 * as minor units (e.g. kobo/cents) and never mix provider response shapes outside
 * the driver.
 */
interface PaymentDriver
{
    /**
     * The provider this driver implements.
     */
    public function provider(): PaymentProvider;

    /**
     * ISO 4217 currency codes supported by this provider instance.
     *
     * @return list<string> Uppercase currency codes (e.g. NGN, USD).
     */
    public function supportedCurrencies(): array;

    /**
     * Whether the provider accepts charges in the given currency.
     *
     * @param  string  $currency  ISO 4217 code; comparison is case-insensitive.
     */
    public function supportsCurrency(string $currency): bool;

    /**
     * Whether the provider supports a normalized payment method slug.
     *
     * @param  string  $method  Application method key (e.g. card, bank_transfer).
     */
    public function supportsPaymentMethod(string $method): bool;

    /**
     * Start a hosted checkout session with the provider.
     *
     * @throws PaymentException When currency is unsupported or the provider rejects the request.
     */
    public function initialize(PaymentInitializationData $data): PaymentInitializationResult;

    /**
     * Confirm transaction status with the provider and normalize the outcome.
     *
     * On successful status, the implementation must reject responses whose amount
     * or currency do not match the expected values supplied by the application.
     *
     * @param  int  $expectedAmount  Charge amount in minor units.
     * @param  string  $expectedCurrency  ISO 4217 currency code.
     *
     * @throws PaymentException When verification fails or amount/currency mismatch on success.
     */
    public function verify(string $reference, int $expectedAmount, string $expectedCurrency): PaymentVerificationResult;

    /**
     * Refund a successful charge with the provider.
     *
     * @param  string  $reference  Provider or merchant reference for the original charge.
     * @param  int  $amount  Amount in minor units.
     * @param  string  $currency  ISO 4217 currency code.
     *
     * @throws PaymentException When the provider rejects the refund.
     */
    public function refund(string $reference, int $amount, string $currency, ?string $reason = null): PaymentVerificationResult;

    /**
     * Validate an inbound webhook using the provider's signing scheme.
     *
     * Must use a constant-time comparison where applicable. Returns false when
     * configuration is missing or the signature does not match.
     */
    public function verifyWebhookSignature(string $signature, string $payload): bool;

    /**
     * Map a verified webhook body to a normalized verification result.
     *
     * @throws PaymentException When the payload cannot be parsed or normalized.
     */
    public function normalizeWebhook(WebhookPayload $payload): PaymentVerificationResult;
}
