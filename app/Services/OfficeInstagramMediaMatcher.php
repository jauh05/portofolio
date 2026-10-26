<?php

namespace App\Services;

use App\Models\OfficeContentItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Mencocokkan konten yang sudah terbit dengan ID media Instagram lewat caption.
 * Caption dicari di kedua akun, karena konten bisa terbit di akun lain dari brand-nya.
 * Hasilnya disimpan di metadata (instagram_media_id dan instagram_account), bukan di external_id,
 * supaya event bridge tetap menemukan konten yang sama lewat external_id yang lama.
 */
class OfficeInstagramMediaMatcher
{
    private const MAX_PAGES = 20;

    private const ACCOUNTS = ['jauki', 'kauiz'];

    // Caption yang lebih pendek dari ini terlalu mudah cocok dengan post lain.
    private const MIN_CAPTION_LENGTH = 15;

    // Konten tanpa teks dicocokkan dengan post yang terbit dalam selisih waktu ini.
    private const TIME_WINDOW_SECONDS = 600;

    /** @return array{matched: int, ambiguous: int, not_found: int} */
    public function match(bool $dryRun = false): array
    {
        $result = ['matched' => 0, 'ambiguous' => 0, 'not_found' => 0];
        $claimed = [];
        $media = collect(self::ACCOUNTS)->flatMap(fn (string $account) => $this->accountMedia($account));

        $items = OfficeContentItem::with('brand')
            ->whereNotNull('external_id')->where('platform', 'instagram')->get()
            ->reject(fn (OfficeContentItem $item) => ! empty(($item->metadata ?? [])['instagram_media_id'] ?? null))
            ->reject(fn (OfficeContentItem $item) => ctype_digit((string) $item->external_id));

        foreach ($items as $item) {
            $caption = $this->normalize($item->text);
            $matches = match (true) {
                $caption === '' => $this->samePublishTime($item, $media, $claimed),
                mb_strlen($caption) < self::MIN_CAPTION_LENGTH => collect(),
                default => $media->where('caption', $caption)->reject(fn (array $row) => in_array($row['id'], $claimed, true))->values(),
            };

            if ($matches->count() === 1) {
                $match = $matches->first();
                $claimed[] = $match['id'];
                $result['matched']++;

                if (! $dryRun) {
                    $item->update(['metadata' => array_merge($item->metadata ?? [], [
                        'instagram_media_id' => $match['id'],
                        'instagram_account' => $match['account'],
                    ])]);
                }
            } elseif ($matches->count() > 1) {
                $result['ambiguous']++;
            } else {
                $result['not_found']++;
            }
        }

        return $result;
    }

    /** @return Collection<int, array{id: string, caption: string, account: string}> */
    private function accountMedia(string $account): Collection
    {
        $token = OfficeContentMetricsService::tokenForAccount($account);

        return $token === '' ? collect() : $this->recentMedia($token)->map(fn (array $row) => $row + ['account' => $account]);
    }

    /** @return Collection<int, array{id: string, caption: string, timestamp: ?CarbonImmutable}> */
    private function recentMedia(string $token): Collection
    {
        $host = rtrim((string) config('services.instagram.host'), '/');
        $version = (string) config('services.instagram.version');
        // Halaman berikutnya diminta dengan cursor dan token yang sama. URL "next" dari Meta tidak dipakai.
        $query = ['fields' => 'id,caption,timestamp', 'limit' => 50, 'access_token' => $token];
        $media = collect();

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $response = Http::timeout(20)->get("{$host}/{$version}/me/media", $query)->throw();
            $media = $media->concat(collect($response->json('data', []))->map(fn (array $row) => [
                'id' => (string) $row['id'],
                'caption' => $this->normalize($row['caption'] ?? ''),
                'timestamp' => isset($row['timestamp']) ? CarbonImmutable::parse($row['timestamp']) : null,
            ]));

            $after = $response->json('paging.cursors.after');
            if (! $response->json('paging.next') || $after === null) {
                break;
            }
            $query['after'] = $after;
        }

        return $media;
    }

    /** Konten tanpa teks dicocokkan dengan post dari akun brand-nya yang terbit dalam selisih waktu yang dekat. */
    private function samePublishTime(OfficeContentItem $item, Collection $media, array $claimed): Collection
    {
        if ($item->published_at === null) {
            return collect();
        }

        $account = $item->brand?->slug === 'kauiz' ? 'kauiz' : 'jauki';

        return $media->where('account', $account)
            ->filter(fn (array $row) => $row['timestamp'] !== null && abs($row['timestamp']->diffInSeconds($item->published_at)) <= self::TIME_WINDOW_SECONDS)
            ->reject(fn (array $row) => in_array($row['id'], $claimed, true))
            ->values();
    }

    /** Hanya huruf dan angka yang dibandingkan. Emoji, tanda baca, dan spasi diabaikan. */
    private function normalize(?string $text): string
    {
        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower((string) $text)));
    }
}
