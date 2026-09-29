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
use Illuminate\Support\Str;

class OfficeAnalystReportService
{
    public function generate(string $type = 'daily', array $filters = []): OfficeAnalystReport
    {
        [$start, $end] = $this->range($type, $filters);
        $context = $this->collect($start, $end, $filters);
        $body = $this->factualReport($context, $start, $end, $type);
        $report = OfficeAnalystReport::create([
            'report_date' => $end->toDateString(), 'report_type' => $type, 'status' => 'ready',
            'summary' => $body['summary'], 'findings' => $body['findings'], 'conclusion' => $body['conclusion'],
            'recommendations' => $body['recommendations'], 'next_actions' => $body['next_actions'],
            'metadata' => ['source' => 'internal_office_data', 'range_start' => $start->toIso8601String(), 'range_end' => $end->toIso8601String(), 'metrics' => $context['metrics'], 'performance_available' => false, 'filters' => $filters],
            'generated_at' => now('Asia/Jakarta'),
        ]);
        OfficeNotification::create([
            'deduplication_key' => 'analyst-report-'.$report->id, 'type' => 'analyst.report_ready', 'agent_id' => 'jauki-analyst',
            'title' => ucfirst($type).' Analyst Brief is ready', 'message' => 'Review findings and approve any proposed next actions.', 'severity' => 'info',
            'data' => ['report_id' => $report->id], 'created_at' => now('Asia/Jakarta'),
        ]);

        return $report;
    }

    public function approve(OfficeAnalystReport $report, array $actionIds): OfficeAnalystReport
    {
        $actions = collect($report->next_actions ?? [])->map(function (array $action) use ($actionIds, $report) {
            if (! in_array($action['id'] ?? null, $actionIds, true) || ($action['status'] ?? 'proposed') !== 'proposed') return $action;
            if (($action['action_type'] ?? null) === 'content_schedule') $this->createSchedule($action['schedule'] ?? []);
            if (($action['action_type'] ?? null) === 'task') $this->createTask($action, $report);
            return array_merge($action, ['status' => 'approved', 'approved_at' => now('Asia/Jakarta')->toIso8601String()]);
        })->all();
        $report->update(['next_actions' => $actions, 'status' => $this->allResolved($actions) ? 'reviewed' : 'ready']);

        return $report->fresh();
    }

    public function dismiss(OfficeAnalystReport $report, array $actionIds): OfficeAnalystReport
    {
        $actions = collect($report->next_actions ?? [])->map(fn (array $action) => in_array($action['id'] ?? null, $actionIds, true) && ($action['status'] ?? 'proposed') === 'proposed'
            ? array_merge($action, ['status' => 'dismissed', 'dismissed_at' => now('Asia/Jakarta')->toIso8601String()]) : $action)->all();
        $report->update(['next_actions' => $actions, 'status' => $this->allResolved($actions) ? 'reviewed' : 'ready']);

        return $report->fresh();
    }

    private function collect(CarbonImmutable $start, CarbonImmutable $end, array $filters): array
    {
        $brandId = $filters['brand_id'] ?? null;
        $tasks = OfficeTask::where(function ($query) use ($start, $end) {
            $query->whereBetween('created_at', [$start, $end])->orWhereBetween('started_at', [$start, $end]);
        })->get();
        $events = OfficeEvent::whereBetween('created_at', [$start, $end])->get();
        $commands = OfficeCommand::whereBetween('created_at', [$start, $end])->get();
        $notifications = OfficeNotification::whereBetween('created_at', [$start, $end])->get();
        $schedules = OfficeContentSchedule::with('brand')->where('is_active', true)->when($brandId, fn ($query) => $query->where('brand_id', $brandId))->get();
        $content = OfficeContentItem::with('brand')->whereBetween('created_at', [$start, $end])->when($brandId, fn ($query) => $query->where('brand_id', $brandId))->get();
        $brands = OfficeContentBrand::where('is_active', true)->when($brandId, fn ($query) => $query->whereKey($brandId))->get();
        $metrics = [
            'tasks_total' => $tasks->count(), 'tasks_completed' => $tasks->where('status', 'completed')->count(), 'tasks_failed' => $tasks->where('status', 'failed')->count(),
            'events_total' => $events->count(), 'commands_total' => $commands->count(), 'notifications_total' => $notifications->count(),
            'content_total' => $content->count(), 'content_failed' => $content->where('status', 'failed')->count(), 'active_schedules' => $schedules->count(),
        ];
        return compact('tasks', 'events', 'commands', 'notifications', 'schedules', 'content', 'brands', 'metrics');
    }

    private function factualReport(array $context, CarbonImmutable $start, CarbonImmutable $end, string $type): array
    {
        $metrics = $context['metrics'];
        $findings = [];
        if ($metrics['tasks_failed'] > 0) $findings[] = $this->finding('issue', $metrics['tasks_failed'].' task failure(s) recorded', 'Failed tasks were recorded in the selected period.', 'office_tasks.status = failed');
        if ($metrics['content_failed'] > 0) $findings[] = $this->finding('issue', $metrics['content_failed'].' content failure(s) recorded', 'Content items marked failed need review.', 'office_content_items.status = failed');
        $withoutTopic = $context['schedules']->filter(fn ($schedule) => blank($schedule->topic))->count();
        if ($withoutTopic > 0) $findings[] = $this->finding('attention', $withoutTopic.' schedule(s) need topic detail', 'Active schedules without a topic are harder to review.', 'office_content_schedules.topic is null');
        foreach ($context['brands'] as $brand) {
            if ($context['schedules']->where('brand_id', $brand->id)->isEmpty()) $findings[] = $this->finding('opportunity', $brand->name.' has no active schedule', 'No active content schedule is assigned to this workspace.', 'office_content_schedules brand count = 0');
        }
        if ($findings === []) $findings[] = $this->finding('status', 'No anomalies found in available Office data', 'No task/content failure or incomplete schedule was found in this period.', 'Internal Office records only');
        $recommendations = collect($findings)->filter(fn ($finding) => $finding['type'] !== 'status')->map(fn ($finding) => ['priority' => $finding['type'] === 'issue' ? 'high' : 'medium', 'action' => $finding['title'], 'reason' => $finding['description']])->values()->all();
        if ($recommendations === []) $recommendations[] = ['priority' => 'low', 'action' => 'Continue monitoring the content plan.', 'reason' => 'No issue is recorded in available Office data.'];
        $nextActions = $this->nextActions($context['tasks'], $context['content']);
        $summary = "{$type} analysis for {$start->toDateString()} to {$end->toDateString()}: {$metrics['tasks_total']} task(s), {$metrics['content_total']} content item(s), and {$metrics['active_schedules']} active schedule(s) in available Office data. No performance data available yet.";
        $conclusion = ($metrics['tasks_failed'] + $metrics['content_failed']) > 0 ? 'The pipeline needs owner review for recorded failures before further planning.' : 'Available Office data shows a stable pipeline; continue with owner-reviewed planning.';
        return ['summary' => $summary, 'findings' => $findings, 'conclusion' => $conclusion, 'recommendations' => $recommendations, 'next_actions' => $nextActions];
    }

    private function nextActions(Collection $tasks, Collection $content): array
    {
        $actions = [];
        foreach ($tasks->where('status', 'failed')->take(5) as $task) $actions[] = ['id' => (string) Str::uuid(), 'status' => 'proposed', 'action_type' => 'task', 'agent_id' => 'jauki-analyst', 'description' => 'Review failed task: '.$task->title, 'target' => $task->id];
        foreach ($content->where('status', 'failed')->take(5) as $item) $actions[] = ['id' => (string) Str::uuid(), 'status' => 'proposed', 'action_type' => 'task', 'agent_id' => 'jauki-analyst', 'description' => 'Review failed content: '.($item->title ?? $item->content_type), 'target' => $item->id];
        return $actions;
    }

    private function createTask(array $action, OfficeAnalystReport $report): void
    {
        OfficeTask::create(['agent_id' => $action['agent_id'] ?? 'jauki-analyst', 'external_id' => 'analyst-'.$report->id.'-'.$action['id'], 'title' => $action['description'], 'type' => 'analyst_follow_up', 'status' => 'queued', 'progress' => 0, 'target' => $action['target'] ?? null, 'metadata' => ['analyst_report_id' => $report->id, 'approval_only' => true]]);
    }

    private function createSchedule(array $schedule): void
    {
        $attributes = collect($schedule)->only(['name', 'brand_id', 'platform', 'content_type', 'topic', 'brief', 'assigned_agent', 'timezone', 'generation_mode', 'publishing_mode'])->all();
        $attributes['schedule_type'] = $schedule['schedule_type'];
        if ($schedule['schedule_type'] === 'one_time') $attributes['scheduled_at'] = CarbonImmutable::parse($schedule['scheduled_at'], $schedule['timezone'] ?? 'Asia/Jakarta');
        else {
            $attributes['starts_at'] = CarbonImmutable::parse($schedule['starts_at'], $schedule['timezone'] ?? 'Asia/Jakarta')->startOfDay();
            $attributes['recurrence_rule'] = ['frequency' => $schedule['frequency'], 'days' => $schedule['days'] ?? [], 'time' => $schedule['time']];
            $attributes['recurrence_label'] = $schedule['frequency'].' at '.$schedule['time'];
        }
        OfficeContentSchedule::create($attributes);
    }

    private function range(string $type, array $filters): array
    {
        $now = CarbonImmutable::now('Asia/Jakarta');
        return $type === 'weekly' || ($filters['scope'] ?? null) === 'last_7_days'
            ? [$now->subDays(6)->startOfDay(), $now->endOfDay()]
            : [$now->startOfDay(), $now->endOfDay()];
    }

    private function finding(string $type, string $title, string $description, string $evidence): array
    {
        return compact('type', 'title', 'description', 'evidence');
    }

    private function allResolved(array $actions): bool
    {
        return $actions !== [] && collect($actions)->every(fn ($action) => in_array($action['status'] ?? 'proposed', ['approved', 'dismissed'], true));
    }
}
