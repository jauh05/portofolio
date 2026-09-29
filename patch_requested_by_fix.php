<?php
$file = 'app/Services/OfficeAnalystReportService.php';
$content = file_get_contents($file);

$badRun = "'status' => 'queued',\n            'requested_by' => auth()->id() ?? \App\Models\User::where('is_office_owner', true)->first()?->id,
            'started_at'";

$goodRun = "'status' => 'queued',
            'started_at'";

$content = str_replace($badRun, $goodRun, $content);
file_put_contents($file, $content);
