<?php

namespace Tests\Feature;

use App\Models\OfficeCommand;
use App\Models\OfficeContentBrand;
use App\Models\OfficeContentItem;
use App\Models\OfficeContentMetric;
use App\Models\OfficeContentPlanItem;
use App\Models\OfficeContentSchedule;
use App\Models\OfficeResearchRun;
use App\Models\User;
use App\Services\OfficeAnalystReportService;
use App\Services\OfficeContentMetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalystDailyReportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private OfficeContentBrand $jauki;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['is_office_owner' => true]);
        $this->jauki = OfficeContentBrand::firstOrCreate(['slug' => 'jauki'], ['id' => (string) Str::uuid(), 'name' => 'Jauki']);
        $dir = sys_get_temp_dir().'/analyst-daily-'.uniqid();
        File::ensureDirectoryExists($dir);
        config([
            'reels.history_path' => $dir.'/history.json',
            'reels.hidden_path' => $dir.'/hidden.json',
            'reels.public_base_url' => 'https://example.test/reels',
            'services.instagram.token' => 'test-token',
            'services.instagram.host' => 'https://graph.example.test',
            'services.instagram.version' => 'v23.0',
        ]);
    }

    private function publishedItem(string $topic, string $mediaId, ?string $brandId = null): OfficeContentItem
    {
        $schedule = OfficeContentSchedule::create([
            'brand_id' => $brandId ?? $this->jauki->id, 'name' => $topic, 'schedule_type' => 'recurring', 'platform' => 'instagram',
            'content_type' => 'feed', 'topic' => $topic, 'timezone' => 'Asia/Jakarta', 'is_active' => true,
            'recurrence_rule' => ['frequency' => 'weekly', 'days' => [1], 'time' => '09:00'],
            'starts_at' => CarbonImmutable::parse('2026-10-01', 'Asia/Jakarta'), 'generation_mode' => 'manual', 'publishing_mode' => 'automatic',
        ]);

        return OfficeContentItem::create([
            'deduplication_key' => Str::uuid()->toString(), 'agent_id' => 'jauki-social', 'platform' => 'instagram', 'content_type' => 'feed',
            'brand_id' => $brandId ?? $this->jauki->id, 'schedule_id' => $schedule->id, 'title' => 'Draft: '.$topic,
            'status' => 'published', 'external_id' => $mediaId, 'published_at' => now('Asia/Jakarta'), 'generated_at' => now('Asia/Jakarta'),
        ]);
    }

    public function test_report_accepts_the_new_scopes(): void
    {
        $this->actingAs($this->owner)->postJson('/office/api/analyst-reports/generate', [
            'report_type' => 'manual', 'scope' => 'dalam',
        ])->assertCreated()->assertJsonPath('metadata.filters.scope', 'dalam');

        $this->actingAs($this->owner)->postJson('/office/api/analyst-reports/generate', [
            'report_type' => 'manual', 'scope' => 'luar',
        ])->assertCreated();

        $this->actingAs($this->owner)->postJson('/office/api/analyst-reports/generate', [
            'report_type' => 'manual', 'scope' => 'bukan-scope',
        ])->assertStatus(422)->assertJsonValidationErrors(['scope']);
    }

    public function test_refresh_stores_views_and_likes_per_topic_from_instagram_insights(): void
    {
        $this->publishedItem('tips belajar', '1799');
        Http::fake(['graph.example.test/*' => Http::response(['data' => [
            ['name' => 'views', 'period' => 'lifetime', 'values' => [['value' => 120]]],
            ['name' => 'likes', 'period' => 'lifetime', 'values' => [['value' => 9]]],
        ]])]);

        $result = app(OfficeContentMetricsService::class)->refresh();

        $this->assertSame(['status' => 'ok', 'fetched' => 1, 'failed' => 0], $result);
        $metric = OfficeContentMetric::where('media_id', '1799')->sole();
        $this->assertSame(120, $metric->views);
        $this->assertSame(9, $metric->likes);
        $this->assertSame('tips belajar', $metric->topic);
        $this->assertSame('tips belajar', OfficeContentMetricsService::topicTotals()->first()['topic']);
    }

    public function test_refresh_is_skipped_without_a_token(): void
    {
        config(['services.instagram.token' => null]);

        $result = app(OfficeContentMetricsService::class)->refresh();

        $this->assertSame('skipped', $result['status']);
        $this->assertSame(0, OfficeContentMetric::count());
    }

    public function test_dalam_recommendations_come_from_performance_and_luar_from_research(): void
    {
        OfficeContentMetric::create(['platform' => 'instagram', 'media_id' => '1', 'topic' => 'pomodoro', 'views' => 50, 'likes' => 5, 'fetched_at' => now()]);
        OfficeContentMetric::create(['platform' => 'instagram', 'media_id' => '2', 'topic' => 'active recall', 'views' => 300, 'likes' => 20, 'fetched_at' => now()]);
        $run = OfficeResearchRun::create([
            'query' => 'tren belajar', 'status' => 'completed', 'sources' => ['a'],
            'trends' => ['Jurnal refleksi'], 'content_ideas' => [['topic' => 'Peta konsep']], 'created_at' => now(),
        ]);
        $analyst = app(OfficeAnalystReportService::class);

        $dalam = $analyst->topicRecommendations('dalam', $run);
        $this->assertSame(['active recall', 'pomodoro'], array_column($dalam, 'topic'));
        $this->assertSame(['dalam', 'dalam'], array_column($dalam, 'source'));

        $luar = $analyst->topicRecommendations('luar', $run);
        $this->assertSame(['Jurnal refleksi', 'Peta konsep'], array_column($luar, 'topic'));
        $this->assertSame(['luar', 'luar'], array_column($luar, 'source'));

        $both = $analyst->topicRecommendations('keduanya', $run);
        $this->assertSame('luar', $both[0]['source']);
        $this->assertContains('active recall', array_column($both, 'topic'));
    }

    public function test_feed_format_uses_the_existing_generator_and_reels_becomes_a_plan(): void
    {
        $this->actingAs($this->owner)->postJson('/office/api/analyst-reports/generate-format', [
            'brand_slug' => 'jauki', 'format' => 'feed', 'topic' => 'tips belajar',
        ])->assertCreated()->assertJsonPath('status', 'generated');

        $this->assertSame(1, OfficeContentItem::count());
        $this->assertSame(1, OfficeCommand::count());

        $this->actingAs($this->owner)->postJson('/office/api/analyst-reports/generate-format', [
            'brand_slug' => 'jauki', 'format' => 'reels', 'topic' => 'tips belajar',
        ])->assertCreated()->assertJsonPath('status', 'proposed');

        $plan = OfficeContentPlanItem::sole();
        $this->assertSame('reels', $plan->content_type);
        $this->assertSame('tips belajar', $plan->topic);
    }

    public function test_charts_endpoint_lists_topics_daily_content_and_social_series(): void
    {
        $item = $this->publishedItem('tips belajar', '1799');
        OfficeContentMetric::create(['platform' => 'instagram', 'media_id' => '1799', 'brand_slug' => 'jauki', 'topic' => 'tips belajar', 'views' => 120, 'likes' => 9, 'published_at' => now('Asia/Jakarta'), 'fetched_at' => now()]);

        $response = $this->actingAs($this->owner)->getJson('/office/api/analyst-reports/charts')->assertOk();

        $this->assertSame('tips belajar', $response->json('data.topics.0.topic'));
        $this->assertCount(14, $response->json('data.contentPerDay'));
        $this->assertSame(120, $response->json('data.topicMetrics.0.views'));
        $this->assertArrayHasKey('kauiz', $response->json('data.social'));
        $this->assertArrayHasKey('jauki', $response->json('data.social'));
        $this->assertSame($item->id, OfficeContentItem::sole()->id);
    }
}
