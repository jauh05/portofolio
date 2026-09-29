<?php
$file = 'app/Services/OfficeAnalystReportService.php';
$content = file_get_contents($file);

$generatePlanFromResearch = "
    public function generatePlanFromResearch(\App\Models\OfficeResearchRun \$run): void
    {
        \$reportId = \$run->metadata['analyst_report_id'] ?? null;
        if (!\$reportId) return;
        \$report = OfficeAnalystReport::find(\$reportId);
        if (!\$report) return;

        \$type = \$report->report_type;
        \$filters = \$report->metadata['filters'] ?? [];
        [\$start, \$end] = \$this->range(\$type, \$filters);
        \$context = \$this->collect(\$start, \$end, \$filters);
        
        \$body = \$this->factualReport(\$context, \$start, \$end, \$type);
        \$analysisMode = 'fallback_internal';
        try {
            \$contextJson = json_encode(array_merge(\$this->aiContext(\$context, \$start, \$end, \$type), [
                'agent_reach_research' => [
                    'topic' => \$run->query,
                    'sources' => \$run->sources,
                    'findings' => \$run->findings,
                    'trends' => \$run->trends,
                    'content_ideas' => \$run->content_ideas,
                ]
            ]), JSON_THROW_ON_ERROR);
            \$body = \$this->normalizeAiReport(\$this->ai->completeJson(\$this->systemPrompt(), \$contextJson), \$context['brands']);
            \$analysisMode = 'ai';
        } catch (\Throwable \$e) {
            // An unavailable/malformed provider must not create fake AI output.
        }

        \$report->update([
            'status' => 'ready',
            'summary' => \$body['summary'],
            'findings' => \$body['findings'],
            'conclusion' => \$body['conclusion'],
            'recommendations' => \$body['recommendations'],
            'next_actions' => [], // Plan items handle actions now
            'metadata' => array_merge(\$report->metadata ?? [], [
                'source' => 'agent_reach', 
                'analysis_mode' => \$analysisMode, 
                'range_start' => \$start->toIso8601String(), 
                'range_end' => \$end->toIso8601String(), 
                'metrics' => \$context['metrics'], 
                'performance_available' => false,
                'research_run_id' => \$run->id
            ]),
            'generated_at' => now('Asia/Jakarta'),
        ]);

        // Create Content Plan Items from AI Output
        \$this->createPlanItems(\$body['content_plan'] ?? [], \$run, \$context['brands']);

        OfficeNotification::create([
            'deduplication_key' => 'analyst-report-'.\$report->id, 'type' => 'analyst.report_ready', 'agent_id' => 'jauki-analyst',
            'title' => ucfirst(\$type).' Analyst Brief is ready', 'message' => 'Review findings and approve any proposed next actions.', 'severity' => 'info', 'data' => ['report_id' => \$report->id], 'created_at' => now('Asia/Jakarta'),
        ]);
    }

    private function createPlanItems(array \$plans, \App\Models\OfficeResearchRun \$run, \$brands): void
    {
        foreach (\$plans as \$plan) {
            \$brand = \$brands->firstWhere('slug', strtolower((string) (\$plan['brand_slug'] ?? '')));
            if (!\$brand) continue;
            \App\Models\OfficeContentPlanItem::create([
                'research_run_id' => \$run->id,
                'brand_id' => \$brand->id,
                'platform' => \$plan['platform'] ?? 'instagram',
                'content_type' => \$plan['content_type'] ?? 'feed',
                'topic' => \$plan['topic'] ?? 'General',
                'brief' => \$plan['brief'] ?? '',
                'reason' => \$plan['reason'] ?? '',
                'scheduled_at' => \Carbon\CarbonImmutable::parse(\$plan['scheduled_at'] ?? now()->addDay(), 'Asia/Jakarta'),
                'generation_mode' => \$plan['generation_mode'] ?? 'manual',
                'publishing_mode' => \$plan['publishing_mode'] ?? 'review',
                'status' => 'proposed',
            ]);
        }
    }
";

$content = str_replace(
    "    public function approve(OfficeAnalystReport \$report, array \$actionIds): OfficeAnalystReport",
    $generatePlanFromResearch . "\n    public function approve(OfficeAnalystReport \$report, array \$actionIds): OfficeAnalystReport",
    $content
);

file_put_contents($file, $content);
