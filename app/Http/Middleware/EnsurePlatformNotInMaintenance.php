<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Landlord\Settings\SettingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Block unauthenticated landlord API traffic during platform maintenance.
 *
 * Authenticated users and auth endpoints remain reachable so admins can sign in
 * and disable maintenance mode via settings.
 */
class EnsurePlatformNotInMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/landlord/*')) {
            return $next($request);
        }

        if (! $this->maintenanceEnabled()) {
            return $next($request);
        }

        if ($request->user() !== null) {
            return $next($request);
        }

        if ($request->is('api/landlord/auth/*')) {
            return $next($request);
        }

        $message = $this->maintenanceMessage();

        return response()->json([
            'message' => $message,
        ], 503);
    }

    private function maintenanceEnabled(): bool
    {
        try {
            if (! Schema::hasTable('settings')) {
                return false;
            }

            return (bool) app(SettingService::class)->value('platform.maintenance_mode', false);
        } catch (\Throwable) {
            return false;
        }
    }

    private function maintenanceMessage(): string
    {
        $message = app(SettingService::class)->value('platform.maintenance_message');

        if (is_string($message) && $message !== '') {
            return $message;
        }

        return 'The platform is currently under maintenance.';
    }
}
