<?php
$file = 'tests/Feature/LivingOfficeV2Test.php';
$content = file_get_contents($file);

$content = str_replace(
    "postJson('/office/api/bridge/events'",
    "postJson('/office/api/events'",
    $content
);

$content = str_replace(
    "\App\Models\OfficeContentBrand::create(['name' => 'Kauiz', 'slug' => 'kauiz', 'is_active' => true]);",
    "\App\Models\OfficeContentBrand::firstOrCreate(['slug' => 'kauiz'], ['name' => 'Kauiz', 'is_active' => true]);",
    $content
);

file_put_contents($file, $content);
