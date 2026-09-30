<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\OfficeAnalystReportService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => app(OfficeAnalystReportService::class)->generate('daily'))->name('office-analyst-daily-report')->dailyAt('18:15')->timezone('Asia/Jakarta')->withoutOverlapping();
Schedule::call(fn () => app(OfficeAnalystReportService::class)->generate('weekly'))->name('office-analyst-weekly-report')->weeklyOn(1, '18:30')->timezone('Asia/Jakarta')->withoutOverlapping();

Artisan::command('office:scheduled-generation', function () {
    $this->info(json_encode(app(\App\Services\OfficeScheduledGenerationService::class)->runDue(), JSON_PRETTY_PRINT));
})->purpose('Dispatch due Living Office content generation commands');

Schedule::call(fn () => app(\App\Services\OfficeScheduledGenerationService::class)->runDue())->everyFiveMinutes()->name('office-scheduled-generation')->timezone('Asia/Jakarta')->withoutOverlapping();

Schedule::call(function () {
    app(\App\Http\Controllers\OfficeContentPlannerController::class)->dispatchScheduledPublishing();
})->everyFiveMinutes()->name('office-scheduled-publishing')->timezone('Asia/Jakarta')->withoutOverlapping();
