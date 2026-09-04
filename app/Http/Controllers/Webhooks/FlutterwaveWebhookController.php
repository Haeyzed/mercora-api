<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * @deprecated Use {@see PaymentWebhookController} via POST /api/webhooks/payments/flutterwave.
 */
class FlutterwaveWebhookController extends Controller
{
    /**
     * Receive Flutterwave payment webhooks (legacy path).
     *
     * @unauthenticated
     */
    public function __invoke(Request $request, PaymentWebhookController $controller): Response
    {
        return $controller($request, 'flutterwave');
    }
}
