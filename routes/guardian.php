<?php

use Brigada\Guardian\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'overview'])->name('guardian.overview');
Route::get('/requests', [DashboardController::class, 'requests'])->name('guardian.requests');
Route::get('/queries', [DashboardController::class, 'queries'])->name('guardian.queries');
Route::get('/outgoing-http', [DashboardController::class, 'outgoingHttp'])->name('guardian.outgoing-http');
Route::get('/jobs', [DashboardController::class, 'jobs'])->name('guardian.jobs');
Route::get('/mail', [DashboardController::class, 'mail'])->name('guardian.mail');
Route::get('/notifications', [DashboardController::class, 'notifications'])->name('guardian.notifications');
Route::get('/cache', [DashboardController::class, 'cache'])->name('guardian.cache');
Route::get('/exceptions', [DashboardController::class, 'exceptions'])->name('guardian.exceptions');
Route::get('/queue', [DashboardController::class, 'queue'])->name('guardian.queue');
Route::get('/logs', [DashboardController::class, 'logs'])->name('guardian.logs');
Route::get('/alerts', [DashboardController::class, 'alerts'])->name('guardian.alerts');
Route::get('/health', [DashboardController::class, 'health'])->name('guardian.health');

Route::prefix('api')->middleware('throttle:guardian-api')->group(function () {
    Route::get('/overview', [DashboardController::class, 'apiOverview'])->name('guardian.api.overview');
    Route::get('/requests', [DashboardController::class, 'apiRequests'])->name('guardian.api.requests');
    Route::get('/queries', [DashboardController::class, 'apiQueries'])->name('guardian.api.queries');
    Route::get('/outgoing-http', [DashboardController::class, 'apiOutgoingHttp'])->name('guardian.api.outgoing-http');
    Route::get('/jobs', [DashboardController::class, 'apiJobs'])->name('guardian.api.jobs');
    Route::get('/mail', [DashboardController::class, 'apiMail'])->name('guardian.api.mail');
    Route::get('/notifications', [DashboardController::class, 'apiNotifications'])->name('guardian.api.notifications');
    Route::get('/cache', [DashboardController::class, 'apiCache'])->name('guardian.api.cache');
    Route::get('/exceptions', [DashboardController::class, 'apiExceptions'])->name('guardian.api.exceptions');
    Route::get('/queue', [DashboardController::class, 'apiQueue'])->name('guardian.api.queue');
    Route::get('/logs', [DashboardController::class, 'apiLogs'])->name('guardian.api.logs');
    Route::get('/alerts', [DashboardController::class, 'apiAlerts'])->name('guardian.api.alerts');
    Route::get('/health', [DashboardController::class, 'apiHealth'])->name('guardian.api.health');
    Route::post('/health/run/{check}', [DashboardController::class, 'apiHealthRun'])->name('guardian.api.health.run');
});
