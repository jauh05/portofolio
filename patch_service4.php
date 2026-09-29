<?php
$file = 'app/Services/OfficeAnalystReportService.php';
$content = file_get_contents($file);

$oldApprove = "    public function approve(OfficeAnalystReport \$report, array \$actionIds): OfficeAnalystReport
    {
        return DB::transaction(function () use (\$report, \$actionIds) {
            \$report = OfficeAnalystReport::whereKey(\$report->id)->lockForUpdate()->firstOrFail();
            \$actions = collect(\$report->next_actions ?? [])->map(function (array \$action) use (\$actionIds, \$report) {
                if (! in_array(\$action['id'] ?? null, \$actionIds, true) || (\$action['status'] ?? 'proposed') !== 'proposed') return \$action;
                if ((\$action['action_type'] ?? null) === 'content_schedule') \$this->createSchedule(\$action, \$report);
                if ((\$action['action_type'] ?? null) === 'task') \$this->createTask(\$action, \$report);
                return array_merge(\$action, ['status' => 'approved', 'approved_at' => now('Asia/Jakarta')->toIso8601String()]);
            })->all();
            \$report->update(['next_actions' => \$actions, 'status' => \$this->allResolved(\$actions) ? 'reviewed' : 'ready']);
            return \$report->fresh();
        });
    }";

$newApprove = "    public function approve(OfficeAnalystReport \$report, array \$planIds): OfficeAnalystReport
    {
        return DB::transaction(function () use (\$report, \$planIds) {
            \$report = OfficeAnalystReport::whereKey(\$report->id)->lockForUpdate()->firstOrFail();
            \$plans = \App\Models\OfficeContentPlanItem::where('research_run_id', \$report->metadata['research_run_id'] ?? '')
                        ->whereIn('id', \$planIds)
                        ->where('status', 'proposed')
                        ->get();
            
            foreach (\$plans as \$plan) {
                \$schedule = \App\Models\OfficeContentSchedule::create([
                    'brand_id' => \$plan->brand_id,
                    'name' => 'Plan: ' . \$plan->topic,
                    'schedule_type' => 'one_time',
                    'platform' => \$plan->platform,
                    'content_type' => \$plan->content_type,
                    'topic' => \$plan->topic,
                    'brief' => \$plan->brief,
                    'scheduled_at' => \$plan->scheduled_at,
                    'timezone' => 'Asia/Jakarta',
                    'generation_mode' => \$plan->generation_mode,
                    'publishing_mode' => \$plan->publishing_mode,
                    'metadata' => ['plan_item_id' => \$plan->id],
                ]);
                \$plan->update(['status' => 'approved', 'schedule_id' => \$schedule->id]);
                
                if (\$plan->generation_mode === 'automatic') {
                    // Trigger draft and generator immediately
                    \$generator = app(\App\Services\OfficeContentGenerator::class);
                    \$draft = \$generator->ensureDraft(\$schedule);
                    \$plan->update(['content_item_id' => \$draft->id]);
                    app(\App\Http\Controllers\OfficeContentPlannerController::class)->dispatchGeneration(\$draft);
                }
            }
            
            \$unresolved = \App\Models\OfficeContentPlanItem::where('research_run_id', \$report->metadata['research_run_id'] ?? '')
                            ->where('status', 'proposed')->exists();
            if (!\$unresolved) {
                \$report->update(['status' => 'reviewed']);
            }
            return \$report->fresh();
        });
    }";
    
$content = str_replace($oldApprove, $newApprove, $content);

$oldDismiss = "    public function dismiss(OfficeAnalystReport \$report, array \$actionIds): OfficeAnalystReport
    {
        return DB::transaction(function () use (\$report, \$actionIds) {
            \$report = OfficeAnalystReport::whereKey(\$report->id)->lockForUpdate()->firstOrFail();
            \$actions = collect(\$report->next_actions ?? [])->map(fn (array \$action) => in_array(\$action['id'] ?? null, \$actionIds, true) && (\$action['status'] ?? 'proposed') === 'proposed' ? array_merge(\$action, ['status' => 'dismissed', 'dismissed_at' => now('Asia/Jakarta')->toIso8601String()]) : \$action)->all();
            \$report->update(['next_actions' => \$actions, 'status' => \$this->allResolved(\$actions) ? 'reviewed' : 'ready']);
            return \$report->fresh();
        });
    }";

$newDismiss = "    public function dismiss(OfficeAnalystReport \$report, array \$planIds): OfficeAnalystReport
    {
        return DB::transaction(function () use (\$report, \$planIds) {
            \$report = OfficeAnalystReport::whereKey(\$report->id)->lockForUpdate()->firstOrFail();
            \App\Models\OfficeContentPlanItem::where('research_run_id', \$report->metadata['research_run_id'] ?? '')
                        ->whereIn('id', \$planIds)
                        ->where('status', 'proposed')
                        ->update(['status' => 'dismissed']);
            
            \$unresolved = \App\Models\OfficeContentPlanItem::where('research_run_id', \$report->metadata['research_run_id'] ?? '')
                            ->where('status', 'proposed')->exists();
            if (!\$unresolved) {
                \$report->update(['status' => 'reviewed']);
            }
            return \$report->fresh();
        });
    }";

$content = str_replace($oldDismiss, $newDismiss, $content);
file_put_contents($file, $content);
