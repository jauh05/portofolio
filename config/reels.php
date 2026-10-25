<?php

return [
    // File pengaturan generate Reels. Dibaca bot Python (REELS_SETTINGS_FILE) dan ditulis dari dashboard.
    'settings_path' => env('REELS_SETTINGS_FILE', storage_path('app/reels/settings.json')),
];
