<?php

namespace App\Services;

use App\Models\OfficeContentItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Mencocokkan konten yang sudah terbit dengan ID media Instagram lewat caption.
 * ID disimpan di metadata.instagram_media_id, bukan di external_id, supaya event bridge
 * tetap menemukan konten yang sama lewat external_id yang lama.
 */
class OfficeInstagramMediaMatcher
{
    private const MAX_PAGES = 20;

    /** @return array{matched: int, ambiguous: int, not_found: int} */
    public function match(bool $dryRun = false): array
    {
        $result = ['matched' => 0, 'ambiguous' => 0, 'not_found' => 0];
        $claimed = [];

        $items = OfficeContentItem::with('brand')
            ->whereNotNull('external_id')->where('platform', 'instagram')->get()
            ->reject(fn (OfficeContentItem $item) => ! empty(($item->metadata ?? [])['instagram_media_id'] ?? null));

        foreach ($items->groupBy(fn (OfficeContentItem $item) => $item->brand?->slug ?? 'jauki') as $brandSlug => $brandItems) {
            $token = OfficeContentMetricsService::tokenFor((string) $brandSlug);
            $media = $token === '' ? collect() : $this->recentMedia($token);

            foreach ($brandItems as $item) {
                $caption = $this->normalize($item->text);
                $matches = $caption === ''
                    ? collect()
                    : $media->where('caption', $caption)->reject(fn (array $row) => in_array($row['id'], $claimed, true))->values();

                if ($matches->count() === 1) {
                    $mediaId = $matches->first()['id'];
                    $claimed[] = $mediaId;
                    $result['matched']++;

                    if (! $dryRun) {
                        $item->update(['metadata' => array_merge($item->metadata ?? [], ['instagram_media_id' => $mediaId])]);
                    }
                } elseif ($matches->count() > 1) {
                    $result['ambiguous']++;
                } else {
                    $result['not_found']++;
                }
            }
        }

        return $result;
    }

    /** @return Collection<int, array{id: string, caption: string}> */
    private function recentMedia(string $token): Collection
    {
        $host = rtrim((string) config('services.instagram.host'), '/');
        $version = (string) config('services.instagram.version');
        $url = "{$host}/{$version}/me/media";
        $query = ['fields' => 'id,caption', 'limit' => 50, 'access_token' => $token];
        $media = collect();

        for ($page = 0; $page < self::MAX_PAGES && $url; $page++) {
            $response = Http::timeout(20)->get($url, $query)->throw();
            $media = $media->concat(collect($response->json('data', []))->map(fn (array $row) => [
                'id' => (string) $row['id'],
                'caption' => $this->normalize($row['caption'] ?? ''),
            ]));
            $url = $response->json('paging.next');
            $query = [];
        }

        return $media;
    }

    private function normalize(?string $text): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $text)));
    }
}
