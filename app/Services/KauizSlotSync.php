<?php

namespace App\Services;

use App\Models\OfficeContentSchedule;

/**
 * Menulis slot jadwal Kauiz dari grid ke file yang dibaca worker Kauiz.
 * Satu jadwal mingguan dengan beberapa hari menjadi beberapa slot.
 */
class KauizSlotSync
{
    public static function sync(): void
    {
        $slots = [];

        OfficeContentSchedule::query()
            ->where('is_active', true)
            ->where('schedule_type', 'recurring')
            ->with('brand')
            ->get()
            ->filter(fn (OfficeContentSchedule $schedule) => $schedule->brand?->slug === 'kauiz')
            ->each(function (OfficeContentSchedule $schedule) use (&$slots) {
                $rule = $schedule->recurrence_rule ?? [];
                if (($rule['frequency'] ?? null) !== 'weekly' || empty($rule['time'])) {
                    return;
                }
                foreach ($rule['days'] ?? [] as $day) {
                    $slots[] = [
                        'id' => $schedule->id,
                        'label' => $schedule->name,
                        'format' => $schedule->content_type,
                        'platform' => $schedule->platform,
                        'day' => (int) $day,
                        'time' => $rule['time'],
                        'timezone' => $schedule->timezone,
                        'lead_minutes' => (int) ($schedule->generation_lead_minutes ?? 120),
                    ];
                }
            });

        SlotFile::write(config('kauiz.slots_path'), $slots);
    }
}
