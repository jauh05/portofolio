<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

/**
 * Membaca riwayat render bot Reels (content-bot/history.json) untuk tabel hasil generate.
 */
class ReelsHistoryReader
{
    public static function all(): array
    {
        $path = config('reels.history_path');
        if (! File::exists($path)) {
            return [];
        }

        $history = json_decode(File::get($path), true);
        $base = rtrim((string) config('reels.public_base_url'), '/');

        return collect(is_array($history) ? $history : [])->map(function (array $entry) use ($base) {
            $status = match (true) {
                ! empty($entry['instagram_media_id']) => 'published',
                ! empty($entry['post_error']) => 'failed',
                default => 'generated',
            };

            return [
                'id' => $entry['id'] ?? null,
                'source' => 'reels',
                'platform' => 'instagram',
                'contentType' => 'reels',
                'brand' => 'jauki',
                'title' => $entry['overlay'] ?? '',
                'text' => $entry['caption'] ?? '',
                'videoUrl' => ($base !== '' && ! empty($entry['output'])) ? $base.'/'.$entry['output'] : null,
                'imageUrl' => null,
                'status' => $status,
                'scheduledAt' => $entry['slot_at'] ?? $entry['created_at'] ?? null,
                'publishedAt' => $entry['posted_at'] ?? null,
                'error' => $entry['post_error'] ?? null,
                'mediaId' => $entry['instagram_media_id'] ?? null,
            ];
        })->values()->all();
    }
}
