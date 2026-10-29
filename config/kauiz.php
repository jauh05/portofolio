<?php

return [
    // Slot jadwal Kauiz dari grid. Ditulis otomatis dari jadwal Kauiz, dibaca worker Kauiz.
    'slots_path' => env('KAUIZ_SLOTS_FILE', storage_path('app/kauiz/slots.json')),
];
