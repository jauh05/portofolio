<?php
$file = 'app/Http/Controllers/OfficeContentPlannerController.php';
$content = file_get_contents($file);
$content = str_replace(
    "\$generator->ensureDraft(\$schedule);",
    "\$draft = \$generator->ensureDraft(\$schedule);\n        if (\$schedule->generation_mode === 'automatic') {\n            \$this->dispatchGeneration(\$draft);\n        }",
    $content
);
file_put_contents($file, $content);
