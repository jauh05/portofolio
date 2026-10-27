<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

/**
 * Baca dan tulis file JSON slot secara atomik, supaya bot atau worker tidak pernah membaca file setengah jadi.
 */
class SlotFile
{
    public static function read(string $path): array
    {
        if (! File::exists($path)) {
            return [];
        }

        $data = json_decode(File::get($path), true);

        return is_array($data) ? array_values($data) : [];
    }

    public static function write(string $path, array $data): void
    {
        File::ensureDirectoryExists(dirname($path));
        $tmp = $path.'.tmp';
        File::put($tmp, json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
        File::move($tmp, $path);
    }
}
