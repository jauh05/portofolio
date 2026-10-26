<?php

namespace Tests\Feature;

use App\Models\OfficeContentBrand;
use App\Models\OfficeContentItem;
use App\Services\OfficeContentMetricsService;
use App\Services\OfficeInstagramMediaMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfficeInstagramMediaMatcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.instagram.token' => 'token-jauki', 'services.instagram.kauiz_token' => 'token-kauiz']);
    }

    public function test_feed_posts_get_their_instagram_media_id_from_the_matching_caption(): void
    {
        $this->fakeInstagram([['id' => '17999', 'caption' => 'Tulis tangan rapi']], [['id' => '18001', 'caption' => 'Tugas kuliah cepat']]);
        $jauki = $this->item('jauki', 'Tulis tangan rapi', 'uuid-jauki');
        $kauiz = $this->item('kauiz', 'Tugas kuliah cepat', 'uuid-kauiz');

        $result = app(OfficeInstagramMediaMatcher::class)->match(false);

        $this->assertSame(['matched' => 2, 'ambiguous' => 0, 'not_found' => 0], $result);
        $this->assertSame('17999', $jauki->fresh()->metadata['instagram_media_id']);
        $this->assertSame('18001', $kauiz->fresh()->metadata['instagram_media_id']);
        $this->assertSame('uuid-jauki', $jauki->fresh()->external_id);
    }

    public function test_dry_run_reports_matches_without_saving(): void
    {
        $this->fakeInstagram([['id' => '17999', 'caption' => 'Tulis tangan rapi']], []);
        $item = $this->item('jauki', 'Tulis tangan rapi', 'uuid-jauki');

        $result = app(OfficeInstagramMediaMatcher::class)->match(true);

        $this->assertSame(1, $result['matched']);
        $this->assertArrayNotHasKey('instagram_media_id', $item->fresh()->metadata);
    }

    public function test_caption_shared_by_two_posts_is_not_matched(): void
    {
        $this->fakeInstagram([['id' => '1', 'caption' => 'Sama'], ['id' => '2', 'caption' => 'Sama']], []);
        $item = $this->item('jauki', 'Sama', 'uuid-1');

        $result = app(OfficeInstagramMediaMatcher::class)->match(false);

        $this->assertSame(['matched' => 0, 'ambiguous' => 1, 'not_found' => 0], $result);
        $this->assertArrayNotHasKey('instagram_media_id', $item->fresh()->metadata);
    }

    public function test_metrics_are_fetched_with_the_matched_media_id_and_the_brand_token(): void
    {
        $this->fakeInstagram([['id' => '17999', 'caption' => 'Tulis tangan rapi']], [['id' => '18001', 'caption' => 'Tugas kuliah cepat']]);
        $this->item('jauki', 'Tulis tangan rapi', 'uuid-jauki');
        $this->item('kauiz', 'Tugas kuliah cepat', 'uuid-kauiz');
        app(OfficeInstagramMediaMatcher::class)->match(false);

        app(OfficeContentMetricsService::class)->refresh();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/17999/insights') && str_contains($request->url(), 'token-jauki'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/18001/insights') && str_contains($request->url(), 'token-kauiz'));
    }

    public function test_later_pages_of_media_are_requested_with_the_same_token(): void
    {
        Http::fake(function ($request) {
            if (! str_contains($request->url(), '/me/media')) {
                return Http::response(['data' => []]);
            }
            if (str_contains($request->url(), 'after=')) {
                // Halaman berikutnya harus memakai token yang sama. URL "next" dari Meta tidak boleh dipakai begitu saja.
                return str_contains($request->url(), 'access_token=token-jauki')
                    ? Http::response(['data' => [['id' => '17999', 'caption' => 'Tulis tangan rapi']], 'paging' => []])
                    : Http::response(['error' => ['message' => 'Invalid OAuth 2.0 Access Token', 'code' => 190]], 400);
            }

            return Http::response(['data' => [['id' => '17001', 'caption' => 'Post lain']], 'paging' => [
                'cursors' => ['after' => 'abc'],
                'next' => 'https://graph.instagram.com/v23.0/me/media?after=abc&access_token=token-lama',
            ]]);
        });
        $item = $this->item('jauki', 'Tulis tangan rapi', 'uuid-jauki');

        $result = app(OfficeInstagramMediaMatcher::class)->match(false);

        $this->assertSame(1, $result['matched']);
        $this->assertSame('17999', $item->fresh()->metadata['instagram_media_id']);
    }

    /** @param array<int, array{id: string, caption: string}> $jauki @param array<int, array{id: string, caption: string}> $kauiz */
    private function fakeInstagram(array $jauki, array $kauiz): void
    {
        Http::fake(function ($request) use ($jauki, $kauiz) {
            if (str_contains($request->url(), '/me/media')) {
                $posts = str_contains($request->url(), 'token-kauiz') ? $kauiz : $jauki;

                return Http::response(['data' => $posts, 'paging' => []]);
            }

            return Http::response(['data' => [
                ['name' => 'views', 'values' => [['value' => 5]]],
                ['name' => 'likes', 'values' => [['value' => 2]]],
            ]]);
        });
    }

    private function item(string $brandSlug, string $text, string $externalId): OfficeContentItem
    {
        $brand = OfficeContentBrand::firstOrCreate(['slug' => $brandSlug], ['id' => (string) Str::uuid(), 'name' => ucfirst($brandSlug)]);

        return OfficeContentItem::create([
            'deduplication_key' => $externalId, 'agent_id' => 'jauki-social', 'platform' => 'instagram', 'content_type' => 'feed',
            'brand_id' => $brand->id, 'title' => $externalId, 'text' => $text, 'status' => 'published', 'metadata' => [],
            'external_id' => $externalId, 'published_at' => now('Asia/Jakarta'),
        ]);
    }
}
