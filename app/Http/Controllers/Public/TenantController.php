<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ResolvePublicTenantRequest;
use App\Http\Resources\Public\PublicTenantResource;
use App\Services\Landlord\Tenants\PublicTenantResolver;

/**
 * Public tenant discovery by domain for auth branding.
 */
class TenantController extends Controller
{
    public function __construct(private readonly PublicTenantResolver $resolver) {}

    /**
     * Resolve a tenant from domain query or request host without initializing tenancy.
     */
    public function show(ResolvePublicTenantRequest $request): PublicTenantResource
    {
        $tenant = $this->resolver->resolveByDomain($request->resolvedDomain());

        return new PublicTenantResource($tenant);
    }
}
