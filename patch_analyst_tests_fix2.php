<?php
$file = 'tests/Feature/LivingOfficeV2Test.php';
$content = file_get_contents($file);

$content = str_replace(
    "postJson('/office/api/events'",
    "postJson('/api/office/events'",
    $content
);

file_put_contents($file, $content);
