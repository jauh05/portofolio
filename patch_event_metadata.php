<?php
$file = 'app/Services/OfficeEventService.php';
$content = file_get_contents($file);
$content = str_replace(
    "'metadata' => ['raw_result' => \$result],",
    "'metadata' => array_merge(\$run->metadata ?? [], ['raw_result' => \$result]),",
    $content
);
file_put_contents($file, $content);
