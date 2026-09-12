<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force the tenant Sanctum guard for tenant API routes.
 */
class SetTenantGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('tenant');

        return $next($request);
    }
}
