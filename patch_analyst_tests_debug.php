<?php
$file = 'tests/Feature/LivingOfficeV2Test.php';
$content = file_get_contents($file);

$content = str_replace(
    "expect(\$report->status)->toBe('ready')",
    "if (\$report->status !== 'ready') { dd(\$report->toArray(), \App\Models\OfficeResearchRun::first()->toArray()); } expect(\$report->status)->toBe('ready')",
    $content
);

file_put_contents($file, $content);
