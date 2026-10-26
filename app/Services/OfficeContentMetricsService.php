<?php

namespace App\Services;

use App\Models\OfficeContentItem;
use App\Models\OfficeContentMetric;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Mengambil views dan likes dari Instagram Insights untuk konten yang sudah terposting.
 * Satu media = satu baris di office_content_metrics, dengan topik yang sudah dikunci,
 * supaya angka bisa dijumlahkan per topik untuk Temuan dan chart.
 */
class OfficeContentMetricsService
{
    private const METRICS = 'views,likes';

    /**
     * Kunci topik. Hanya topik jadwal yang dipakai: judul konten tidak dipakai karena judul generator
     * sering berupa nama format (mis. "Threads post") dan bukan topik.
     */
    public static function topicOf(?string $topic): ?string
    {
        $topic = trim((string) $topic);

        return $topic === '' ? null : Str::limit($topic, 255, '');
    }

    /** Views dan likes per topik, diurutkan dari yang paling ramai. */
    public static function topicTotals(): Collection
    {
        return OfficeContentMetric::query()->whereNotNull('topic')->get()
            ->groupBy('topic')
            ->map(fn (Collection $rows, string $topic) => [
                'topic' => $topic,
                'posts' => $rows->count(),
                'views' => (int) $rows->sum('views'),
                'likes' => (int) $rows->sum('likes'),
            ])
            ->sortByDesc(fn (array $row) => $row['views'] + $row['likes'] * 2)
            ->values();
    }

    public function configured(): bool
    {
        return (string) config('services.instagram.token') !== '' || (string) config('services.instagram.kauiz_token') !== '';
    }

    /** @return array{status: string, fetched: int, failed: int, reason?: string} */
    public function refresh(): array
    {
        if (! $this->configured()) {
            return ['status' => 'skipped', 'fetched' => 0, 'failed' => 0, 'reason' => 'INSTAGRAM_ACCESS_TOKEN belum diisi.'];
        }

        $fetched = 0;
        $failed = 0;
        foreach ($this->mediaList() as $media) {
            $token = $this->tokenFor($media['brand_slug']);
            if ($token === '') {
                $failed++;

                continue;
            }

            try {
                $metrics = $this->insights($media['media_id'], $token);
            } catch (\Throwable) {
                $failed++;

                continue;
            }

            OfficeContentMetric::updateOrCreate(
                ['platform' => $media['platform'], 'media_id' => $media['media_id']],
                [
                    'brand_slug' => $media['brand_slug'],
                    'topic' => $media['topic'],
                    'views' => $metrics['views'],
                    'likes' => $metrics['likes'],
                    'published_at' => $media['published_at'],
                    'fetched_at' => now('Asia/Jakarta'),
                ],
            );
            $fetched++;
        }

        return ['status' => 'ok', 'fetched' => $fetched, 'failed' => $failed];
    }

    /** Akun Kauiz memakai tokennya sendiri. Brand lain memakai INSTAGRAM_ACCESS_TOKEN (Jauki). */
    private function tokenFor(?string $brandSlug): string
    {
        return (string) ($brandSlug === 'kauiz' ? config('services.instagram.kauiz_token') : config('services.instagram.token'));
    }

    /** @return array{views: int, likes: int} */
    public function insights(string $mediaId, ?string $token = null): array
    {
        $host = rtrim((string) config('services.instagram.host'), '/');
        $version = (string) config('services.instagram.version');

        $response = Http::timeout(20)->get("{$host}/{$version}/{$mediaId}/insights", [
            'metric' => self::METRICS,
            'access_token' => $token ?? config('services.instagram.token'),
        ])->throw();

        $values = collect($response->json('data', []))->mapWithKeys(fn (array $metric) => [
            (string) ($metric['name'] ?? '') => (int) data_get($metric, 'values.0.value', 0),
        ]);

        return ['views' => (int) $values->get('views', 0), 'likes' => (int) $values->get('likes', 0)];
    }

    /** @return array<int, array{platform: string, media_id: string, brand_slug: ?string, topic: ?string, published_at: mixed}> */
    private function mediaList(): array
    {
        $items = OfficeContentItem::with(['brand', 'schedule'])
            ->whereNotNull('external_id')->where('platform', 'instagram')->get()
            ->map(fn (OfficeContentItem $item) => [
                'platform' => 'instagram',
                'media_id' => (string) $item->external_id,
                'brand_slug' => $item->brand?->slug,
                'topic' => self::topicOf($item->schedule?->topic),
                'published_at' => $item->published_at,
            ]);

        $reels = collect(ReelsHistoryReader::all())
            ->filter(fn (array $row) => ! empty($row['mediaId']))
            ->map(fn (array $row) => [
                'platform' => 'instagram',
                'media_id' => (string) $row['mediaId'],
                'brand_slug' => 'jauki',
                'topic' => self::topicOf($row['title'] ?? null),
                'published_at' => $row['publishedAt'] ?? null,
            ]);

        return $items->concat($reels)->unique(fn (array $row) => $row['platform'].':'.$row['media_id'])->values()->all();
    }
}
