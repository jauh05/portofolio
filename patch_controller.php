<?php
$file = 'app/Http/Controllers/OfficeContentPlannerController.php';
$content = file_get_contents($file);

$oldGenerate = "    public function generateContent(OfficeContentItem \$content, OfficeContentGenerator \$generator)
    {
        \$this->authorizeOwner();
        \$content->update(['status' => 'generating']);
        try {
            \$generator->generateContent(\$content);
        } catch (\Throwable \$e) {
            \$content->update(['status' => 'failed']);
            throw \$e;
        }
        return response()->json(\$content->fresh());
    }";

$newGenerate = "    public function generateContent(OfficeContentItem \$content)
    {
        \$this->authorizeOwner();
        \$this->dispatchGeneration(\$content);
        return response()->json(\$content->fresh());
    }

    public function dispatchGeneration(OfficeContentItem \$content)
    {
        \$content->update(['status' => 'generating']);
        \$schedule = \$content->schedule;
        \$brand = \$content->brand;
        
        \$action = 'generate_' . (\$content->content_type ?? 'feed');
        \$agent = match(\$content->platform) {
            'instagram' => 'jauki-social',
            'threads' => 'jauki-threads',
            'article', 'blog' => 'jauki-article',
            default => 'jauki-social'
        };

        // Include research findings if applicable
        \$researchId = \$schedule->metadata['plan_item_id'] ?? null;
        \$researchFindings = null;
        if (\$researchId) {
            \$plan = \App\Models\OfficeContentPlanItem::find(\$researchId);
            if (\$plan && \$plan->research_run_id) {
                \$run = \App\Models\OfficeResearchRun::find(\$plan->research_run_id);
                \$researchFindings = \$run ? ['findings' => \$run->findings, 'sources' => \$run->sources] : null;
            }
        }

        \App\Models\OfficeCommand::create([
            'agent_id' => \$agent,
            'action' => \$action,
            'payload' => [
                'content_id' => \$content->id,
                'brand' => \$brand?->name,
                'platform' => \$content->platform,
                'content_type' => \$content->content_type,
                'topic' => \$schedule?->topic,
                'brief' => \$schedule?->brief,
                'language' => 'id',
                'research' => \$researchFindings,
                'scheduled_at' => \$content->metadata['scheduled_at'] ?? null,
            ],
            'status' => 'queued',
        ]);
    }";

$content = str_replace($oldGenerate, $newGenerate, $content);
file_put_contents($file, $content);
