<?php

use Brigada\Guardian\Http\Controllers\ClientErrorController;
use Illuminate\Support\Facades\Route;

if (! config('guardian.client_errors.enabled', true)) {
    return;
}

Route::post(
    config('guardian.client_errors.route', 'guardian/client-errors'),
    [ClientErrorController::class, 'store']
)
    ->middleware(config('guardian.client_errors.middleware', ['web', 'throttle:60,1']))
    ->name('guardian.client-errors');

Route::get(
    config('guardian.client_errors.script_route', 'guardian/client.js'),
    function () {
        $path = __DIR__ . '/../../resources/js/guardian-client.js';

        if (! is_file($path)) {
            abort(404);
        }

        return response(file_get_contents($path), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
)->name('guardian.client-script');