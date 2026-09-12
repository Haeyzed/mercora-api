<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Symfony\Component\HttpFoundation\Response;

/**
 * Same as Stancl PreventAccessFromCentralDomains, but allow tenant routes on a
 * central Host when X-Tenant-Domain identifies a tenant (BFF proxy pattern).
 */
class PreventAccessFromCentralDomainsUnlessTenantHeader extends PreventAccessFromCentralDomains
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->getHost(), config('tenancy.central_domains'), true)) {
            return $next($request);
        }

        $header = trim((string) $request->header(InitializeTenancyByDomainOrHeader::HEADER, ''));

        if ($header !== '') {
            return $next($request);
        }

        $abortRequest = static::$abortRequest ?? function (): Response {
            abort(404);
        };

        return $abortRequest($request, $next);
    }
}
