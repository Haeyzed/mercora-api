<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Landlord\SettingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject non-HTTPS tenant requests when tenancy.require_https is enabled.
 */
class EnsureTenantHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->secure() || ! $this->httpsRequired()) {
            return $next($request);
        }

        return response()->json([
            'message' => 'HTTPS is required for tenant requests.',
        ], 403);
    }

    private function httpsRequired(): bool
    {
        try {
            if (! Schema::hasTable('settings')) {
                return true;
            }

            return (bool) app(SettingService::class)->value('tenancy.require_https', true);
        } catch (\Throwable) {
            return true;
        }
    }
}
