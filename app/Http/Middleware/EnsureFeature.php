<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Landlord\Tenant;
use App\Services\Landlord\Plans\FeatureGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Block tenant product routes when the plan does not include the given feature.
 */
class EnsureFeature
{
    public function __construct(private FeatureGate $featureGate) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            return response()->json([
                'message' => 'Tenant context is required.',
            ], 403);
        }

        $allowed = tenancy()->initialized
            ? tenancy()->central(fn (): bool => $this->featureGate->allows($tenant, $feature))
            : $this->featureGate->allows($tenant, $feature);

        if (! $allowed) {
            return response()->json([
                'message' => 'Your current plan does not include this feature.',
            ], 403);
        }

        return $next($request);
    }
}
