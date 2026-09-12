<?php

use App\Http\Controllers\Public\TenantController as PublicTenantController;
use App\Http\Controllers\Webhooks\FlutterwaveWebhookController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/webhooks/payments/{provider}', PaymentWebhookController::class)
    ->name('webhooks.payments');

Route::post('/webhooks/flutterwave', FlutterwaveWebhookController::class);

Route::prefix('public')->name('public.')->group(function (): void {
    Route::get('tenant', [PublicTenantController::class, 'show'])->name('tenant.show');
});

require __DIR__.'/landlord.php';
