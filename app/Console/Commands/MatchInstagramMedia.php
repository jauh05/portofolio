<?php

namespace App\Console\Commands;

use App\Services\OfficeInstagramMediaMatcher;
use Illuminate\Console\Command;

class MatchInstagramMedia extends Command
{
    protected $signature = 'office:match-instagram-media {--dry-run : Tampilkan hasil tanpa menyimpan}';

    protected $description = 'Cocokkan konten yang sudah terbit dengan ID media Instagram lewat caption';

    public function handle(OfficeInstagramMediaMatcher $matcher): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $matcher->match($dryRun);

        $this->info(sprintf(
            'Dicocokkan: %d, caption ganda: %d, tidak ditemukan: %d%s',
            $result['matched'],
            $result['ambiguous'],
            $result['not_found'],
            $dryRun ? ' (dry-run, belum disimpan)' : '',
        ));

        return self::SUCCESS;
    }
}
