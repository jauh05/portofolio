<?php

namespace App\Services;

use App\Models\OfficeReport;

class OfficeReportService
{
    public function __construct(private OfficeMemoryService $memories) {}

    public function persistAnalystResult(array $result, ?string $commandId = null): ?OfficeReport
    {
        $result = $this->structuredOnly($result);
        $required = ['executive_summary', 'key_findings'];
        if (! array_filter($required, fn ($key) => filled($result[$key] ?? null))) return null;
        $brand = $result['brand'] ?? $result['brand_id'] ?? null;
        if ($brand !== null && ! in_array($brand, OfficeMemoryService::BRANDS, true)) return null;
        $type = $result['report_type'] ?? 'trend_research';
        $report = OfficeReport::create(['agent_id' => 'jauki-analyst', 'brand_id' => $brand, 'report_type' => $type,
            'title' => $result['title'] ?? $result['topic'] ?? 'Analyst research', 'summary' => $result['executive_summary'],
            'body_json' => $result, 'source_command_id' => $commandId]);
        foreach ((array) ($result['key_findings'] ?? []) as $finding) {
            $summary = is_string($finding) ? $finding : ($finding['summary'] ?? $finding['title'] ?? null);
            if (! $summary) continue;
            $this->memories->writeMemory(['brand_id' => $brand, 'source_agent' => 'jauki-analyst', 'memory_type' => 'insight', 'title' => is_array($finding) ? ($finding['title'] ?? 'Analyst finding') : 'Analyst finding', 'summary' => $summary, 'payload_json' => is_array($finding) ? $finding : null, 'confidence' => $result['confidence'] ?? null, 'tags_json' => ['analyst', $type]]);
        }
        foreach ((array) ($result['sources'] ?? []) as $source) {
            $url = is_array($source) ? ($source['url'] ?? null) : (filter_var($source, FILTER_VALIDATE_URL) ? $source : null);
            $reference = is_array($source) ? ($source['title'] ?? $source['reference'] ?? null) : ($url ? null : $source);
            if ($url || $reference) $this->memories->writeMemory(['brand_id' => $brand, 'source_agent' => 'jauki-analyst', 'memory_type' => 'research', 'title' => 'Research source', 'summary' => $reference ?? $url, 'source_url' => $url, 'source_reference' => $reference, 'confidence' => $result['confidence'] ?? null, 'importance' => 40, 'tags_json' => ['source', $type]]);
        }
        return $report;
    }

    /** Deliberately discard common hidden-reasoning fields from all persisted structures. */
    private function structuredOnly(array $value): array
    {
        $blocked = ['reasoning', 'chain_of_thought', 'chain-of-thought', 'internal_reasoning', 'analysis'];
        foreach ($value as $key => $item) {
            if (in_array(strtolower((string) $key), $blocked, true)) { unset($value[$key]); continue; }
            if (is_array($item)) $value[$key] = $this->structuredOnly($item);
        }
        return $value;
    }
}
