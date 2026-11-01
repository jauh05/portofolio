<?php

namespace App\Services;

use App\Models\OfficeContentSchedule;

/**
 * Feed dan Story Instagram Kauiz dan Jauki dikerjakan worker slot: gambar dibuat sehari sebelumnya dan diposting tepat jam tayang.
 * Jalur perintah Office (bot Jauki lama, publish_last) tidak boleh menyentuh jadwal ini, karena publish_last memakai token Jauki
 * dan menerbitkan draf terakhir bot, jadi jadwal Kauiz bisa tayang di akun yang salah.
 */
class SlotWorkers
{
    public const FORMATS = ['feed', 'story'];

    /** @return list<string> */
    public static function brands(): array
    {
        return (array) config('slotworkers.brands', []);
    }

    public static function leadMinutes(): int
    {
        return max(0, (int) config('slotworkers.lead_minutes', 1440));
    }

    public static function manages(OfficeContentSchedule $schedule): bool
    {
        return strtolower((string) $schedule->platform) === 'instagram'
            && in_array($schedule->brand?->slug, self::brands(), true);
    }

    /** Jadwal yang masuk file slot worker: Instagram feed atau story. Artikel dan Threads punya worker sendiri. */
    public static function wantsSlot(OfficeContentSchedule $schedule): bool
    {
        return strtolower((string) $schedule->platform) === 'instagram'
            && in_array(strtolower((string) $schedule->content_type), self::FORMATS, true);
    }
}
