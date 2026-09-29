<?php

$file = 'app/Services/OfficeAnalystReportService.php';
$content = file_get_contents($file);
$content = str_replace(
    "'status' => 'queued',",
    "'status' => 'queued',\n            'requested_by' => auth()->id() ?? \App\Models\User::where('is_office_owner', true)->first()?->id,",
    $content
);
file_put_contents($file, $content);

$file = 'app/Http/Controllers/OfficeContentPlannerController.php';
$content = file_get_contents($file);
$content = str_replace(
    "'status' => 'queued',",
    "'status' => 'queued',\n            'requested_by' => auth()->id() ?? \App\Models\User::where('is_office_owner', true)->first()?->id,",
    $content
);
file_put_contents($file, $content);

