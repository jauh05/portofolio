<?php
$file = 'routes/console.php';
$content = file_get_contents($file);
$scheduler = "
Schedule::call(function () {
    app(\App\Http\Controllers\OfficeContentPlannerController::class)->dispatchScheduledPublishing();
})->everyFiveMinutes()->name('office-scheduled-publishing')->withoutOverlapping();
";
$content .= $scheduler;
file_put_contents($file, $content);
