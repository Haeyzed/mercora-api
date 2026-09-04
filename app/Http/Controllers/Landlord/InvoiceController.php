<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\Billing\StoreInvoiceRequest;
use App\Http\Requests\Landlord\Billing\UpdateInvoiceRequest;
use App\Http\Resources\Landlord\Billing\InvoiceResource;
use App\Http\Resources\Landlord\Payments\PaymentResource;
use App\Models\Landlord\Invoice;
use App\Services\Landlord\InvoiceService;
use App\Services\Landlord\Payments\Exceptions\PaymentException;
use App\Services\Landlord\Payments\PaymentService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

#[Group('Landlord Billing')]
class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceService $invoiceService,
        private PaymentService $paymentService,
    ) {}

    /**
     * List invoices.
     *
     * @return AnonymousResourceCollection<int, InvoiceResource>
     */
    #[Endpoint(operationId: 'listLandlordInvoices', title: 'List invoices')]
    #[QueryParameter('filter[tenant_id]', description: 'Exact tenant id.', type: 'string')]
    #[QueryParameter('filter[subscription_id]', description: 'Exact subscription id.', type: 'int')]
    #[QueryParameter('filter[status]', description: 'Exact invoice status.', type: 'string')]
    #[QueryParameter('filter[number]', description: 'Exact invoice number.', type: 'string')]
    #[QueryParameter('search', description: 'Partial match across invoice number and tenant name or slug.', type: 'string')]
    #[QueryParameter('include', description: 'Comma-separated relationships. Allowed: tenant, subscription.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Invoice::class);

        return InvoiceResource::collection($this->invoiceService->paginate($request));
    }

    /**
     * Create an invoice.
     */
    #[Endpoint(operationId: 'storeLandlordInvoice', title: 'Create an invoice')]
    #[Response(201)]
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $this->authorize('create', Invoice::class);

        return $this->invoiceService
            ->store($request->validated())
            ->toResource(InvoiceResource::class)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show an invoice.
     */
    #[Endpoint(operationId: 'showLandlordInvoice', title: 'Show an invoice')]
    #[QueryParameter('include', description: 'Comma-separated relationships. Allowed: tenant, subscription.', type: 'string')]
    public function show(Request $request, Invoice $invoice): InvoiceResource
    {
        $this->authorize('view', $invoice);

        return $this->invoiceService
            ->show($invoice, $request)
            ->toResource(InvoiceResource::class);
    }

    /**
     * Update an open invoice.
     */
    #[Endpoint(operationId: 'updateLandlordInvoice', title: 'Update an invoice')]
    public function update(UpdateInvoiceRequest $request, Invoice $invoice): InvoiceResource
    {
        $this->authorize('update', $invoice);

        return $this->invoiceService
            ->update($invoice, $request->validated())
            ->toResource(InvoiceResource::class);
    }

    /**
     * Initialize payment for an open invoice.
     */
    #[Endpoint(operationId: 'payLandlordInvoice', title: 'Pay an invoice')]
    public function pay(Request $request, Invoice $invoice): PaymentResource
    {
        $this->authorize('pay', $invoice);

        try {
            $payment = $this->paymentService->initializeForInvoice(
                $invoice,
                $request->user(),
                $request->input('redirect_url'),
            );
        } catch (PaymentException $exception) {
            throw ValidationException::withMessages([
                'payment' => $exception->getMessage(),
            ]);
        }

        return new PaymentResource($payment);
    }

    /**
     * Void an invoice.
     */
    #[Endpoint(operationId: 'voidLandlordInvoice', title: 'Void an invoice')]
    public function void(Invoice $invoice): InvoiceResource
    {
        $this->authorize('void', $invoice);

        return $this->invoiceService
            ->void($invoice)
            ->toResource(InvoiceResource::class);
    }
}
