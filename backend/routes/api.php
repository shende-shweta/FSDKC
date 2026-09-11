<?php

use App\Http\Controllers\Api\ConnectController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DiscoveryController;
use App\Http\Controllers\Api\LegacyReportController;
use App\Http\Controllers\Api\MongoController;
use App\Http\Controllers\Api\StreamController;
use App\Services\MongoService;
use Illuminate\Support\Facades\Route;

// Public routes — no auth required (health checks and MongoDB connectivity status)
Route::get('/health', function (MongoService $mongo) {
    return response()->json([
        'status' => 'ok',
        'platform' => 'Klearcom',
        'version' => '1.0.0',
        'mongodb' => $mongo->health(),
    ]);
});

Route::get('/mongodb/status', [MongoController::class, 'status']);

// All other API routes require a valid Bearer API key (see ApiKeyMiddleware)
Route::middleware('api.key')->group(function (): void {
    Route::get('/mongodb/transcripts', [MongoController::class, 'transcripts']);
    Route::get('/mongodb/diagnostics/{module}/{referenceId}', [MongoController::class, 'diagnostics']);

    Route::get('/dashboard/kpis', [DashboardController::class, 'kpis']);

    Route::prefix('legacy')->group(function (): void {
        Route::get('/reports/carriers', [LegacyReportController::class, 'carrierSummary']);
        Route::get('/reports/ivr/{jobId}', [LegacyReportController::class, 'ivrDepthReport']);
    });

    Route::prefix('discovery')->group(function (): void {
        Route::get('/jobs', [DiscoveryController::class, 'index']);
        Route::post('/jobs', [DiscoveryController::class, 'store']);
        Route::get('/jobs/{id}', [DiscoveryController::class, 'show']);
        Route::get('/jobs/{id}/tree', [DiscoveryController::class, 'tree']);
        Route::post('/jobs/{id}/start', [DiscoveryController::class, 'start']);
        Route::get('/jobs/{id}/stream', [StreamController::class, 'discoveryEvents']);
    });

    Route::prefix('connect')->group(function (): void {
        Route::get('/monitors', [ConnectController::class, 'index']);
        Route::post('/monitors', [ConnectController::class, 'store']);
        Route::get('/monitors/{id}', [ConnectController::class, 'show']);
        Route::get('/monitors/{id}/checks', [ConnectController::class, 'checks']);
        Route::post('/monitors/{id}/run-check', [ConnectController::class, 'runCheck']);
        Route::get('/monitors/{id}/stream', [StreamController::class, 'connectEvents']);
    });
});