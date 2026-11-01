<?php

return [
    // Brand yang Feed dan Story Instagram-nya dibuat dan diposting worker slot (Muse), bukan lewat perintah Office ke bot lama.
    // Kosongkan (SLOT_WORKER_BRANDS=) untuk mengembalikan semuanya ke jalur Office.
    'brands' => array_values(array_filter(array_map('trim', explode(',', (string) env('SLOT_WORKER_BRANDS', 'kauiz,jauki'))))),

    // Gambar dibuat sejak sekian menit sebelum jam tayang. 1440 = sehari sebelumnya.
    'lead_minutes' => (int) env('SLOT_LEAD_MINUTES', 1440),
];
