<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OfficeBridgeController;


Route::prefix('office')->group(function () {
    // Ingestion endpoint (requires OFFICE_BRIDGE_TOKEN)
    // Server-to-server only, no browser auth needed
    Route::post('/events', [OfficeBridgeController::class, 'ingest']);
    Route::post('/commands/claim', [OfficeBridgeController::class, 'claim']);
    Route::patch('/commands/{command}', [OfficeBridgeController::class, 'updateCommand']);
});
