<?php

namespace App\Console\Commands;

use App\Services\KauizSlotSync;
use App\Services\SlotFile;
use Illuminate\Console\Command;

/**
 * Menulis ulang file slot Kauiz dan Jauki dari jadwal di database. File ini biasanya ditulis otomatis saat jadwal disimpan,
 * tetapi tidak ada sampai ada jadwal yang disimpan (misalnya setelah pemasangan), dan bisa hilang atau tertinggal.
 */
class SyncSlotWorkers extends Command
{
    protected $signature = 'office:sync-slots';

    protected $description = 'Tulis ulang file slot worker Instagram (Kauiz dan Jauki) dari jadwal aktif';

    public function handle(): int
    {
        KauizSlotSync::sync();
        foreach (KauizSlotSync::BRANDS as $brand) {
            $path = config($brand.'.slots_path');
            $this->line(sprintf('%-6s %d slot -> %s', $brand, count(SlotFile::read($path)), $path));
        }

        return self::SUCCESS;
    }
}
