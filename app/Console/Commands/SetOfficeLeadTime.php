<?php

namespace App\Console\Commands;

use App\Models\OfficeContentSchedule;
use App\Services\SlotWorkers;
use Illuminate\Console\Command;

/**
 * Mengatur sehari sebelumnya (atau menit lain) sebagai waktu pembuatan konten untuk jadwal yang dikerjakan lewat perintah Office
 * (Threads dan artikel). Feed dan Story Kauiz dan Jauki mengikuti SLOT_LEAD_MINUTES, bukan nilai di jadwal.
 */
class SetOfficeLeadTime extends Command
{
    protected $signature = 'office:set-lead-time {minutes=1440 : menit sebelum jam tayang} {--include-manual : jadwal bertimbang manual ikut diubah menjadi otomatis} {--dry-run : hanya tampilkan perubahan}';

    protected $description = 'Atur jadwal aktif agar kontennya dibuat sehari sebelum jam tayang';

    public function handle(): int
    {
        $minutes = (int) $this->argument('minutes');
        if ($minutes < 0 || $minutes > 10080) {
            $this->error('Menit harus antara 0 dan 10080.');
            return self::FAILURE;
        }

        $changed = $review = 0;
        OfficeContentSchedule::with('brand')->where('is_active', true)->get()->each(function (OfficeContentSchedule $schedule) use ($minutes, &$changed, &$review) {
            if (SlotWorkers::manages($schedule)) {
                return; // diatur SLOT_LEAD_MINUTES lewat file slot
            }
            $automatic = in_array($schedule->generation_timing, ['lead_time', 'immediate'], true);
            if (! $automatic && ! $this->option('include-manual')) {
                return;
            }
            if (($schedule->publishing_mode ?? 'review') === 'review') {
                $review++;
            }
            $same = $schedule->generation_timing === 'lead_time' && (int) $schedule->generation_lead_minutes === $minutes;
            if ($same) {
                return;
            }
            $this->line(sprintf('%s %s/%s: %s %s -> lead_time %d', $this->option('dry-run') ? '[dry-run]' : 'ubah', $schedule->brand?->slug, $schedule->platform, $schedule->name,
                $schedule->generation_timing.'/'.($schedule->generation_lead_minutes ?? '-'), $minutes));
            $changed++;
            if (! $this->option('dry-run')) {
                $schedule->generation_timing = 'lead_time';
                $schedule->generation_lead_minutes = $minutes;
                $schedule->generation_mode = 'automatic';
                $schedule->save();
            }
        });

        $this->info("{$changed} jadwal ".($this->option('dry-run') ? 'akan diubah.' : 'diubah.'));
        if ($review > 0) {
            $this->warn("{$review} jadwal berstatus publishing 'review': hasilnya baru terbit setelah Anda menyetujuinya. Ubah ke 'automatic' di dashboard bila ingin langsung terbit.");
        }

        return self::SUCCESS;
    }
}
