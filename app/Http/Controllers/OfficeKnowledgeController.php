<?php

namespace App\Http\Controllers;

use App\Models\OfficeMemory;
use App\Models\OfficeReport;
use App\Services\OfficeMemoryService;
use App\Services\OfficeReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OfficeKnowledgeController extends Controller
{
    public function memories(Request $request, OfficeMemoryService $service): JsonResponse
    {
        $data = $request->validate(['brand' => ['nullable', Rule::in(OfficeMemoryService::BRANDS)], 'agent' => ['nullable', 'string', 'max:100'], 'type' => ['nullable', 'string', 'max:50'], 'limit' => ['nullable', 'integer', 'between:1,100']]);
        $query = OfficeMemory::query()->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
        if (array_key_exists('brand', $data)) $query->where(fn ($q) => $data['brand'] === 'global' ? $q->whereNull('brand_id')->orWhere('brand_id', 'global') : $q->where('brand_id', $data['brand']));
        if (! empty($data['agent'])) $query->where('source_agent', $data['agent']);
        if (! empty($data['type'])) $query->where('memory_type', $data['type']);
        return response()->json(['data' => $query->orderByDesc('importance')->latest()->limit($data['limit'] ?? 50)->get()->map(fn (OfficeMemory $m) => $this->serializeMemory($m))]);
    }

    public function memory(OfficeMemory $memory): JsonResponse { return response()->json($this->serializeMemory($memory, true)); }
    public function context(Request $request, OfficeMemoryService $service): JsonResponse { $data = $request->validate(['brand' => ['nullable', Rule::in(OfficeMemoryService::BRANDS)], 'limit' => ['nullable', 'integer', 'between:1,25']]); return response()->json($service->getAgentContext($data['brand'] ?? 'global', $data['limit'] ?? 8)); }
    public function reports(Request $request): JsonResponse { $data = $request->validate(['brand' => ['nullable', Rule::in(OfficeMemoryService::BRANDS)], 'agent' => ['nullable', 'string', 'max:100'], 'type' => ['nullable', 'string', 'max:100']]); $q = OfficeReport::latest(); if (isset($data['brand'])) $q->where('brand_id', $data['brand']); if (! empty($data['agent'])) $q->where('agent_id', $data['agent']); if (! empty($data['type'])) $q->where('report_type', $data['type']); return response()->json(['data' => $q->limit(100)->get()->map(fn (OfficeReport $r) => $this->serializeReport($r))]); }
    public function report(OfficeReport $report): JsonResponse { return response()->json($this->serializeReport($report, true)); }

    /** Server-to-server endpoint; browser users only have read access. */
    public function createReport(Request $request, OfficeReportService $reports): JsonResponse
    {
        $token = (string) config('services.office_bridge.token');
        if ($token === '' || ! hash_equals('Bearer '.$token, (string) $request->header('Authorization'))) return response()->json(['message' => 'Unauthorized'], 401);
        $data = $request->validate(['agent_id' => ['required', 'in:jauki-analyst'], 'source_command_id' => ['nullable', 'uuid'], 'result' => ['required', 'array']]);
        $report = $reports->persistAnalystResult($data['result'], $data['source_command_id'] ?? null);
        return $report ? response()->json(['id' => $report->id], 201) : response()->json(['message' => 'A structured analyst result with executive_summary and key_findings is required.'], 422);
    }

    private function serializeMemory(OfficeMemory $m, bool $detail = false): array { return array_filter(['id' => $m->id, 'brandId' => $m->brand_id ?? 'global', 'sourceAgent' => $m->source_agent, 'type' => $m->memory_type, 'title' => $m->title, 'summary' => $m->summary, 'sourceUrl' => $m->source_url, 'sourceReference' => $m->source_reference, 'confidence' => $m->confidence, 'importance' => $m->importance, 'createdAt' => $m->created_at?->toIso8601String(), 'payload' => $detail ? $m->payload_json : null, 'tags' => $detail ? $m->tags_json : null], fn ($v) => $v !== null); }
    private function serializeReport(OfficeReport $r, bool $detail = false): array { return array_filter(['id' => $r->id, 'agentId' => $r->agent_id, 'brandId' => $r->brand_id ?? 'global', 'type' => $r->report_type, 'title' => $r->title, 'summary' => $r->summary, 'createdAt' => $r->created_at?->toIso8601String(), 'body' => $detail ? $r->body_json : null], fn ($v) => $v !== null); }
}
