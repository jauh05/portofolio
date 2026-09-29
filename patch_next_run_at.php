<?php
$file = 'app/Services/OfficeAnalystReportService.php';
$content = file_get_contents($file);
$content = str_replace(
    "'scheduled_at' => \$plan->scheduled_at,",
    "'scheduled_at' => \$plan->scheduled_at,\n                    'next_run_at' => \$plan->scheduled_at,",
    $content
);
file_put_contents($file, $content);
