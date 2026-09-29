<?php
$file = 'app/Services/OfficeEventService.php';
$content = file_get_contents($file);
$content = str_replace(
    "\$published ? 'published' : (\$data['event'] === 'content.preview_ready' ? 'preview_ready' : 'generated')",
    "\$published ? 'published' : (\$data['event'] === 'content.preview_ready' ? 'preview_ready' : 'ready_for_review')",
    $content
);
file_put_contents($file, $content);
