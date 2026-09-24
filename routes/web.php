<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/projects', function () {
    return view('projects');
});

Route::get('/certificates', function () {
    return view('certificates');
});

use App\Http\Controllers\OfficeAuthController;
use App\Http\Controllers\LivingOfficeController;

Route::get('/office/login', [OfficeAuthController::class, 'showLogin'])->name('login');
Route::post('/office/login', [OfficeAuthController::class, 'login']);
Route::post('/office/logout', [OfficeAuthController::class, 'logout'])->name('office.logout');

Route::middleware('auth')->prefix('office')->group(function () {
    Route::get('/', function () {
        return view('living-office');
    });

    // Browser-readable API endpoints for the React app
    Route::prefix('api')->group(function () {
        Route::get('/agents', [LivingOfficeController::class, 'getAgents']);
        Route::get('/agents/{id}', [LivingOfficeController::class, 'getAgent']);
        Route::get('/tasks', [LivingOfficeController::class, 'getTasks']);
        Route::get('/activity', [LivingOfficeController::class, 'getActivity']);
        Route::get('/system-status', [LivingOfficeController::class, 'getSystemStatus']);
        Route::post('/agents/{id}/command', [LivingOfficeController::class, 'commandAgent']);
    });
});
