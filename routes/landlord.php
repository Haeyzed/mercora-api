<?php

declare(strict_types=1);

use App\Http\Controllers\Landlord\ApiKeys\ApiKeyController;
use App\Http\Controllers\Landlord\Audit\ActivityController;
use App\Http\Controllers\Landlord\Auth\AuthController;
use App\Http\Controllers\Landlord\Billing\InvoiceController;
use App\Http\Controllers\Landlord\Notifications\NotificationController;
use App\Http\Controllers\Landlord\Plans\PlanController;
use App\Http\Controllers\Landlord\Roles\PermissionController;
use App\Http\Controllers\Landlord\Roles\RoleController;
use App\Http\Controllers\Landlord\Settings\SettingController;
use App\Http\Controllers\Landlord\Subscriptions\SubscriptionController;
use App\Http\Controllers\Landlord\Tenants\DomainController;
use App\Http\Controllers\Landlord\Tenants\TenantController;
use App\Http\Controllers\Landlord\Users\UserController;
use App\Http\Controllers\Shared\World\CityController;
use App\Http\Controllers\Shared\World\CountryController;
use App\Http\Controllers\Shared\World\CurrencyController;
use App\Http\Controllers\Shared\World\LanguageController;
use App\Http\Controllers\Shared\World\StateController;
use App\Http\Controllers\Shared\World\TimezoneController;
use Illuminate\Support\Facades\Route;

Route::prefix('landlord')->name('landlord.')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:landlord-login')
        ->name('auth.login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');

        Route::get('users/options', [UserController::class, 'options'])->name('users.options');
        Route::post('users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
        Route::post('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
        Route::post('users/{user}/roles', [UserController::class, 'syncRoles'])->name('users.roles.sync');
        Route::apiResource('users', UserController::class);

        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::apiResource('roles', RoleController::class);

        Route::get('tenants/options', [TenantController::class, 'options'])->name('tenants.options');
        Route::delete('tenants/destroy-many', [TenantController::class, 'destroyMany'])->name('tenants.destroy_many');
        Route::post('tenants/restore-many', [TenantController::class, 'restoreMany'])->name('tenants.restore_many');
        Route::post('tenants/{tenant}/restore', [TenantController::class, 'restore'])->withTrashed()->name('tenants.restore');
        Route::post('tenants/{tenant}/provision', [TenantController::class, 'provision'])->name('tenants.provision');
        Route::post('tenants/{tenant}/activate', [TenantController::class, 'activate'])->name('tenants.activate');
        Route::post('tenants/{tenant}/suspend', [TenantController::class, 'suspend'])->name('tenants.suspend');
        Route::post('tenants/{tenant}/reactivate', [TenantController::class, 'reactivate'])->name('tenants.reactivate');
        Route::delete('tenants/{tenant}/force', [TenantController::class, 'forceDestroy'])->withTrashed()->name('tenants.force_delete');
        Route::apiResource('tenants', TenantController::class);

        Route::scopeBindings()->group(function (): void {
            Route::get('tenants/{tenant}/domains', [DomainController::class, 'index'])->name('tenants.domains.index');
            Route::post('tenants/{tenant}/domains', [DomainController::class, 'store'])->name('tenants.domains.store');
            Route::delete('tenants/{tenant}/domains/{domain}', [DomainController::class, 'destroy'])->name('tenants.domains.destroy');
        });

        Route::get('subscriptions/options', [SubscriptionController::class, 'options'])->name('subscriptions.options');
        Route::delete('subscriptions/destroy-many', [SubscriptionController::class, 'destroyMany'])->name('subscriptions.destroy_many');
        Route::post('subscriptions/restore-many', [SubscriptionController::class, 'restoreMany'])->name('subscriptions.restore_many');
        Route::post('subscriptions/{subscription}/restore', [SubscriptionController::class, 'restore'])->withTrashed()->name('subscriptions.restore');
        Route::post('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
        Route::post('subscriptions/{subscription}/renew', [SubscriptionController::class, 'renew'])->name('subscriptions.renew');
        Route::apiResource('subscriptions', SubscriptionController::class);

        Route::get('invoices/options', [InvoiceController::class, 'options'])->name('invoices.options');
        Route::delete('invoices/destroy-many', [InvoiceController::class, 'destroyMany'])->name('invoices.destroy_many');
        Route::post('invoices/restore-many', [InvoiceController::class, 'restoreMany'])->name('invoices.restore_many');
        Route::post('invoices/{invoice}/restore', [InvoiceController::class, 'restore'])->withTrashed()->name('invoices.restore');
        Route::post('invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->name('invoices.pay');
        Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
        Route::apiResource('invoices', InvoiceController::class);

        Route::get('activities/options', [ActivityController::class, 'options'])->name('activities.options');
        Route::delete('activities/destroy-many', [ActivityController::class, 'destroyMany'])->name('activities.destroy_many');
        Route::apiResource('activities', ActivityController::class)->only(['index', 'show', 'destroy']);

        Route::get('api-keys/options', [ApiKeyController::class, 'options'])->name('api-keys.options');
        Route::delete('api-keys/destroy-many', [ApiKeyController::class, 'destroyMany'])->name('api-keys.destroy_many');
        Route::post('api-keys/restore-many', [ApiKeyController::class, 'restoreMany'])->name('api-keys.restore_many');
        Route::post('api-keys/{api_key}/restore', [ApiKeyController::class, 'restore'])->withTrashed()->name('api-keys.restore');
        Route::post('api-keys/{api_key}/revoke', [ApiKeyController::class, 'revoke'])->name('api-keys.revoke');
        Route::apiResource('api-keys', ApiKeyController::class);

        Route::get('notifications/options', [NotificationController::class, 'options'])->name('notifications.options');
        Route::delete('notifications/destroy-many', [NotificationController::class, 'destroyMany'])->name('notifications.destroy_many');
        Route::post('notifications/restore-many', [NotificationController::class, 'restoreMany'])->name('notifications.restore_many');
        Route::post('notifications/{notice}/restore', [NotificationController::class, 'restore'])->withTrashed()->name('notifications.restore');
        Route::post('notifications/{notice}/read', [NotificationController::class, 'read'])->name('notifications.read');
        Route::apiResource('notifications', NotificationController::class)->parameters(['notifications' => 'notice']);

        Route::get('settings/options', [SettingController::class, 'options'])->name('settings.options');
        Route::delete('settings/destroy-many', [SettingController::class, 'destroyMany'])->name('settings.destroy_many');
        Route::post('settings/restore-many', [SettingController::class, 'restoreMany'])->name('settings.restore_many');
        Route::post('settings/{setting}/restore', [SettingController::class, 'restore'])->withTrashed()->name('settings.restore');
        Route::apiResource('settings', SettingController::class);

        Route::get('plans/options', [PlanController::class, 'options'])->name('plans.options');
        Route::delete('plans/destroy-many', [PlanController::class, 'destroyMany'])->name('plans.destroy_many');
        Route::post('plans/restore-many', [PlanController::class, 'restoreMany'])->name('plans.restore_many');
        Route::post('plans/{plan}/restore', [PlanController::class, 'restore'])->withTrashed()->name('plans.restore');
        Route::apiResource('plans', PlanController::class);

        Route::prefix('world')->name('world.')->group(function (): void {
            $worldActions = function (string $resource, string $parameter, string $controller): void {
                Route::get($resource.'/options', [$controller, 'options'])->name($resource.'.options');
                Route::get($resource.'/export', [$controller, 'export'])->name($resource.'.export');
                Route::get($resource.'/template', [$controller, 'template'])->name($resource.'.template');
                Route::post($resource.'/import', [$controller, 'import'])->name($resource.'.import');
                Route::delete($resource.'/destroy-many', [$controller, 'destroyMany'])->name($resource.'.destroy_many');
                Route::post($resource.'/restore-many', [$controller, 'restoreMany'])->name($resource.'.restore_many');
                Route::post($resource.'/{'.$parameter.'}/restore', [$controller, 'restore'])->withTrashed()->name($resource.'.restore');
                Route::apiResource($resource, $controller);
            };

            $worldActions('countries', 'country', CountryController::class);
            $worldActions('states', 'state', StateController::class);
            $worldActions('cities', 'city', CityController::class);
            $worldActions('timezones', 'timezone', TimezoneController::class);
            $worldActions('languages', 'language', LanguageController::class);
            $worldActions('currencies', 'currency', CurrencyController::class);
        });
    });
});
