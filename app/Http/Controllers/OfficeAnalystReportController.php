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
        ]);
        return response()->json($this->payload($analyst->generate($data['report_type'], ['scope' => $data['scope'] ?? null, 'brand_id' => $data['brand_id'] ?? null])), 201);
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
        return ['id' => $report->id, 'reportDate' => $report->report_date?->toDateString(), 'reportType' => $report->report_type,
            'status' => $report->status, 'summary' => $report->summary, 'findings' => $report->findings ?? [], 'conclusion' => $report->conclusion,
            'recommendations' => $report->recommendations ?? [], 'nextActions' => $report->next_actions ?? [], 'metadata' => $report->metadata ?? [],
            'generatedAt' => $report->generated_at?->toIso8601String()];
    }
}
