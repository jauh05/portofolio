<?php

return [
    // Slot jadwal Instagram Jauki dari grid. Ditulis otomatis dari jadwal Jauki, dibaca worker Jauki.
    'slots_path' => env('JAUKI_SLOTS_FILE', storage_path('app/jauki/slots.json')),
];
