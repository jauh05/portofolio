<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LivingOfficeController;


Route::prefix('office')->group(function () {
    Route::get('/agents', [LivingOfficeController::class, 'getAgents']);
    Route::get('/agents/{id}', [LivingOfficeController::class, 'getAgent']);
    Route::get('/tasks', [LivingOfficeController::class, 'getTasks']);
    Route::get('/activity', [LivingOfficeController::class, 'getActivity']);
    Route::get('/system-status', [LivingOfficeController::class, 'getSystemStatus']);
    
    // Ingestion endpoint (requires OFFICE_BRIDGE_TOKEN)
    Route::post('/events', [LivingOfficeController::class, 'ingestEvent']);
    
    // Command endpoint
    Route::post('/agents/{id}/command', [LivingOfficeController::class, 'commandAgent']);
});
