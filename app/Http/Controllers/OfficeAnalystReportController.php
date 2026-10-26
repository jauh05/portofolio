<?php

namespace App\Http\Controllers;

use App\Models\OfficeAnalystReport;
use App\Models\OfficeContentBrand;
use App\Models\OfficeContentItem;
use App\Models\OfficeContentMetric;
use App\Models\OfficeContentPlanItem;
use App\Models\OfficeContentSchedule;
use App\Models\OfficeResearchRun;
use App\Services\OfficeAnalystReportService;
use App\Services\OfficeContentMetricsService;
use App\Services\OfficeScheduledGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OfficeAnalystReportController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => OfficeAnalystReport::latest('generated_at')->limit(30)->get()->map(fn (OfficeAnalystReport $report) => $this->payload($report))]);
    }

    public function researchStats(): JsonResponse
    {
        $timezone = 'Asia/Jakarta';
        $today = now($timezone)->startOfDay();
        $days = collect(range(13, 0))->map(fn (int $ago) => $today->copy()->subDays($ago)->toDateString());
        $runs = OfficeResearchRun::query()->latest('created_at')->limit(500)
            ->get(['id', 'query', 'status', 'sources', 'findings', 'trends', 'content_ideas', 'created_at']);

        $count = fn (?array $value) => is_array($value) ? count($value) : 0;
        $byDay = $runs->groupBy(fn (OfficeResearchRun $run) => $run->created_at?->setTimezone($timezone)->toDateString());
        $series = $days->map(function (string $date) use ($byDay) {
            $items = $byDay->get($date, collect());
            return [
                'date' => $date,
                'total' => $items->count(),
                'completed' => $items->where('status', 'completed')->count(),
                'running' => $items->whereIn('status', ['queued', 'running'])->count(),
                'failed' => $items->where('status', 'failed')->count(),
            ];
        })->values();

        $totals = [
            'runs' => $runs->count(),
            'completed' => $runs->where('status', 'completed')->count(),
            'running' => $runs->whereIn('status', ['queued', 'running'])->count(),
            'failed' => $runs->where('status', 'failed')->count(),
            'sources' => $runs->sum(fn (OfficeResearchRun $run) => $count($run->sources)),
            'findings' => $runs->sum(fn (OfficeResearchRun $run) => $count($run->findings)),
            'trends' => $runs->sum(fn (OfficeResearchRun $run) => $count($run->trends)),
            'contentIdeas' => $runs->sum(fn (OfficeResearchRun $run) => $count($run->content_ideas)),
        ];

        $topics = $runs->groupBy(fn (OfficeResearchRun $run) => $run->query ?: 'General Trends')
            ->map(fn ($items, $topic) => ['topic' => $topic, 'runs' => $items->count(), 'sources' => $items->sum(fn (OfficeResearchRun $run) => $count($run->sources))])
            ->sortByDesc('sources')->take(5)->values();

        $recent = $runs->take(6)->map(fn (OfficeResearchRun $run) => [
            'id' => $run->id,
            'query' => $run->query ?: 'General Trends',
            'status' => $run->status,
            'sources' => $count($run->sources),
            'findings' => $count($run->findings),
            'createdAt' => $run->created_at?->toIso8601String(),
        ])->values();

        return response()->json(['data' => [
            'generatedAt' => now($timezone)->toIso8601String(),
            'totals' => $totals,
            'series' => $series,
            'topics' => $topics,
            'recent' => $recent,
        ]]);
    }

    public function show(OfficeAnalystReport $report): JsonResponse
    {
        return response()->json($this->payload($report));
    }

    public function generate(Request $request, OfficeAnalystReportService $analyst): JsonResponse
    {
        $data = $request->validate([
            'report_type' => ['required', Rule::in(['daily', 'weekly', 'manual'])],
            'scope' => ['nullable', Rule::in(['today', 'last_7_days', 'content_planner', 'worker_performance', 'brand', 'luar', 'dalam', 'keduanya'])],
            'brand_id' => ['nullable', 'uuid', 'exists:office_content_brands,id'],
            'planning_horizon' => ['nullable', Rule::in(['only_analysis', '7_days', '14_days', '30_days'])],
        ]);
        return response()->json($this->payload($analyst->generate($data['report_type'], [
            'scope' => $data['scope'] ?? null,
            'brand_id' => $data['brand_id'] ?? null,
            'planning_horizon' => $data['planning_horizon'] ?? '7_days',
        ])), 201);
    }

    public function approve(Request $request, OfficeAnalystReport $report, OfficeAnalystReportService $analyst): JsonResponse
    {
        $ids = $request->validate(['plan_ids' => ['required', 'array', 'min:1'], 'plan_ids.*' => ['uuid']])['plan_ids'];
        return response()->json($this->payload($analyst->approve($report, $ids)));
    }

    public function dismiss(Request $request, OfficeAnalystReport $report, OfficeAnalystReportService $analyst): JsonResponse
    {
        $ids = $request->validate(['plan_ids' => ['required', 'array', 'min:1'], 'plan_ids.*' => ['uuid']])['plan_ids'];
        return response()->json($this->payload($analyst->dismiss($report, $ids)));
    }

    /** Data kartu Chart di Daily Report: semua topik yang pernah dibuat, konten per hari, views dan likes per topik, dan sosial per brand. */
    public function charts(): JsonResponse
    {
        $timezone = 'Asia/Jakarta';
        $today = now($timezone)->startOfDay();
        $days = collect(range(13, 0))->map(fn (int $ago) => $today->copy()->subDays($ago)->toDateString());

        $items = OfficeContentItem::with(['brand', 'schedule'])->latest('generated_at')->limit(2000)->get();
        $topics = $items
            ->map(fn (OfficeContentItem $item) => OfficeContentMetricsService::topicOf($item->schedule?->topic, $item->title))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->map(fn (int $count, string $topic) => ['topic' => $topic, 'count' => $count])
            ->values();

        $byDay = $items->groupBy(fn (OfficeContentItem $item) => $item->generated_at?->copy()->setTimezone($timezone)->toDateString());
        $contentPerDay = $days->map(fn (string $date) => ['date' => $date, 'total' => $byDay->get($date, collect())->count()])->values();

        $metricsByDay = OfficeContentMetric::query()->whereNotNull('published_at')->get()
            ->groupBy(fn (OfficeContentMetric $row) => $row->published_at?->copy()->setTimezone($timezone)->toDateString());
        $social = collect(['kauiz', 'jauki'])->mapWithKeys(fn (string $slug) => [$slug => $days->map(function (string $date) use ($metricsByDay, $slug) {
            $rows = $metricsByDay->get($date, collect())->where('brand_slug', $slug);

            return ['date' => $date, 'views' => (int) $rows->sum('views'), 'likes' => (int) $rows->sum('likes')];
        })->values()]);

        $metricTopics = OfficeContentMetricsService::topicTotals();

        return response()->json(['data' => [
            'generatedAt' => now($timezone)->toIso8601String(),
            'metricsConfigured' => app(OfficeContentMetricsService::class)->configured(),
            'lastFetchedAt' => OfficeContentMetric::query()->max('fetched_at'),
            'topics' => $topics,
            'topTopic' => $metricTopics->first() ?? $topics->first(),
            'contentPerDay' => $contentPerDay,
            'topicMetrics' => $metricTopics->take(12)->values(),
            'social' => $social,
        ]]);
    }

    /**
     * Tombol generate per format di Daily Report. Feed, Story, dan Artikel memakai generator yang sudah ada.
     * Reels dan TikTok dibuat sebagai rencana yang menunggu persetujuan, karena render dan skrip-nya belum punya jalur sendiri.
     */
    public function generateFormat(Request $request, OfficeScheduledGenerationService $generation): JsonResponse
    {
        $data = $request->validate([
            'brand_slug' => ['required', Rule::in(['jauki', 'kauiz'])],
            'format' => ['required', Rule::in(['feed', 'story', 'reels', 'article', 'tiktok'])],
            'topic' => ['required', 'string', 'max:255'],
        ]);
        $brand = OfficeContentBrand::where('slug', $data['brand_slug'])->firstOrFail();
        $topic = trim($data['topic']);

        if (in_array($data['format'], ['feed', 'story', 'article'], true)) {
            $when = now('Asia/Jakarta')->addHour();
            $schedule = OfficeContentSchedule::create([
                'brand_id' => $brand->id,
                'name' => 'Daily Report: '.$topic,
                'schedule_type' => 'one_time',
                'platform' => $data['format'] === 'article' ? 'article' : 'instagram',
                'content_type' => $data['format'],
                'topic' => $topic,
                'timezone' => 'Asia/Jakarta',
                'scheduled_at' => $when,
                'next_run_at' => $when,
                'is_active' => true,
                'generation_mode' => 'manual',
                'generation_timing' => 'manual',
                'publishing_mode' => 'review',
                'metadata' => ['source' => 'daily_report'],
            ]);
            $result = $generation->generateSchedule($schedule, null, true);

            return response()->json(['status' => $result['status'], 'reason' => $result['reason'] ?? null, 'contentId' => $result['content_id'] ?? null], 201);
        }

        $plan = OfficeContentPlanItem::create([
            'brand_id' => $brand->id,
            'platform' => $data['format'] === 'tiktok' ? 'tiktok' : 'instagram',
            'content_type' => $data['format'],
            'topic' => $topic,
            'brief' => 'Dibuat dari Daily Report: '.$topic,
            'reason' => 'Dibuat manual dari Daily Report.',
            'scheduled_at' => now('Asia/Jakarta')->addDay(),
            'generation_mode' => 'manual',
            'publishing_mode' => 'review',
            'status' => 'proposed',
            'metadata' => ['source' => 'daily_report', 'overlay' => $topic],
        ]);

        return response()->json(['status' => 'proposed', 'planId' => $plan->id], 201);
    }

    private function payload(OfficeAnalystReport $report): array
    {
        $researchRunId = $report->metadata['research_run_id'] ?? null;
        if ($researchRunId) {
            $planItems = \App\Models\OfficeContentPlanItem::with('brand')->where('research_run_id', $researchRunId)->orderBy('scheduled_at')->get();
        } else {
            $planItems = \App\Models\OfficeContentPlanItem::with('brand')->where('metadata->analyst_report_id', $report->id)->orderBy('scheduled_at')->get();
        }

        return ['id' => $report->id, 'reportDate' => $report->report_date?->toDateString(), 'reportType' => $report->report_type,
            'status' => $report->status, 'summary' => $report->summary, 'findings' => $report->findings ?? [], 'conclusion' => $report->conclusion,
            'recommendations' => $report->recommendations ?? [], 'nextActions' => $report->next_actions ?? [], 'contentPlans' => $planItems->map(fn ($plan) => [
                'id' => $plan->id, 'status' => $plan->status, 'brand' => $plan->brand ? ['id' => $plan->brand->id, 'name' => $plan->brand->name, 'slug' => $plan->brand->slug] : null,
                'platform' => $plan->platform, 'contentType' => $plan->content_type, 'topic' => $plan->topic, 'brief' => $plan->brief, 'reason' => $plan->reason,
                'scheduledAt' => $plan->scheduled_at?->toIso8601String(), 'generationMode' => $plan->generation_mode, 'publishingMode' => $plan->publishing_mode,
                'scheduleId' => $plan->schedule_id, 'metadata' => $plan->metadata ?? [],
            ])->values(), 'metadata' => $report->metadata ?? [],
            'generatedAt' => $report->generated_at?->toIso8601String()];
    }
}
