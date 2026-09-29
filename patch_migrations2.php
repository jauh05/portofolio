<?php
$files = glob('database/migrations/*_create_office_research_runs_table.php');
$file = $files[0];
$content = file_get_contents($file);
$content = str_replace('\App\Models\ContentBrand::class', '\App\Models\OfficeContentBrand::class', $content);
file_put_contents($file, $content);

$files = glob('database/migrations/*_create_office_content_plan_items_table.php');
$file = $files[0];
$content = file_get_contents($file);
$content = str_replace('\App\Models\ContentBrand::class', '\App\Models\OfficeContentBrand::class', $content);
file_put_contents($file, $content);
