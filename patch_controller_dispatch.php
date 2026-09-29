<?php
$file = 'app/Http/Controllers/OfficeContentPlannerController.php';
$content = file_get_contents($file);

if (!str_contains($content, 'public function dispatchGeneration')) {
    $content = str_replace(
        "public function generateContent(OfficeContentItem \$content)",
        "public function dispatchGeneration(OfficeContentItem \$content)
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

        \$researchId = \$schedule->metadata['plan_item_id'] ?? null;
        \$evidence = '';
        if (\$researchId) {
            \$plan = \App\Models\OfficeContentPlanItem::find(\$researchId);
            if (\$plan && \$plan->research_run_id) {
                \$run = \App\Models\OfficeResearchRun::find(\$plan->research_run_id);
                if (\$run) {
                    \$evidence = \"\\nResearch evidence:\\n\";
                    if (\$run->findings) \$evidence .= json_encode(\$run->findings) . \"\\n\";
                    if (\$run->sources) \$evidence .= json_encode(\$run->sources) . \"\\n\";
                }
            }
        }

        \$generationPrompt = sprintf(
            \"Brand: %s\\nPlatform: %s\\nFormat: %s\\nTopik: %s\\nBahasa: Indonesia\\n%s\\nCreate:\\n- hook\\n- slide/content structure\\n- caption\\n- CTA\\n- hashtags\\n- visual brief\",
            \$brand?->name ?? 'None',
            \$content->platform,
            ucfirst(\$content->content_type ?? 'feed'),
            \$schedule?->topic ?? \$content->title,
            \$evidence
        );

        \App\Models\OfficeCommand::create([
            'agent_id' => \$agent,
            'action' => \$action,
            'payload' => [
                'content_id' => \$content->id,
                'prompt' => \$generationPrompt,
                'brand' => \$brand?->name,
                'platform' => \$content->platform,
                'content_type' => \$content->content_type,
                'topic' => \$schedule?->topic,
                'brief' => \$schedule?->brief,
                'language' => 'id',
                'scheduled_at' => \$content->metadata['scheduled_at'] ?? null,
            ],
            'status' => 'queued',
            'requested_by' => auth()->id() ?? \App\Models\User::where('is_office_owner', true)->first()?->id,
        ]);
    }

    public function generateContent(OfficeContentItem \$content)",
        $content
    );
}

file_put_contents($file, $content);
