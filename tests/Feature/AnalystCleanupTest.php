<?php

namespace Tests\Feature;

use App\Models\OfficeAnalystReport;
use App\Models\OfficeContentBrand;
use App\Models\OfficeContentItem;
use App\Models\OfficeContentSchedule;
use App\Models\OfficeResearchRun;
use App\Models\User;
use App\Services\OfficeAnalystReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalystCleanupTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private OfficeContentBrand $jauki;
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['is_office_owner' => true]);
        $this->jauki = OfficeContentBrand::firstOrCreate(['slug' => 'jauki'], ['id' => (string) Str::uuid(), 'name' => 'Jauki']);
        $this->dir = sys_get_temp_dir().'/analyst-cleanup-'.uniqid();
        File::ensureDirectoryExists($this->dir);
        config([
            'reels.history_path' => $this->dir.'/history.json',
            'reels.hidden_path' => $this->dir.'/hidden.json',
            'reels.slots_path' => $this->dir.'/slots.json',
            'reels.public_base_url' => 'https://example.test/reels',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function report(string $summary): OfficeAnalystReport
    {
        return OfficeAnalystReport::create([
            'report_date' => '2026-10-26', 'report_type' => 'daily', 'status' => 'ready', 'summary' => $summary,
            'findings' => [], 'conclusion' => '', 'recommendations' => [], 'next_actions' => [], 'metadata' => [],
            'generated_at' => now('Asia/Jakarta'),
        ]);
    }

    private function schedule(?string $topic, array $overrides = []): OfficeContentSchedule
    {
        return OfficeContentSchedule::create(array_merge([
            'brand_id' => $this->jauki->id, 'name' => $topic ?? 'Threads post', 'schedule_type' => 'recurring', 'platform' => 'instagram',
            'content_type' => 'feed', 'topic' => $topic, 'timezone' => 'Asia/Jakarta', 'is_active' => true,
            'recurrence_rule' => ['frequency' => 'weekly', 'days' => [1], 'time' => '09:00'],
            'starts_at' => '2026-10-01 00:00:00', 'generation_mode' => 'manual', 'publishing_mode' => 'automatic',
        ], $overrides));
    }

    public function test_hide_specific_reports_and_hide_all(): void
    {
        $first = $this->report('Laporan satu');
        $this->report('Laporan dua');

        $this->actingAs($this->owner)->postJson('/office/api/analyst-reports/hide', ['ids' => [$first->id]])
            ->assertOk()->assertJsonPath('hidden', 1);
        $this->actingAs($this->owner)->getJson('/office/api/analyst-reports')->assertJsonCount(1, 'data');

        $this->actingAs($this->owner)->postJson('/office/api/analyst-reports/hide', ['all' => true])
            ->assertOk()->assertJsonPath('hidden', 1);
        $this->actingAs($this->owner)->getJson('/office/api/analyst-reports')->assertJsonCount(0, 'data');
        $this->assertSame(2, OfficeAnalystReport::count(), 'data laporan tidak dihapus');
    }

    public function test_clear_results_hides_everything_except_items_in_flight(): void
    {
        File::put($this->dir.'/history.json', json_encode([[
            'id' => 'rl1', 'created_at' => '2026-10-26T14:00:00+07:00', 'overlay' => 'Teks satu', 'output' => 'satu.mp4',
        ]]));
        $schedule = $this->schedule('tips belajar');
        OfficeContentItem::create([
            'deduplication_key' => 'k1', 'agent_id' => 'jauki-social', 'platform' => 'instagram', 'content_type' => 'feed',
            'brand_id' => $this->jauki->id, 'schedule_id' => $schedule->id, 'title' => 'Draft: tips', 'status' => 'ready_for_review',
            'metadata' => ['scheduled_at' => '2026-10-27T09:00:00+07:00'],
        ]);
        OfficeContentItem::create([
            'deduplication_key' => 'k2', 'agent_id' => 'jauki-social', 'platform' => 'instagram', 'content_type' => 'feed',
            'brand_id' => $this->jauki->id, 'schedule_id' => $schedule->id, 'title' => 'Sedang jalan', 'status' => 'generating',
            'metadata' => ['scheduled_at' => '2026-10-28T09:00:00+07:00'],
        ]);

        $this->actingAs($this->owner)->postJson('/office/api/content-results/clear')
            ->assertOk()->assertJsonPath('deleted', 2)->assertJsonPath('skipped', 1);

        $this->actingAs($this->owner)->getJson('/office/api/content-results')->assertJsonCount(1, 'results');
        $this->assertSame('generating', OfficeContentItem::where('deduplication_key', 'k2')->value('status'));
    }

    public function test_worker_research_shape_produces_topic_recommendations(): void
    {
        $run = OfficeResearchRun::create([
            'query' => 'Tips belajar', 'status' => 'completed', 'sources' => ['a'],
            'trends' => [['signal' => 'Jurnal refleksi', 'evidence_count' => 3]],
            'content_ideas' => ['feed' => ['Peta konsep'], 'story' => ['Kuis cepat']],
            'created_at' => now(),
        ]);

        $topics = array_column(app(OfficeAnalystReportService::class)->topicRecommendations('luar', $run), 'topic');

        $this->assertSame(['Jurnal refleksi', 'Peta konsep', 'Kuis cepat'], $topics);
    }

    public function test_research_query_comes_from_active_schedule_topics(): void
    {
        $this->schedule('Pomodoro');

        $report = app(OfficeAnalystReportService::class)->generate('manual', []);

        $run = OfficeResearchRun::where('metadata->analyst_report_id', $report->id)->sole();
        $this->assertSame('Pomodoro', $run->query);
    }

    public function test_charts_label_missing_topics_and_skip_hidden_content(): void
    {
        $this->schedule(null);
        $withTopic = $this->schedule('tips belajar');
        OfficeContentItem::create([
            'deduplication_key' => 'k1', 'agent_id' => 'jauki-social', 'platform' => 'instagram', 'content_type' => 'feed',
            'brand_id' => $this->jauki->id, 'schedule_id' => $withTopic->id, 'title' => 'x', 'status' => 'published',
            'metadata' => [], 'generated_at' => now('Asia/Jakarta'),
        ]);
        OfficeContentItem::create([
            'deduplication_key' => 'k2', 'agent_id' => 'jauki-social', 'platform' => 'instagram', 'content_type' => 'feed',
            'brand_id' => $this->jauki->id, 'schedule_id' => $withTopic->id, 'title' => 'y', 'status' => 'dismissed',
            'metadata' => [], 'generated_at' => now('Asia/Jakarta'),
        ]);

        $topics = collect($this->actingAs($this->owner)->getJson('/office/api/analyst-reports/charts')->json('data.topics'))->pluck('count', 'topic');

        $this->assertSame(1, $topics['tips belajar']);
        $this->assertFalse($topics->has('Threads post'));
        $this->assertSame(1, $topics->count(), 'konten dismissed tidak dihitung');
    }

    public function test_calendar_shows_weekly_cards_with_and_without_time_and_reels_slots_like_the_grid(): void
    {
        $this->schedule('tips belajar');
        $this->schedule('tanpa jam', ['recurrence_rule' => ['frequency' => 'weekly', 'days' => [1], 'time' => null]]);
        OfficeContentSchedule::create([
            'brand_id' => $this->jauki->id, 'name' => 'Sekali uji', 'schedule_type' => 'one_time', 'platform' => 'instagram',
            'content_type' => 'feed', 'timezone' => 'Asia/Jakarta', 'is_active' => true,
            'scheduled_at' => '2026-10-27 10:00:00', 'next_run_at' => '2026-10-27 10:00:00', 'generation_mode' => 'manual', 'publishing_mode' => 'automatic',
        ]);
        File::put($this->dir.'/slots.json', json_encode([
            ['id' => 'slot-1', 'label' => 'Reels Jauki', 'day' => 2, 'time' => '16:00', 'active' => true],
            ['id' => 'slot-2', 'label' => 'Reels tanpa jam', 'day' => 3, 'time' => null, 'active' => true],
        ]));

        $occurrences = collect($this->actingAs($this->owner)
            ->getJson('/office/api/content-planner?start=2026-10-26&end=2026-11-01')->assertOk()->json('occurrences'));

        $this->assertSame(['feed', 'reels'], $occurrences->pluck('contentType')->unique()->sort()->values()->all());
        $this->assertCount(4, $occurrences, 'dua mingguan (dengan dan tanpa jam) dan dua slot Reels; sekali jalan tidak tampil');
        $this->assertSame('2026-10-26T09:00:00+07:00', $occurrences->firstWhere('name', 'tips belajar')['scheduledAt']);
        $this->assertFalse($occurrences->firstWhere('name', 'tips belajar')['timeless']);
        $this->assertSame('2026-10-26T00:00:00+07:00', $occurrences->firstWhere('name', 'tanpa jam')['scheduledAt']);
        $this->assertTrue($occurrences->firstWhere('name', 'tanpa jam')['timeless']);
        $this->assertSame('2026-10-27T16:00:00+07:00', $occurrences->firstWhere('name', 'Reels Jauki')['scheduledAt']);
        $this->assertTrue($occurrences->firstWhere('name', 'Reels tanpa jam')['timeless']);
    }
}
