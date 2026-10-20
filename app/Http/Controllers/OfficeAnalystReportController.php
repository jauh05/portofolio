<?php

namespace App\Http\Controllers;

use App\Models\OfficeAnalystReport;
use App\Models\OfficeResearchRun;
use App\Services\OfficeAnalystReportService;
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
            'scope' => ['nullable', Rule::in(['today', 'last_7_days', 'content_planner', 'worker_performance', 'brand'])],
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
        $data = $request->validate([
            'plan_ids' => ['required', 'array', 'min:1'], 'plan_ids.*' => ['uuid'],
            'timings' => ['nullable', 'array'], 'timings.*' => ['in:now,H-1,H-2,manual'],
        ]);

        return response()->json($this->payload($analyst->approve($report, $data['plan_ids'], $data['timings'] ?? [])));
    }

    public function dismiss(Request $request, OfficeAnalystReport $report, OfficeAnalystReportService $analyst): JsonResponse
    {
        $ids = $request->validate(['plan_ids' => ['required', 'array', 'min:1'], 'plan_ids.*' => ['uuid']])['plan_ids'];
        return response()->json($this->payload($analyst->dismiss($report, $ids)));
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
