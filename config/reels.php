<?php

return [
    // File pengaturan generate Reels. Dibaca bot Python (REELS_SETTINGS_FILE) dan ditulis dari dashboard.
    'settings_path' => env('REELS_SETTINGS_FILE', storage_path('app/reels/settings.json')),

    // Slot jadwal Reels di grid. Dibaca bot (REELS_SLOTS_FILE) untuk menentukan kapan render dan posting.
    'slots_path' => env('REELS_SLOTS_FILE', storage_path('app/reels/slots.json')),

    // Riwayat render bot Reels. Dibaca dashboard untuk tabel hasil generate.
    'history_path' => env('REELS_HISTORY_FILE', base_path('content-bot/history.json')),

    // Alamat publik folder output bot, misalnya https://jauharfauzi.my.id/reels
    'public_base_url' => env('PUBLIC_VIDEO_BASE_URL', ''),
];
