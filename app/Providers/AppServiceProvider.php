<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\OfficeCommand::created(function ($command) {
            app(\App\Services\OfficeTelegramNotifier::class)->handleUserAction($command);
        });

        \App\Models\OfficeCommand::updated(function ($command) {
            if ($command->isDirty('status')) {
                app(\App\Services\OfficeTelegramNotifier::class)->handleCommandStatus($command);
            }
        });

        \App\Models\OfficeTask::created(function ($task) {
            app(\App\Services\OfficeTelegramNotifier::class)->handleTask($task);
        });

        \App\Models\OfficeTask::updated(function ($task) {
            if ($task->isDirty('status')) {
                app(\App\Services\OfficeTelegramNotifier::class)->handleTask($task);
            }
        });

        \App\Models\OfficeContentItem::created(function ($item) {
            app(\App\Services\OfficeTelegramNotifier::class)->handleContent($item);
        });

        \App\Models\OfficeContentItem::updated(function ($item) {
            if ($item->isDirty('status')) {
                app(\App\Services\OfficeTelegramNotifier::class)->handleContent($item);
            }
        });

        \App\Models\OfficeResearchRun::updated(function ($run) {
            if ($run->isDirty('status')) {
                app(\App\Services\OfficeTelegramNotifier::class)->handleResearch($run);
            }
        });

        \App\Models\OfficeNotification::created(function ($notif) {
            if ($notif->severity === 'error') {
                app(\App\Services\OfficeTelegramNotifier::class)->handleError($notif);
            }
        });
    }
}
