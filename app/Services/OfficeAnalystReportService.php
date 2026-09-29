<?php

namespace App\Services;

use App\Models\OfficeAnalystReport;
use App\Models\OfficeCommand;
use App\Models\OfficeContentBrand;
use App\Models\OfficeContentItem;
use App\Models\OfficeContentSchedule;
use App\Models\OfficeEvent;
use App\Models\OfficeNotification;
use App\Models\OfficeTask;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OfficeAnalystReportService
{
    public function __construct(private OfficeAiGateway $ai) {}

    public function generate(string $type = 'daily', array $filters = []): OfficeAnalystReport
    {
        [$start, $end] = $this->range($type, $filters);
        $context = $this->collect($start, $end, $filters);
        $body = $this->factualReport($context, $start, $end, $type);
        $analysisMode = 'fallback_internal';
        try {
            $contextJson = json_encode($this->aiContext($context, $start, $end, $type), JSON_THROW_ON_ERROR);
            $body = $this->normalizeAiReport($this->ai->completeJson($this->systemPrompt(), $contextJson), $context['brands']);
            $analysisMode = 'ai';
        } catch (\Throwable) {
            // An unavailable/malformed provider must not create fake AI output.
        }

        $report = OfficeAnalystReport::create([
            'report_date' => $end->toDateString(), 'report_type' => $type, 'status' => 'ready',
            'summary' => $body['summary'], 'findings' => $body['findings'], 'conclusion' => $body['conclusion'],
            'recommendations' => $body['recommendations'], 'next_actions' => $body['next_actions'],
            'metadata' => ['source' => 'internal_office_data', 'analysis_mode' => $analysisMode, 'range_start' => $start->toIso8601String(), 'range_end' => $end->toIso8601String(), 'metrics' => $context['metrics'], 'performance_available' => false, 'filters' => $filters],
            'generated_at' => now('Asia/Jakarta'),
        ]);
        OfficeNotification::create([
            'deduplication_key' => 'analyst-report-'.$report->id, 'type' => 'analyst.report_ready', 'agent_id' => 'jauki-analyst',
            'title' => ucfirst($type).' Analyst Brief is ready', 'message' => 'Review findings and approve any proposed next actions.', 'severity' => 'info', 'data' => ['report_id' => $report->id], 'created_at' => now('Asia/Jakarta'),
        ]);
        return $report;
    }

    public function approve(OfficeAnalystReport $report, array $actionIds): OfficeAnalystReport
    {
        return DB::transaction(function () use ($report, $actionIds) {
            $report = OfficeAnalystReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            $actions = collect($report->next_actions ?? [])->map(function (array $action) use ($actionIds, $report) {
                if (! in_array($action['id'] ?? null, $actionIds, true) || ($action['status'] ?? 'proposed') !== 'proposed') return $action;
                if (($action['action_type'] ?? null) === 'content_schedule') $this->createSchedule($action, $report);
                if (($action['action_type'] ?? null) === 'task') $this->createTask($action, $report);
                return array_merge($action, ['status' => 'approved', 'approved_at' => now('Asia/Jakarta')->toIso8601String()]);
            })->all();
            $report->update(['next_actions' => $actions, 'status' => $this->allResolved($actions) ? 'reviewed' : 'ready']);
            return $report->fresh();
        });
    }

    public function dismiss(OfficeAnalystReport $report, array $actionIds): OfficeAnalystReport
    {
        return DB::transaction(function () use ($report, $actionIds) {
            $report = OfficeAnalystReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            $actions = collect($report->next_actions ?? [])->map(fn (array $action) => in_array($action['id'] ?? null, $actionIds, true) && ($action['status'] ?? 'proposed') === 'proposed' ? array_merge($action, ['status' => 'dismissed', 'dismissed_at' => now('Asia/Jakarta')->toIso8601String()]) : $action)->all();
            $report->update(['next_actions' => $actions, 'status' => $this->allResolved($actions) ? 'reviewed' : 'ready']);
            return $report->fresh();
        });
    }

    private function collect(CarbonImmutable $start, CarbonImmutable $end, array $filters): array
    {
        $brandId = $filters['brand_id'] ?? null;
        $tasks = OfficeTask::where(fn ($q) => $q->whereBetween('created_at', [$start, $end])->orWhereBetween('started_at', [$start, $end]))->get();
        $events = OfficeEvent::whereBetween('created_at', [$start, $end])->get();
        $commands = OfficeCommand::whereBetween('created_at', [$start, $end])->get();
        $notifications = OfficeNotification::whereBetween('created_at', [$start, $end])->get();
        $schedules = OfficeContentSchedule::with('brand')->where('is_active', true)->when($brandId, fn ($q) => $q->where('brand_id', $brandId))->get();
        $content = OfficeContentItem::with('brand')->whereBetween('created_at', [$start, $end])->when($brandId, fn ($q) => $q->where('brand_id', $brandId))->get();
        $brands = OfficeContentBrand::where('is_active', true)->when($brandId, fn ($q) => $q->whereKey($brandId))->get();
        $metrics = ['tasks_total' => $tasks->count(), 'tasks_completed' => $tasks->where('status', 'completed')->count(), 'tasks_failed' => $tasks->where('status', 'failed')->count(), 'events_total' => $events->count(), 'commands_total' => $commands->count(), 'commands_failed' => $commands->where('status', 'failed')->count(), 'notifications_total' => $notifications->count(), 'content_total' => $content->count(), 'content_failed' => $content->where('status', 'failed')->count(), 'active_schedules' => $schedules->count()];
        return compact('tasks', 'events', 'commands', 'notifications', 'schedules', 'content', 'brands', 'metrics');
    }

    private function systemPrompt(): string
    {
        return 'You are an internal Living Office analyst. Return JSON only with this schema: {"summary":"","findings":[{"type":"issue|attention|opportunity|status","title":"","description":"","evidence":""}],"conclusion":"","recommendations":[{"priority":"high|medium|low","action":"","reason":""}],"next_actions":[{"action_type":"task|content_schedule","description":"","reason":"","brand_slug":"","schedule":{}}]}. Use only supplied internal Office context. Never invent metrics, performance, external facts, tool results, actions or outcomes. performance_available is false: include exactly "No performance data available yet." in summary. Actions are proposals only. A content_schedule must be complete, manual and review-only; otherwise propose a task.';
    }

    private function aiContext(array $context, CarbonImmutable $start, CarbonImmutable $end, string $type): array
    {
        return [
            'report_type' => $type, 'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'timezone' => 'Asia/Jakarta'], 'performance_available' => false, 'metrics' => $context['metrics'],
            'tasks' => $context['tasks']->take(30)->map(fn (OfficeTask $task) => ['title' => $task->title, 'type' => $task->type, 'status' => $task->status, 'agent_id' => $task->agent_id, 'progress' => $task->progress])->values(),
            'events' => $context['events']->take(30)->map(fn (OfficeEvent $event) => ['event_type' => $event->event_type, 'agent_id' => $event->agent_id, 'activity' => $event->activity, 'status' => $event->status, 'progress' => $event->progress])->values(),
            'commands' => $context['commands']->take(30)->map(fn (OfficeCommand $command) => ['agent_id' => $command->agent_id, 'action' => $command->action, 'status' => $command->status, 'error' => $command->error])->values(),
            'content_schedules' => $context['schedules']->take(30)->map(fn (OfficeContentSchedule $schedule) => ['brand_slug' => $schedule->brand?->slug, 'platform' => $schedule->platform, 'content_type' => $schedule->content_type, 'topic' => $schedule->topic, 'schedule_type' => $schedule->schedule_type])->values(),
            'content_items' => $context['content']->take(30)->map(fn (OfficeContentItem $item) => ['brand_slug' => $item->brand?->slug, 'platform' => $item->platform, 'content_type' => $item->content_type, 'status' => $item->status, 'title' => $item->title])->values(),
            'brands' => $context['brands']->map(fn (OfficeContentBrand $brand) => ['name' => $brand->name, 'slug' => $brand->slug, 'primary_platform' => $brand->primary_platform])->values(),
            'analyst_capabilities' => OfficeRegistry::COMMANDS['jauki-analyst'],
            'completed_analyst_commands_are_evidence_only' => $context['commands']->where('agent_id', 'jauki-analyst')->where('status', 'completed')->map(fn (OfficeCommand $command) => ['action' => $command->action, 'completed_at' => $command->completed_at?->toIso8601String()])->values(),
        ];
    }

    private function normalizeAiReport(array $raw, Collection $brands): array
    {
        $summary = $this->text($raw['summary'] ?? null, 1600); $conclusion = $this->text($raw['conclusion'] ?? null, 1000);
        if (! $summary || ! $conclusion || ! str_contains($summary, 'No performance data available yet.')) throw new \UnexpectedValueException('Invalid analyst report.');
        $findings = collect($raw['findings'] ?? [])->filter(fn ($item) => is_array($item))->map(fn ($item) => $this->normalizeFinding($item))->filter()->take(12)->values()->all();
        $recommendations = collect($raw['recommendations'] ?? [])->filter(fn ($item) => is_array($item))->map(fn ($item) => $this->normalizeRecommendation($item))->filter()->take(12)->values()->all();
        $nextActions = collect($raw['next_actions'] ?? [])->filter(fn ($item) => is_array($item))->map(fn ($item) => $this->normalizeAction($item, $brands))->filter()->take(10)->values()->all();
        if ($findings === [] || $recommendations === []) throw new \UnexpectedValueException('Incomplete analyst report.');
        return compact('summary', 'findings', 'conclusion', 'recommendations', 'nextActions') + ['next_actions' => $nextActions];
    }

    private function normalizeFinding(array $item): ?array
    {
        $type = $item['type'] ?? null; $title = $this->text($item['title'] ?? null, 255); $description = $this->text($item['description'] ?? null, 1000); $evidence = $this->text($item['evidence'] ?? null, 1000);
        return in_array($type, ['issue', 'attention', 'opportunity', 'status'], true) && $title && $description && $evidence ? compact('type', 'title', 'description', 'evidence') : null;
    }

    private function normalizeRecommendation(array $item): ?array
    {
        $priority = $item['priority'] ?? null; $action = $this->text($item['action'] ?? null, 500); $reason = $this->text($item['reason'] ?? null, 1000);
        return in_array($priority, ['high', 'medium', 'low'], true) && $action && $reason ? compact('priority', 'action', 'reason') : null;
    }

    private function normalizeAction(array $item, Collection $brands): ?array
    {
        $type = $item['action_type'] ?? null; $description = $this->text($item['description'] ?? null, 500); $reason = $this->text($item['reason'] ?? null, 1000);
        if (! in_array($type, ['task', 'content_schedule'], true) || ! $description || ! $reason) return null;
        $brand = $brands->firstWhere('slug', strtolower((string) ($item['brand_slug'] ?? '')));
        $action = ['id' => (string) Str::uuid(), 'status' => 'proposed', 'action_type' => $type, 'description' => $description, 'reason' => $reason, 'agent_id' => 'jauki-analyst', 'brand_slug' => $brand?->slug];
        if ($type === 'content_schedule') { $schedule = $this->normalizeSchedule($item['schedule'] ?? null, $brand); if (! $schedule) return null; $action['schedule'] = $schedule; }
        return $action;
    }

    private function normalizeSchedule(mixed $raw, ?OfficeContentBrand $brand): ?array
    {
        if (! is_array($raw) || ! $brand) return null;
        $type = $raw['schedule_type'] ?? null; $platform = $this->text($raw['platform'] ?? null, 100); $contentType = $this->text($raw['content_type'] ?? null, 100);
        if (! in_array($type, ['one_time', 'recurring'], true) || ! $platform || ! $contentType) return null;
        $schedule = ['name' => $this->text($raw['name'] ?? null, 255) ?: ucfirst($contentType).' for '.$brand->name, 'brand_id' => $brand->id, 'schedule_type' => $type, 'platform' => strtolower($platform), 'content_type' => strtolower($contentType), 'topic' => $this->text($raw['topic'] ?? null, 255), 'brief' => $this->text($raw['brief'] ?? null, 2000), 'assigned_agent' => null, 'timezone' => 'Asia/Jakarta', 'generation_mode' => 'manual', 'publishing_mode' => 'review'];
        try {
            if ($type === 'one_time') {
                $scheduledAt = $this->text($raw['scheduled_at'] ?? null, 100);
                return $scheduledAt ? array_merge($schedule, ['scheduled_at' => CarbonImmutable::parse($scheduledAt, 'Asia/Jakarta')->toIso8601String()]) : null;
            }
            $frequency = $raw['frequency'] ?? null; $time = (string) ($raw['time'] ?? ''); $days = collect($raw['days'] ?? [])->filter(fn ($day) => is_int($day) && $day >= 1 && $day <= 7)->values()->all();
            $startsAt = $this->text($raw['starts_at'] ?? null, 100);
            if (! in_array($frequency, ['daily', 'weekly', 'monthly'], true) || ! preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $time) || ! $startsAt || ($frequency === 'weekly' && $days === [])) return null;
            return array_merge($schedule, ['frequency' => $frequency, 'days' => $days, 'time' => $time, 'starts_at' => CarbonImmutable::parse($startsAt, 'Asia/Jakarta')->toDateString()]);
        } catch (\Throwable) { return null; }
    }

    private function factualReport(array $context, CarbonImmutable $start, CarbonImmutable $end, string $type): array
    {
        $metrics = $context['metrics']; $findings = [];
        if ($metrics['tasks_failed'] > 0) $findings[] = $this->finding('issue', $metrics['tasks_failed'].' task failure(s) recorded', 'Failed tasks were recorded in the selected period.', 'office_tasks.status = failed');
        if ($metrics['content_failed'] > 0) $findings[] = $this->finding('issue', $metrics['content_failed'].' content failure(s) recorded', 'Content items marked failed need review.', 'office_content_items.status = failed');
        $withoutTopic = $context['schedules']->filter(fn ($schedule) => blank($schedule->topic))->count();
        if ($withoutTopic > 0) $findings[] = $this->finding('attention', $withoutTopic.' schedule(s) need topic detail', 'Active schedules without a topic are harder to review.', 'office_content_schedules.topic is null');
        foreach ($context['brands'] as $brand) if ($context['schedules']->where('brand_id', $brand->id)->isEmpty()) $findings[] = $this->finding('opportunity', $brand->name.' has no active schedule', 'No active content schedule is assigned to this workspace.', 'office_content_schedules brand count = 0');
        if ($findings === []) $findings[] = $this->finding('status', 'No anomalies found in available Office data', 'No task/content failure or incomplete schedule was found in this period.', 'Internal Office records only');
        $recommendations = collect($findings)->filter(fn ($finding) => $finding['type'] !== 'status')->map(fn ($finding) => ['priority' => $finding['type'] === 'issue' ? 'high' : 'medium', 'action' => $finding['title'], 'reason' => $finding['description']])->values()->all();
        if ($recommendations === []) $recommendations[] = ['priority' => 'low', 'action' => 'Continue monitoring the content plan.', 'reason' => 'No issue is recorded in available Office data.'];
        $summary = "{$type} analysis for {$start->toDateString()} to {$end->toDateString()}: {$metrics['tasks_total']} task(s), {$metrics['content_total']} content item(s), and {$metrics['active_schedules']} active schedule(s) in available Office data. No performance data available yet.";
        $conclusion = ($metrics['tasks_failed'] + $metrics['content_failed']) > 0 ? 'The pipeline needs owner review for recorded failures before further planning.' : 'Available Office data shows a stable pipeline; continue with owner-reviewed planning.';
        return ['summary' => $summary, 'findings' => $findings, 'conclusion' => $conclusion, 'recommendations' => $recommendations, 'next_actions' => $this->nextActions($context['tasks'], $context['content'])];
    }

    private function nextActions(Collection $tasks, Collection $content): array
    {
        $actions = [];
        foreach ($tasks->where('status', 'failed')->take(5) as $task) $actions[] = ['id' => (string) Str::uuid(), 'status' => 'proposed', 'action_type' => 'task', 'agent_id' => 'jauki-analyst', 'description' => 'Review failed task: '.$task->title, 'reason' => 'The task is marked failed in Office records.', 'target' => $task->id];
        foreach ($content->where('status', 'failed')->take(5) as $item) $actions[] = ['id' => (string) Str::uuid(), 'status' => 'proposed', 'action_type' => 'task', 'agent_id' => 'jauki-analyst', 'description' => 'Review failed content: '.($item->title ?? $item->content_type), 'reason' => 'The content item is marked failed in Office records.', 'target' => $item->id];
        return $actions;
    }

    private function createTask(array $action, OfficeAnalystReport $report): void { OfficeTask::firstOrCreate(['agent_id' => $action['agent_id'] ?? 'jauki-analyst', 'external_id' => 'analyst-'.$report->id.'-'.$action['id']], ['title' => $action['description'], 'type' => 'analyst_follow_up', 'status' => 'queued', 'progress' => 0, 'target' => $action['target'] ?? null, 'metadata' => ['analyst_report_id' => $report->id, 'analyst_action_id' => $action['id'], 'approval_only' => true]]); }
    private function createSchedule(array $action, OfficeAnalystReport $report): void
    {
        if (OfficeContentSchedule::where('metadata->analyst_action_id', $action['id'])->exists()) return;
        $schedule = $action['schedule']; $attributes = collect($schedule)->only(['name', 'brand_id', 'platform', 'content_type', 'topic', 'brief', 'assigned_agent', 'timezone', 'generation_mode', 'publishing_mode'])->all();
        $attributes['metadata'] = ['analyst_report_id' => $report->id, 'analyst_action_id' => $action['id'], 'approval_only' => true]; $attributes['schedule_type'] = $schedule['schedule_type'];
        if ($schedule['schedule_type'] === 'one_time') $attributes['scheduled_at'] = CarbonImmutable::parse($schedule['scheduled_at'], $schedule['timezone']);
        else { $attributes['starts_at'] = CarbonImmutable::parse($schedule['starts_at'], $schedule['timezone'])->startOfDay(); $attributes['recurrence_rule'] = ['frequency' => $schedule['frequency'], 'days' => $schedule['days'], 'time' => $schedule['time']]; $attributes['recurrence_label'] = $schedule['frequency'].' at '.$schedule['time']; }
        OfficeContentSchedule::create($attributes);
    }
    private function range(string $type, array $filters): array { $now = CarbonImmutable::now('Asia/Jakarta'); return $type === 'weekly' || ($filters['scope'] ?? null) === 'last_7_days' ? [$now->subDays(6)->startOfDay(), $now->endOfDay()] : [$now->startOfDay(), $now->endOfDay()]; }
    private function finding(string $type, string $title, string $description, string $evidence): array { return compact('type', 'title', 'description', 'evidence'); }
    private function text(mixed $value, int $limit): ?string { if (! is_string($value)) return null; $value = trim(strip_tags($value)); return $value === '' ? null : Str::limit($value, $limit, ''); }
    private function allResolved(array $actions): bool { return $actions !== [] && collect($actions)->every(fn ($action) => in_array($action['status'] ?? 'proposed', ['approved', 'dismissed'], true)); }
}
