<?php
$file = 'app/Services/OfficeEventService.php';
$content = file_get_contents($file);

$oldCode = "            \$content = \$this->updateContent(\$data);
            \$this->notify(\$data, \$task, \$content);
        });";

$newCode = "            \$content = \$this->updateContent(\$data);
            \$this->handleResearch(\$data, \$task);
            \$this->notify(\$data, \$task, \$content);
        });";
$content = str_replace($oldCode, $newCode, $content);

$methodCode = "
    private function handleResearch(array \$data, ?OfficeTask \$task): void
    {
        if (\$data['event'] === 'research.completed') {
            if (\$run = \App\Models\OfficeResearchRun::where('command_id', \$data['task_id'] ?? \$data['command_id'] ?? '')->first()) {
                \$result = \$data['result'] ?? [];
                \$run->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'sources' => \$result['sources'] ?? null,
                    'findings' => \$result['summary'] ? ['summary' => \$result['summary']] : null,
                    'trends' => \$result['trend_signals'] ?? null,
                    'content_ideas' => \$result['content_ideas'] ?? null,
                    'metadata' => ['raw_result' => \$result],
                ]);
                
                // Immediately consume it to generate the Analyst Report and Plan
                app(\App\Services\OfficeAnalystReportService::class)->generatePlanFromResearch(\$run);
            }
        }
    }
";

$content = str_replace(
    "    private function updateTask(array \$data): ?OfficeTask",
    $methodCode . "\n    private function updateTask(array \$data): ?OfficeTask",
    $content
);

file_put_contents($file, $content);
