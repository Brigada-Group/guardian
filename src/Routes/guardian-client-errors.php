<?php 

    use Brigada\Guardian\Http\Controllers\ClientErrorController;
    use Illuminate\Support\Facades\Route;

    if (! config('guardian.client_errors.enabled',true)) return;

    Route::post(
        config('guardian.client_errors.route','guardian/client-errors'),
        [ClientErrorController::class,'store']
    )->middleware(config('guardian.client_errors.middleware',['web','throttle:60,1']))->name('guardian.client-errors');