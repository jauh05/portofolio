<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// run the logic manually
$run = \App\Models\OfficeResearchRun::first();
echo "Command ID: " . ($run->command_id ?? 'NULL') . "\n";
