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
use App\Http\Controllers\OfficeAnalystReportController;
use App\Http\Controllers\OfficeContentPlannerController;
use App\Http\Controllers\LivingOfficeController;

Route::get('/office/login', [OfficeAuthController::class, 'showLogin'])->name('login');
Route::post('/office/login', [OfficeAuthController::class, 'login']);
Route::post('/office/logout', [OfficeAuthController::class, 'logout'])->name('office.logout');

Route::middleware(['auth', 'office.owner'])->prefix('office')->group(function () {
    Route::get('/', function () {
        return view('living-office');
    });

    // Browser-readable API endpoints for the React app
    Route::prefix('api')->group(function () {
        Route::get('/agents', [LivingOfficeController::class, 'getAgents']);
        Route::get('/agents/{id}', [LivingOfficeController::class, 'getAgent']);
        Route::get('/tasks', [LivingOfficeController::class, 'getTasks']);
        Route::get('/activity', [LivingOfficeController::class, 'getActivity']);
        Route::get('/content', [LivingOfficeController::class, 'getContent']);
        Route::get('/analyst-reports', [OfficeAnalystReportController::class, 'index']);
        Route::get('/research-stats', [OfficeAnalystReportController::class, 'researchStats']);
        Route::post('/analyst-reports/generate', [OfficeAnalystReportController::class, 'generate']);
        Route::get('/analyst-reports/charts', [OfficeAnalystReportController::class, 'charts']);
        Route::post('/analyst-reports/generate-format', [OfficeAnalystReportController::class, 'generateFormat']);
        Route::get('/analyst-reports/{report}', [OfficeAnalystReportController::class, 'show']);
        Route::post('/analyst-reports/{report}/approve', [OfficeAnalystReportController::class, 'approve']);
        Route::post('/analyst-reports/{report}/dismiss', [OfficeAnalystReportController::class, 'dismiss']);
        Route::get('/content-brands', [OfficeContentPlannerController::class, 'brands']);
        Route::post('/content-brands', [OfficeContentPlannerController::class, 'storeBrand']);
        Route::patch('/content-brands/{brand}', [OfficeContentPlannerController::class, 'updateBrand']);
        Route::get('/content-planner', [OfficeContentPlannerController::class, 'planner']);
        Route::post('/content-planner/analyze', [OfficeContentPlannerController::class, 'analyze']);
        Route::post('/content-planner/chat', [\App\Http\Controllers\OfficeContentPlannerChatController::class, 'message']);
        Route::get('/content-schedules', [OfficeContentPlannerController::class, 'index']);
        Route::post('/content-schedules', [OfficeContentPlannerController::class, 'store']);
        Route::post('/content-schedules/bulk-action', [OfficeContentPlannerController::class, 'bulkAction']);
        Route::post('/content-schedules/generate-month', [OfficeContentPlannerController::class, 'generateMonth']);
        Route::post('/content-schedules/generate-all-active', [OfficeContentPlannerController::class, 'generateAllActive']);
        Route::post('/content-schedules/{schedule}/generate', [OfficeContentPlannerController::class, 'generateSchedule']);
        Route::post('/content-schedules/{schedule}/move', [OfficeContentPlannerController::class, 'moveCard']);
        Route::post('/content-schedules/{schedule}/remove-card', [OfficeContentPlannerController::class, 'removeCard']);
        Route::get('/content-schedules/{schedule}', [OfficeContentPlannerController::class, 'show']);
        Route::patch('/content-schedules/{schedule}', [OfficeContentPlannerController::class, 'update']);
        Route::delete('/content-schedules/{schedule}', [OfficeContentPlannerController::class, 'destroy']);
        Route::post('/content', [OfficeContentPlannerController::class, 'createContent']);
        Route::patch('/content/{content}', [OfficeContentPlannerController::class, 'updateContent']);
        Route::post('/content/{content}/generate', [\App\Http\Controllers\OfficeContentPlannerController::class, 'generateContent']);
        Route::post('/content/{content}/revise', [\App\Http\Controllers\OfficeContentPlannerController::class, 'reviseContent']);
        Route::post('/content/{content}/approve', [\App\Http\Controllers\OfficeContentPlannerController::class, 'approveContent']);

        Route::post('/content/{content}/generate', [\App\Http\Controllers\OfficeContentPlannerController::class, 'generateContent']);
        Route::post('/content/{content}/revise', [\App\Http\Controllers\OfficeContentPlannerController::class, 'reviseContent']);
        Route::post('/content/{content}/approve', [\App\Http\Controllers\OfficeContentPlannerController::class, 'approveContent']);

        Route::get('/reels-settings', [\App\Http\Controllers\OfficeReelsSettingsController::class, 'show']);
        Route::get('/reels-slots', [\App\Http\Controllers\OfficePlannerGridController::class, 'reelsSlots']);
        Route::post('/reels-slots', [\App\Http\Controllers\OfficePlannerGridController::class, 'storeReelsSlot']);
        Route::patch('/reels-slots/{id}', [\App\Http\Controllers\OfficePlannerGridController::class, 'updateReelsSlot']);
        Route::delete('/reels-slots/{id}', [\App\Http\Controllers\OfficePlannerGridController::class, 'destroyReelsSlot']);
        Route::get('/content-results', [\App\Http\Controllers\OfficePlannerGridController::class, 'contentResults']);
        Route::post('/content-results/delete', [\App\Http\Controllers\OfficePlannerGridController::class, 'deleteResults']);
        Route::put('/reels-settings', [\App\Http\Controllers\OfficeReelsSettingsController::class, 'update']);

        Route::get('/summary', [LivingOfficeController::class, 'summary']);
        Route::get('/notifications', [LivingOfficeController::class, 'getNotifications']);
        Route::patch('/notifications/read-all', [LivingOfficeController::class, 'readAllNotifications']);
        Route::patch('/notifications/{notification}/read', [LivingOfficeController::class, 'readNotification']);
        Route::get('/commands', [LivingOfficeController::class, 'getCommands']);
        Route::get('/system-status', [LivingOfficeController::class, 'getSystemStatus']);
        Route::post('/agents/{id}/command', [LivingOfficeController::class, 'commandAgent']);
    });
});
