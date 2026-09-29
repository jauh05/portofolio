<?php
$file = 'app/Http/Controllers/OfficeAnalystReportController.php';
$content = file_get_contents($file);

$content = str_replace(
    "'action_ids' => ['required', 'array']",
    "'plan_ids' => ['required', 'array']",
    $content
);

$content = str_replace(
    "\$service->approve(\$report, \$data['action_ids'])",
    "\$service->approve(\$report, \$data['plan_ids'])",
    $content
);

$content = str_replace(
    "\$service->dismiss(\$report, \$data['action_ids'])",
    "\$service->dismiss(\$report, \$data['plan_ids'])",
    $content
);

file_put_contents($file, $content);
