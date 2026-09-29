<?php
$file = 'app/Http/Controllers/OfficeAnalystReportController.php';
$content = file_get_contents($file);

$content = str_replace(
    "'action_ids' => ['required', 'array', 'min:1'], 'action_ids.*'",
    "'plan_ids' => ['required', 'array', 'min:1'], 'plan_ids.*'",
    $content
);

$content = str_replace(
    "['action_ids']",
    "['plan_ids']",
    $content
);

file_put_contents($file, $content);
