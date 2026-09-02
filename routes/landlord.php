<?php

declare(strict_types=1);

use App\Http\Controllers\Landlord\ApiKeys\ApiKeyController;
use App\Http\Controllers\Landlord\Audit\ActivityController;
use App\Http\Controllers\Landlord\Auth\AuthController;
use App\Http\Controllers\Landlord\Billing\InvoiceController;
use App\Http\Controllers\Landlord\Notifications\NotificationController;
use App\Http\Controllers\Landlord\Payments\PaymentController;
use App\Http\Controllers\Landlord\Plans\FeatureController;
use App\Http\Controllers\Landlord\Plans\PlanController;
use App\Http\Controllers\Landlord\Plans\PlanPriceController;
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
    Route::middleware('throttle:landlord-auth')->group(function (): void {
        Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');
        Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot_password');
        Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset_password');
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::match(['put', 'patch'], 'auth/profile', [AuthController::class, 'updateProfile'])->name('auth.profile');
        Route::post('auth/avatar', [AuthController::class, 'storeAvatar'])->name('auth.avatar.store');
        Route::delete('auth/avatar', [AuthController::class, 'destroyAvatar'])->name('auth.avatar.destroy');
        Route::post('auth/change-password', [AuthController::class, 'changePassword'])->name('auth.change_password');

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

        Route::post('subscriptions/{subscription}/change-plan', [SubscriptionController::class, 'changePlan'])->name('subscriptions.change_plan');
        Route::post('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
        Route::post('subscriptions/{subscription}/renew', [SubscriptionController::class, 'renew'])->name('subscriptions.renew');
        Route::apiResource('subscriptions', SubscriptionController::class)->only(['index', 'show', 'store']);

        Route::post('invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->name('invoices.pay');
        Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
        Route::apiResource('invoices', InvoiceController::class)->except(['destroy']);

        Route::delete('activities/destroy-many', [ActivityController::class, 'destroyMany'])->name('activities.destroy_many');
        Route::apiResource('activities', ActivityController::class)->only(['index', 'show', 'destroy']);

        Route::delete('api-keys/destroy-many', [ApiKeyController::class, 'destroyMany'])->name('api-keys.destroy_many');
        Route::post('api-keys/restore-many', [ApiKeyController::class, 'restoreMany'])->name('api-keys.restore_many');
        Route::post('api-keys/{api_key}/restore', [ApiKeyController::class, 'restore'])->withTrashed()->name('api-keys.restore');
        Route::post('api-keys/{api_key}/revoke', [ApiKeyController::class, 'revoke'])->name('api-keys.revoke');
        Route::apiResource('api-keys', ApiKeyController::class);

        Route::delete('notifications/destroy-many', [NotificationController::class, 'destroyMany'])->name('notifications.destroy_many');
        Route::post('notifications/restore-many', [NotificationController::class, 'restoreMany'])->name('notifications.restore_many');
        Route::post('notifications/{notice}/restore', [NotificationController::class, 'restore'])->withTrashed()->name('notifications.restore');
        Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read_all');
        Route::post('notifications/{notice}/read', [NotificationController::class, 'read'])->name('notifications.read');
        Route::apiResource('notifications', NotificationController::class)->parameters(['notifications' => 'notice']);

        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::get('settings/{domain}', [SettingController::class, 'show'])->name('settings.show');
        Route::put('settings/{domain}', [SettingController::class, 'update'])->name('settings.update');

        Route::get('plans/options', [PlanController::class, 'options'])->name('plans.options');
        Route::delete('plans/destroy-many', [PlanController::class, 'destroyMany'])->name('plans.destroy_many');
        Route::post('plans/restore-many', [PlanController::class, 'restoreMany'])->name('plans.restore_many');
        Route::post('plans/{plan}/restore', [PlanController::class, 'restore'])->withTrashed()->name('plans.restore');
        Route::post('plans/{plan}/features/sync', [PlanController::class, 'syncFeatures'])->name('plans.features.sync');
        Route::apiResource('plans', PlanController::class);

        Route::scopeBindings()->group(function (): void {
            Route::get('plans/{plan}/prices', [PlanPriceController::class, 'index'])->name('plans.prices.index');
            Route::post('plans/{plan}/prices', [PlanPriceController::class, 'store'])->name('plans.prices.store');
            Route::get('plans/{plan}/prices/{plan_price}', [PlanPriceController::class, 'show'])->name('plans.prices.show');
            Route::put('plans/{plan}/prices/{plan_price}', [PlanPriceController::class, 'update'])->name('plans.prices.update');
            Route::post('plans/{plan}/prices/{plan_price}/activate', [PlanPriceController::class, 'activate'])->name('plans.prices.activate');
            Route::post('plans/{plan}/prices/{plan_price}/deactivate', [PlanPriceController::class, 'deactivate'])->name('plans.prices.deactivate');
            Route::delete('plans/{plan}/prices/{plan_price}', [PlanPriceController::class, 'destroy'])->name('plans.prices.destroy');
        });

        Route::get('features/options', [FeatureController::class, 'options'])->name('features.options');
        Route::delete('features/destroy-many', [FeatureController::class, 'destroyMany'])->name('features.destroy_many');
        Route::post('features/restore-many', [FeatureController::class, 'restoreMany'])->name('features.restore_many');
        Route::post('features/{feature}/restore', [FeatureController::class, 'restore'])->withTrashed()->name('features.restore');
        Route::apiResource('features', FeatureController::class);

        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::post('payments/{payment}/verify', [PaymentController::class, 'verify'])->name('payments.verify');

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
