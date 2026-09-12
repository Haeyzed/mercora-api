<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Landlord\Tenant;
use Illuminate\Http\JsonResponse;

/**
 * Minimal tenant API shell probe that proves identification succeeded.
 */
class HealthController extends Controller
{
    /**
     * Return the current tenant id and status.
     */
    public function __invoke(): JsonResponse
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        abort_unless($tenant instanceof Tenant, 404, 'Tenant could not be identified on this domain.');

        return response()->json([
            'data' => [
                'id' => $tenant->id,
                'status' => $tenant->status,
            ],
        ]);
    }
}
