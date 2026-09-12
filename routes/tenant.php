<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\AuthController;
use App\Http\Controllers\Tenant\HealthController;
use App\Http\Controllers\Tenant\UserController;
use App\Http\Middleware\EnsureTenantHttps;
use App\Http\Middleware\EnsureTenantNotSuspended;
use App\Http\Middleware\InitializeTenancyByDomainOrHeader;
use App\Http\Middleware\PreventAccessFromCentralDomainsUnlessTenantHeader;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Tenant API routes are identified by Host or X-Tenant-Domain (BFF).
| These routes are loaded by TenancyServiceProvider — not bootstrap/app.php.
|
*/

Route::middleware([
    'api',
    InitializeTenancyByDomainOrHeader::class,
    PreventAccessFromCentralDomainsUnlessTenantHeader::class,
    EnsureTenantHttps::class,
    EnsureTenantNotSuspended::class,
    'tenant.guard',
])->prefix('api/tenant')->name('tenant.')->group(function (): void {
    Route::get('health', HealthController::class)->name('health');

    Route::middleware('throttle:tenant-auth')->group(function (): void {
        Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');
        Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot_password');
        Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset_password');
    });

    Route::middleware(['auth:tenant', 'throttle:tenant-api'])->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::match(['put', 'patch'], 'auth/profile', [AuthController::class, 'updateProfile'])->name('auth.profile');
        Route::post('auth/change-password', [AuthController::class, 'changePassword'])->name('auth.change_password');

        Route::middleware('subscription.active')->group(function (): void {
            Route::middleware('permission:users.view')->group(function (): void {
                Route::get('users', [UserController::class, 'index'])->name('users.index');
                Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
            });

            Route::middleware('permission:users.manage')->group(function (): void {
                Route::post('users', [UserController::class, 'store'])->name('users.store');
                Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])->name('users.update');
                Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
            });
        });
    });
});
