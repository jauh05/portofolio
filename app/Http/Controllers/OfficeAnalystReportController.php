<?php

namespace App\Http\Controllers;

use App\Models\OfficeAnalystReport;
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
        $ids = $request->validate(['plan_ids' => ['required', 'array', 'min:1'], 'plan_ids.*' => ['uuid']])['plan_ids'];
        return response()->json($this->payload($analyst->approve($report, $ids)));
    }

    public function dismiss(Request $request, OfficeAnalystReport $report, OfficeAnalystReportService $analyst): JsonResponse
    {
        $ids = $request->validate(['plan_ids' => ['required', 'array', 'min:1'], 'plan_ids.*' => ['uuid']])['plan_ids'];
        return response()->json($this->payload($analyst->dismiss($report, $ids)));
    }

    private function payload(OfficeAnalystReport $report): array
    {
        $researchRunId = $report->metadata['research_run_id'] ?? null;
        $planItems = $researchRunId ? \App\Models\OfficeContentPlanItem::with('brand')->where('research_run_id', $researchRunId)->orderBy('scheduled_at')->get() : collect();

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
