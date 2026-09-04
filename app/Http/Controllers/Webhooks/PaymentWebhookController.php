<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Enums\Landlord\PaymentProvider;
use App\Http\Controllers\Controller;
use App\Jobs\Landlord\Payments\ProcessPaymentWebhookJob;
use App\Services\Landlord\Payments\DTOs\WebhookPayload;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Receive payment provider webhooks for any configured driver.
 */
class PaymentWebhookController extends Controller
{
    /**
     * Queue inbound webhook processing for the given provider slug.
     *
     * @unauthenticated
     */
    public function __invoke(Request $request, string $provider): Response
    {
        if (! in_array($provider, PaymentProvider::values(), true)) {
            throw new NotFoundHttpException("Unknown payment provider [{$provider}].");
        }

        ProcessPaymentWebhookJob::dispatch(
            $this->payloadFor($request, $provider),
            $provider,
        );

        return response()->noContent();
    }

    private function payloadFor(Request $request, string $provider): WebhookPayload
    {
        $body = $request->all();

        return match ($provider) {
            PaymentProvider::Flutterwave->value => new WebhookPayload(
                rawPayload: $request->getContent(),
                body: $body,
                signature: $request->header('verif-hash'),
                eventType: $request->input('event'),
                eventId: isset($request->input('data')['id']) ? (string) $request->input('data')['id'] : null,
            ),
            PaymentProvider::Paystack->value => new WebhookPayload(
                rawPayload: $request->getContent(),
                body: $body,
                signature: $request->header('x-paystack-signature'),
                eventType: $request->input('event'),
                eventId: isset($request->input('data')['id']) ? (string) $request->input('data')['id'] : null,
            ),
            PaymentProvider::Stripe->value => new WebhookPayload(
                rawPayload: $request->getContent(),
                body: $body,
                signature: $request->header('Stripe-Signature'),
                eventType: $request->input('type'),
                eventId: $request->input('id'),
            ),
            PaymentProvider::Paypal->value => new WebhookPayload(
                rawPayload: $request->getContent(),
                body: $body,
                signature: json_encode([
                    'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
                    'cert_url' => $request->header('PAYPAL-CERT-URL'),
                    'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
                    'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
                    'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
                ], JSON_THROW_ON_ERROR),
                eventType: $request->input('event_type'),
                eventId: $request->input('id'),
            ),
            default => new WebhookPayload(
                rawPayload: $request->getContent(),
                body: $body,
                signature: null,
            ),
        };
    }
}
