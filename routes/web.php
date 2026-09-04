<?php

use App\Services\Landlord\SettingService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/up', function () {
    try {
        if (Schema::hasTable('settings')) {
            $allowed = (bool) app(SettingService::class)->value('platform.allow_status_page', true);

            if (! $allowed) {
                abort(404);
            }
        }
    } catch (Throwable) {
        // Fall through to OK when settings are unavailable.
    }

    return response('OK', 200)
        ->header('Content-Type', 'text/plain');
});
