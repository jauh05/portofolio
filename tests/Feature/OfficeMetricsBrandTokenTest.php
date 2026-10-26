<?php

namespace Tests\Feature;

use App\Models\OfficeContentBrand;
use App\Models\OfficeContentItem;
use App\Services\OfficeContentMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfficeMetricsBrandTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.instagram.token' => 'token-jauki', 'services.instagram.kauiz_token' => 'token-kauiz']);
        Http::fake(['*' => Http::response(['data' => []])]);
    }

    public function test_each_account_uses_its_own_instagram_token(): void
    {
        $this->item('jauki', 'media-jauki', ['instagram_media_id' => 'media-jauki', 'instagram_account' => 'jauki']);
        $this->item('kauiz', 'media-kauiz', ['instagram_media_id' => 'media-kauiz', 'instagram_account' => 'kauiz']);

        app(OfficeContentMetricsService::class)->refresh();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/media-jauki/insights') && str_contains($request->url(), 'access_token=token-jauki'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/media-kauiz/insights') && str_contains($request->url(), 'access_token=token-kauiz'));
    }

    public function test_kauiz_media_is_not_fetched_with_the_jauki_token_when_kauiz_token_is_missing(): void
    {
        config(['services.instagram.kauiz_token' => null]);
        $this->item('kauiz', 'media-kauiz', ['instagram_media_id' => 'media-kauiz', 'instagram_account' => 'kauiz']);

        $result = app(OfficeContentMetricsService::class)->refresh();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/media-kauiz/insights'));
        $this->assertSame(1, $result['failed']);
    }

    public function test_items_without_a_matched_media_id_are_not_fetched(): void
    {
        $this->item('jauki', 'uuid-belum-cocok', []);

        $result = app(OfficeContentMetricsService::class)->refresh();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'uuid-belum-cocok'));
        $this->assertSame(['status' => 'ok', 'fetched' => 0, 'failed' => 0], $result);
    }

    /** @param array<string, mixed> $metadata */
    private function item(string $brandSlug, string $externalId, array $metadata): void
    {
        $brand = OfficeContentBrand::firstOrCreate(['slug' => $brandSlug], ['id' => (string) Str::uuid(), 'name' => ucfirst($brandSlug)]);
        OfficeContentItem::create([
            'deduplication_key' => $externalId, 'agent_id' => 'jauki-social', 'platform' => 'instagram', 'content_type' => 'feed',
            'brand_id' => $brand->id, 'title' => $externalId, 'status' => 'published', 'metadata' => $metadata,
            'external_id' => $externalId, 'published_at' => now('Asia/Jakarta'),
        ]);
    }
}
