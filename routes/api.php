<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LivingOfficeController;


Route::prefix('office')->group(function () {
    // Ingestion endpoint (requires OFFICE_BRIDGE_TOKEN)
    // Server-to-server only, no browser auth needed
    Route::post('/events', [LivingOfficeController::class, 'ingestEvent']);
});
