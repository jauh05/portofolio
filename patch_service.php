<?php
$file = 'app/Services/OfficeAnalystReportService.php';
$content = file_get_contents($file);

// 1. Rewrite generate() to decouple it
$generateFunction = "    public function generate(string \$type = 'daily', array \$filters = []): OfficeAnalystReport
    {
        \$report = OfficeAnalystReport::create([
            'report_date' => now('Asia/Jakarta')->toDateString(),
            'report_type' => \$type,
            'status' => 'generating', // Awaiting research
            'summary' => 'Memulai riset eksternal...',
            'findings' => [], 'conclusion' => '', 'recommendations' => [], 'next_actions' => [],
            'metadata' => ['filters' => \$filters],
            'generated_at' => now('Asia/Jakarta'),
        ]);

        \$topic = \$filters['topic'] ?? 'General Trends';
        \$run = \App\Models\OfficeResearchRun::create([
            'command_id' => null, // populated below
            'brand_id' => \$filters['brand_id'] ?? null,
            'scope' => \$type,
            'query' => \$topic,
            'status' => 'queued',
            'started_at' => now(),
            'metadata' => ['analyst_report_id' => \$report->id],
        ]);

        \$command = OfficeCommand::create([
            'agent_id' => 'jauki-analyst',
            'action' => 'research_trends',
            'payload' => ['topic' => \$topic, 'language' => 'id', 'limit' => 5],
            'status' => 'queued',
        ]);

        \$run->update(['command_id' => \$command->id]);
        return \$report;
    }";
    
$content = preg_replace('/public function generate.*?return \$report;\n    }/s', $generateFunction, $content);

file_put_contents($file, $content);
