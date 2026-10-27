<?php

namespace App\Services;

use App\Models\OfficeContentPlanItem;
use App\Models\OfficeContentSchedule;

/**
 * Menulis slot jadwal Kauiz dari grid ke file yang dibaca worker Kauiz.
 * Satu jadwal mingguan dengan beberapa hari menjadi beberapa slot.
 * Topik dan brief ikut ditulis supaya worker bisa membuat gambar sesuai rencana grid.
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

                $metadata = $schedule->metadata ?? [];
                $plan = isset($metadata['plan_item_id']) ? OfficeContentPlanItem::find($metadata['plan_item_id']) : null;
                if ($plan) $metadata = array_merge($plan->metadata ?? [], $metadata);

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
                        'topic' => $schedule->topic,
                        'brief' => $schedule->brief,
                        'objective' => $metadata['objective'] ?? null,
                        'angle' => $metadata['angle'] ?? null,
                        'hook_direction' => $metadata['hook_direction'] ?? null,
                        'key_message' => $metadata['key_message'] ?? null,
                        'cta' => $metadata['cta'] ?? null,
                        'visual_prompt' => $metadata['visual_prompt'] ?? null,
                    ];
                }
            });

        SlotFile::write(config('kauiz.slots_path'), $slots);
    }
}
