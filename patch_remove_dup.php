<?php
$file = 'tests/Feature/LivingOfficeV2Test.php';
$content = file_get_contents($file);

// Just delete everything from line 909
$lines = explode("\n", $content);
$newLines = [];
$found = false;
foreach ($lines as $line) {
    if (str_contains($line, "test('office bridge token is not rendered into the dashboard'") && $found) {
        break; // skip the second occurrence
    }
    if (str_contains($line, "test('office bridge token is not rendered into the dashboard'")) {
        $found = true;
    }
    $newLines[] = $line;
}
file_put_contents($file, implode("\n", $newLines));
