<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Landlord\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Block tenant product routes when the landlord subscription does not grant access.
 */
class EnsureActiveSubscription
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            return response()->json([
                'message' => 'Tenant context is required.',
            ], 403);
        }

        $subscription = $tenant->activeSubscription();

        if ($subscription === null || ! $subscription->grantsAccess()) {
            return response()->json([
                'message' => 'An active subscription is required.',
            ], 402);
        }

        return $next($request);
    }
}
