<?php

namespace App\Services;

use App\Models\OfficeContentPlanItem;
use App\Models\OfficeContentSchedule;

/**
 * Menulis slot jadwal Instagram dari grid ke file yang dibaca worker tiap brand (satu file per brand, jadi Kauiz dan Jauki
 * tidak pernah tercampur). Hanya Feed dan Story Instagram; artikel dan Threads dikerjakan worker lain lewat perintah Office.
 * Satu jadwal mingguan dengan beberapa hari menjadi beberapa slot. Topik dan brief ikut ditulis supaya worker bisa membuat
 * gambar sesuai rencana grid. Nama kelas dipertahankan agar pemanggil lama tetap jalan.
 */
class KauizSlotSync
{
    /** Brand yang punya worker slot dan nama konfigurasi file slot-nya (config/<brand>.php). */
    public const BRANDS = ['kauiz', 'jauki'];

    public static function sync(): void
    {
        $slots = array_fill_keys(self::BRANDS, []);

        OfficeContentSchedule::query()
            ->where('is_active', true)
            ->where('schedule_type', 'recurring')
            ->with('brand')
            ->get()
            ->filter(fn (OfficeContentSchedule $schedule) => in_array($schedule->brand?->slug, self::BRANDS, true) && SlotWorkers::wantsSlot($schedule))
            ->each(function (OfficeContentSchedule $schedule) use (&$slots) {
                $rule = $schedule->recurrence_rule ?? [];
                if (($rule['frequency'] ?? null) !== 'weekly' || empty($rule['time'])) {
                    return;
                }

                $metadata = $schedule->metadata ?? [];
                $plan = isset($metadata['plan_item_id']) ? OfficeContentPlanItem::find($metadata['plan_item_id']) : null;
                if ($plan) $metadata = array_merge($plan->metadata ?? [], $metadata);

                foreach ($rule['days'] ?? [] as $day) {
                    $slots[$schedule->brand->slug][] = [
                        'id' => $schedule->id,
                        'brand' => $schedule->brand->slug,
                        'label' => $schedule->name,
                        'format' => strtolower($schedule->content_type),
                        'platform' => 'instagram',
                        'day' => (int) $day,
                        'time' => $rule['time'],
                        'timezone' => $schedule->timezone,
                        'lead_minutes' => SlotWorkers::leadMinutes(),
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

        foreach (self::BRANDS as $brand) {
            SlotFile::write(config($brand.'.slots_path'), $slots[$brand]);
        }
    }
}
