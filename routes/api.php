<?php

use App\Http\Controllers\Webhooks\FlutterwaveWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/webhooks/flutterwave', FlutterwaveWebhookController::class);

require __DIR__.'/landlord.php';
