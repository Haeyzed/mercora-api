<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Landlord\TenantStatus;
use App\Models\Landlord\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Block tenant application requests when the tenant is suspended.
 */
class EnsureTenantNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if ($tenant instanceof Tenant && $tenant->status === TenantStatus::Suspended) {
            return response()->json([
                'message' => 'This tenant account has been suspended.',
            ], 403);
        }

        return $next($request);
    }
}
