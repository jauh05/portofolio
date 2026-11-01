<?php

namespace App\Services;

use App\Models\OfficeContentPlanItem;
use App\Models\OfficeContentSchedule;
use Carbon\CarbonImmutable;

/**
 * Menulis slot jadwal Instagram dari grid ke file yang dibaca worker tiap brand (satu file per brand, jadi Kauiz dan Jauki
 * tidak pernah tercampur). Hanya Feed dan Story Instagram; artikel dan Threads dikerjakan worker lain lewat perintah Office.
 * Satu jadwal mingguan dengan beberapa hari menjadi beberapa slot, dan jadwal sekali jalan menjadi satu slot bertanggal. Hanya jadwal
 * berstatus publishing otomatis yang masuk, karena worker memposting tanpa tahap persetujuan. Topik dan brief ikut ditulis supaya worker bisa membuat
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
            ->with('brand')
            ->get()
            ->filter(fn (OfficeContentSchedule $schedule) => in_array($schedule->brand?->slug, self::BRANDS, true)
                && SlotWorkers::wantsSlot($schedule) && SlotWorkers::postsAutomatically($schedule))
            ->each(function (OfficeContentSchedule $schedule) use (&$slots) {
                $metadata = $schedule->metadata ?? [];
                $plan = isset($metadata['plan_item_id']) ? OfficeContentPlanItem::find($metadata['plan_item_id']) : null;
                if ($plan) $metadata = array_merge($plan->metadata ?? [], $metadata);

                $base = [
                    'id' => $schedule->id,
                    'brand' => $schedule->brand->slug,
                    'label' => $schedule->name,
                    'format' => strtolower($schedule->content_type),
                    'platform' => 'instagram',
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

                if ($schedule->schedule_type === 'one_time') {
                    // Sekali jalan: satu slot dengan tanggal. Yang sudah lewat lebih dari sehari atau masih jauh tidak ditulis.
                    $when = $schedule->scheduled_at
                        ? CarbonImmutable::parse($schedule->scheduled_at->format('Y-m-d H:i:s'), $schedule->timezone ?: 'Asia/Jakarta')
                        : null;
                    $now = CarbonImmutable::now($schedule->timezone ?: 'Asia/Jakarta');
                    if ($when && $when->greaterThanOrEqualTo($now->subDay()) && $when->lessThanOrEqualTo($now->addDays(60))) {
                        $slots[$schedule->brand->slug][] = $base + ['day' => $when->isoWeekday(), 'time' => $when->format('H:i'), 'date' => $when->toDateString()];
                    }
                    return;
                }

                $rule = $schedule->recurrence_rule ?? [];
                if ($schedule->schedule_type !== 'recurring' || ($rule['frequency'] ?? null) !== 'weekly' || empty($rule['time'])) {
                    return;
                }
                foreach ($rule['days'] ?? [] as $day) {
                    $slots[$schedule->brand->slug][] = $base + ['day' => (int) $day, 'time' => $rule['time']];
                }
            });

        foreach (self::BRANDS as $brand) {
            SlotFile::write(config($brand.'.slots_path'), $slots[$brand]);
        }
    }
}
