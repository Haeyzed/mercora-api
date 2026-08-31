<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\Landlord\Payments\ProcessPaymentWebhookJob;
use App\Services\Landlord\Payments\DTOs\WebhookPayload;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FlutterwaveWebhookController extends Controller
{
    /**
     * Receive Flutterwave payment webhooks.
     *
     * @unauthenticated
     */
    public function __invoke(Request $request): Response
    {
        ProcessPaymentWebhookJob::dispatch(new WebhookPayload(
            rawPayload: $request->getContent(),
            body: $request->all(),
            signature: $request->header('verif-hash'),
            eventType: $request->input('event'),
            eventId: isset($request->input('data')['id']) ? (string) $request->input('data')['id'] : null,
        ));

        return response()->noContent();
    }
}
