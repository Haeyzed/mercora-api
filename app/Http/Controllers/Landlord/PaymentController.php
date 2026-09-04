<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Resources\Landlord\Payments\PaymentResource;
use App\Models\Landlord\Payment;
use App\Services\Landlord\Payments\Exceptions\PaymentException;
use App\Services\Landlord\Payments\PaymentService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

#[Group('Landlord Payments')]
class PaymentController extends Controller
{
    public function __construct(private PaymentService $paymentService) {}

    /**
     * List payments.
     *
     * @return AnonymousResourceCollection<int, PaymentResource>
     */
    #[Endpoint(operationId: 'listLandlordPayments', title: 'List payments')]
    #[QueryParameter('filter[tenant_id]', description: 'Exact tenant id.', type: 'string')]
    #[QueryParameter('filter[invoice_id]', description: 'Exact invoice id.', type: 'int')]
    #[QueryParameter('filter[status]', description: 'Exact payment status.', type: 'string')]
    #[QueryParameter('filter[provider]', description: 'Exact payment provider.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Payment::class);

        return PaymentResource::collection($this->paymentService->paginate($request));
    }

    /**
     * Show a payment.
     */
    #[Endpoint(operationId: 'showLandlordPayment', title: 'Show a payment')]
    public function show(Payment $payment): PaymentResource
    {
        $this->authorize('view', $payment);

        return $this->paymentService
            ->show($payment)
            ->toResource(PaymentResource::class);
    }

    /**
     * Verify a payment with the provider.
     */
    #[Endpoint(operationId: 'verifyLandlordPayment', title: 'Verify a payment')]
    public function verify(Payment $payment): PaymentResource
    {
        $this->authorize('verify', $payment);

        try {
            $payment = $this->paymentService->verify($payment);
        } catch (PaymentException $exception) {
            throw ValidationException::withMessages([
                'payment' => $exception->getMessage(),
            ]);
        }

        return $payment->toResource(PaymentResource::class);
    }
}
