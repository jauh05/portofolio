<?php
$file = 'tests/Feature/LivingOfficeV2Test.php';
$content = file_get_contents($file);
$content = str_replace(
    "if (\$report->status !== 'ready') { dd(\$report->toArray(), \App\Models\OfficeResearchRun::first()->toArray()); } ",
    "",
    $content
);
file_put_contents($file, $content);
