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

    public function test_each_brand_uses_its_own_instagram_token(): void
    {
        $this->publishedItem('jauki', 'media-jauki');
        $this->publishedItem('kauiz', 'media-kauiz');

        app(OfficeContentMetricsService::class)->refresh();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/media-jauki/insights') && str_contains($request->url(), 'access_token=token-jauki'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/media-kauiz/insights') && str_contains($request->url(), 'access_token=token-kauiz'));
    }

    public function test_kauiz_media_is_not_fetched_with_the_jauki_token_when_kauiz_token_is_missing(): void
    {
        config(['services.instagram.kauiz_token' => null]);
        $this->publishedItem('kauiz', 'media-kauiz');

        $result = app(OfficeContentMetricsService::class)->refresh();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/media-kauiz/insights'));
        $this->assertSame(1, $result['failed']);
    }

    private function publishedItem(string $brandSlug, string $externalId): void
    {
        $brand = OfficeContentBrand::firstOrCreate(['slug' => $brandSlug], ['id' => (string) Str::uuid(), 'name' => ucfirst($brandSlug)]);
        OfficeContentItem::create([
            'deduplication_key' => $externalId, 'agent_id' => 'jauki-social', 'platform' => 'instagram', 'content_type' => 'feed',
            'brand_id' => $brand->id, 'title' => $externalId, 'status' => 'published', 'metadata' => [],
            'external_id' => $externalId, 'published_at' => now('Asia/Jakarta'),
        ]);
    }
}
