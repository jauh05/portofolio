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
        $report = OfficeAnalystReport::create([
            'report_date' => now('Asia/Jakarta')->toDateString(),
            'report_type' => $type,
            'status' => 'generating', // Awaiting research
            'summary' => 'Memulai riset eksternal...',
            'findings' => [], 'conclusion' => '', 'recommendations' => [], 'next_actions' => [],
            'metadata' => ['filters' => $filters, 'planning_horizon' => $filters['planning_horizon'] ?? '7_days'],
            'generated_at' => now('Asia/Jakarta'),
        ]);

        $topic = $filters['topic'] ?? 'General Trends';
        $run = \App\Models\OfficeResearchRun::create([
            'command_id' => null, // populated below
            'brand_id' => $filters['brand_id'] ?? null,
            'scope' => $type,
            'query' => $topic,
            'status' => 'queued',
            'started_at' => now(),
            'metadata' => ['analyst_report_id' => $report->id],
        ]);

        $command = OfficeCommand::create([
            'agent_id' => 'jauki-analyst',
            'action' => 'research_trends',
            'payload' => ['topic' => $topic, 'audience' => $filters['audience'] ?? null, 'language' => 'id', 'limit' => 5],
            'status' => 'queued',
            'requested_by' => auth()->id() ?? \App\Models\User::where('is_office_owner', true)->first()?->id,
        ]);

        $run->update(['command_id' => $command->id]);
        return $report;
    }


    public function generatePlanFromResearch(\App\Models\OfficeResearchRun $run): void
    {
        $reportId = $run->metadata['analyst_report_id'] ?? null;
        if (!$reportId) return;
        $report = OfficeAnalystReport::find($reportId);
        if (!$report) return;

        $type = $report->report_type;
        $filters = $report->metadata['filters'] ?? [];
        [$start, $end] = $this->range($type, $filters);
        $context = $this->collect($start, $end, $filters);

        $body = $this->factualReport($context, $start, $end, $type);
        $analysisMode = 'fallback_internal';
        try {
            $contextJson = json_encode(array_merge($this->aiContext($context, $start, $end, $type), [
                'agent_reach_research' => [
                    'topic' => $run->query,
                    'sources' => $run->sources,
                    'findings' => $run->findings,
                    'trends' => $run->trends,
                    'content_ideas' => $run->content_ideas,
                ]
            ]), JSON_THROW_ON_ERROR);
            $body = $this->normalizeAiReport($this->ai->completeJson($this->systemPrompt($filters), $contextJson), $context['brands']);
            $analysisMode = 'ai';
        } catch (\Throwable $e) {
            // An unavailable/malformed provider must not create fake AI output.
        }

        $report->update([
            'status' => 'ready',
            'summary' => $body['summary'],
            'findings' => $body['findings'],
            'conclusion' => $body['conclusion'],
            'recommendations' => $body['recommendations'],
            'next_actions' => [], // Plan items handle actions now
            'metadata' => array_merge($report->metadata ?? [], [
                'source' => 'agent_reach',
                'analysis_mode' => $analysisMode,
                'range_start' => $start->toIso8601String(),
                'range_end' => $end->toIso8601String(),
                'metrics' => $context['metrics'],
                'performance_available' => false,
                'research_run_id' => $run->id,
                'source_count' => is_array($run->sources) ? count($run->sources) : 0,
            ]),
            'generated_at' => now('Asia/Jakarta'),
        ]);

        // Create Content Plan Items from AI Output
        $this->createPlanItems($body['content_plan'] ?? [], $run, $context['brands'], $report);

        OfficeNotification::create([
            'deduplication_key' => 'analyst-report-'.$report->id, 'type' => 'analyst.report_ready', 'agent_id' => 'jauki-analyst',
            'title' => ucfirst($type).' Analyst Brief is ready', 'message' => 'Review findings and approve any proposed next actions.', 'severity' => 'info', 'data' => ['report_id' => $report->id], 'created_at' => now('Asia/Jakarta'),
        ]);
    }

    private function createPlanItems(array $plans, \App\Models\OfficeResearchRun $run, $brands, OfficeAnalystReport $report): void
    {
        $batchId = (string) Str::uuid();
        $existingTopics = \App\Models\OfficeContentPlanItem::where('research_run_id', $run->id)->pluck('topic')->map(fn ($topic) => Str::lower($topic))->all();
        foreach ($plans as $plan) {
            $plan = $this->normalizeContentPlan($plan, $run, $brands);
            if (!$plan || in_array(Str::lower($plan['topic']), $existingTopics, true)) continue;
            \App\Models\OfficeContentPlanItem::create([
                'research_run_id' => $run->id,
                'brand_id' => $plan['brand']->id,
                'platform' => $plan['platform'],
                'content_type' => $plan['content_type'],
                'topic' => $plan['topic'],
                'brief' => $plan['brief'],
                'reason' => $plan['reason'],
                'scheduled_at' => $plan['scheduled_at'],
                'generation_mode' => $plan['generation_mode'],
                'publishing_mode' => $plan['publishing_mode'],
                'assigned_agent' => $this->agentFor($plan['platform'], $plan['content_type']),
                'status' => 'proposed',
                'metadata' => array_merge($plan['metadata'], [
                    'source' => 'analyst',
                    'analyst_report_id' => $report->id,
                    'analyst_plan_batch_id' => $batchId,
                    'research_run_id' => $run->id,
                ]),
            ]);
            $existingTopics[] = Str::lower($plan['topic']);
        }
    }

    private function normalizeContentPlan(array $plan, \App\Models\OfficeResearchRun $run, $brands): ?array
    {
        $brand = $brands->firstWhere('slug', strtolower((string) ($plan['brand_slug'] ?? '')));
        if (!$brand) return null;
        $platform = strtolower((string) ($plan['platform'] ?? 'instagram'));
        $contentType = strtolower((string) ($plan['content_type'] ?? 'feed'));
        if (!$this->agentFor($platform, $contentType)) return null;
        $topic = $this->text($plan['topic'] ?? null, 255);
        $brief = $this->text($plan['brief'] ?? null, 4000);
        if (!$topic || !$brief) return null;
        try { $scheduledAt = CarbonImmutable::parse($plan['scheduled_at'] ?? now('Asia/Jakarta')->addDay(), 'Asia/Jakarta'); }
        catch (\Throwable) { return null; }
        $evidence = collect($run->sources ?? [])->take(5)->map(fn ($source) => [
            'title' => $source['title'] ?? $source['name'] ?? 'Sumber riset',
            'url' => $source['url'] ?? null,
            'snippet' => $source['snippet'] ?? $source['summary'] ?? null,
        ])->values()->all();
        return [
            'brand' => $brand, 'platform' => $platform, 'content_type' => $contentType, 'topic' => $topic, 'brief' => $brief,
            'reason' => $this->text($plan['reason'] ?? null, 1000) ?: 'Berdasarkan peluang dari riset Agent-Reach.',
            'scheduled_at' => $scheduledAt,
            'generation_mode' => in_array($plan['generation_mode'] ?? 'manual', ['manual', 'automatic'], true) ? $plan['generation_mode'] : 'manual',
            'publishing_mode' => in_array($plan['publishing_mode'] ?? 'review', ['review', 'automatic'], true) ? $plan['publishing_mode'] : 'review',
            'metadata' => [
                'brand_slug' => $brand->slug,
                'objective' => $this->text($plan['objective'] ?? null, 100) ?: 'education',
                'audience' => $this->text($plan['audience'] ?? null, 255),
                'audience_problem' => $this->text($plan['audience_problem'] ?? null, 500),
                'angle' => $this->text($plan['angle'] ?? null, 500),
                'hook_direction' => $this->text($plan['hook_direction'] ?? null, 500),
                'key_message' => $this->text($plan['key_message'] ?? null, 800),
                'cta' => $this->text($plan['cta'] ?? null, 300),
                'research_summary' => $this->text($plan['research_summary'] ?? null, 1000) ?: $this->text($run->summary ?? null, 1000),
                'key_findings' => $plan['key_findings'] ?? $run->findings ?? [],
                'source_evidence' => $plan['source_evidence'] ?? $evidence,
            ],
        ];
    }

    private function agentFor(string $platform, string $contentType): ?string
    {
        $action = 'generate_'.$contentType;
        $agent = match ($platform) { 'instagram' => 'jauki-social', 'threads' => 'jauki-threads', 'article', 'blog', 'website' => 'jauki-article', default => null };
        if ($platform === 'threads' && $contentType === 'thread') $action = 'generate_threads';
        if (in_array($contentType, ['article', 'blog'], true)) $action = 'generate_article';
        return $agent && in_array($action, OfficeRegistry::COMMANDS[$agent] ?? [], true) ? $agent : null;
    }

    public function approve(OfficeAnalystReport $report, array $planIds): OfficeAnalystReport
    {
        return DB::transaction(function () use ($report, $planIds) {
            $report = OfficeAnalystReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            $researchRunId = $report->metadata['research_run_id'] ?? null;

            $query = \App\Models\OfficeContentPlanItem::whereIn('id', $planIds)->where('status', 'proposed');
            if ($researchRunId) {
                $query->where('research_run_id', $researchRunId);
            } else {
                $query->where('metadata->analyst_report_id', $report->id);
            }
            $plans = $query->get();

            foreach ($plans as $plan) {
                $schedule = \App\Models\OfficeContentSchedule::create([
                    'brand_id' => $plan->brand_id,
                    'name' => 'Plan: ' . $plan->topic,
                    'schedule_type' => 'one_time',
                    'platform' => $plan->platform,
                    'content_type' => $plan->content_type,
                    'topic' => $plan->topic,
                    'brief' => $plan->brief,
                    'scheduled_at' => $plan->scheduled_at,
                    'next_run_at' => $plan->scheduled_at,
                    'timezone' => 'Asia/Jakarta',
                    'generation_mode' => $plan->generation_mode,
                    'generation_timing' => $plan->metadata['generation_timing'] ?? 'lead_time',
                    'generation_lead_minutes' => $plan->metadata['generation_lead_minutes'] ?? 1440,
                    'publishing_mode' => $plan->publishing_mode,
                    'metadata' => array_merge($plan->metadata ?? [], [
                        'source' => 'analyst',
                        'plan_item_id' => $plan->id,
                        'analyst_report_id' => $report->id,
                        'research_run_id' => $plan->research_run_id,
                    ]),
                ]);

                $plan->update(['status' => 'approved', 'schedule_id' => $schedule->id]);

                if ($plan->generation_mode === 'automatic' && $schedule->generation_timing === 'immediate') {
                    // Trigger draft and generator immediately
                    $generator = app(\App\Services\OfficeContentGenerator::class);
                    $draft = $generator->ensureDraft($schedule);
                    $plan->update(['content_item_id' => $draft->id]);
                    app(\App\Http\Controllers\OfficeContentPlannerController::class)->dispatchGeneration($draft);
                }
            }

            $unresolvedQuery = \App\Models\OfficeContentPlanItem::where('status', 'proposed');
            if ($researchRunId) {
                $unresolvedQuery->where('research_run_id', $researchRunId);
            } else {
                $unresolvedQuery->where('metadata->analyst_report_id', $report->id);
            }

            if (!$unresolvedQuery->exists()) {
                $report->update(['status' => 'reviewed']);
            }
            return $report->fresh();
        });
    }

    public function dismiss(OfficeAnalystReport $report, array $planIds): OfficeAnalystReport
    {
        return DB::transaction(function () use ($report, $planIds) {
            $report = OfficeAnalystReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            \App\Models\OfficeContentPlanItem::where('research_run_id', $report->metadata['research_run_id'] ?? '')
                        ->whereIn('id', $planIds)
                        ->where('status', 'proposed')
                        ->update(['status' => 'dismissed']);

            $unresolved = \App\Models\OfficeContentPlanItem::where('research_run_id', $report->metadata['research_run_id'] ?? '')
                            ->where('status', 'proposed')->exists();
            if (!$unresolved) {
                $report->update(['status' => 'reviewed']);
            }
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

    private function systemPrompt(array $filters = []): string
    {
        $horizon = $filters['planning_horizon'] ?? '7_days';
        $brandScope = $filters['brand_id'] ?? null ? 'Gunakan hanya brand yang dipilih dalam context.' : 'Jika scope Semua Brand, pilih brand yang relevan saja.';
        return 'You are an internal Living Office analyst. Seluruh output yang dibaca pengguna wajib menggunakan Bahasa Indonesia. Return JSON only with schema: {"summary":"","findings":[{"type":"issue|attention|opportunity|status","title":"","description":"","evidence":""}],"conclusion":"","recommendations":[{"priority":"high|medium|low","action":"","reason":""}],"content_plan":[{"brand_slug":"","platform":"instagram|threads|article|blog","content_type":"feed|story|thread|article|blog","topic":"","objective":"","audience":"","audience_problem":"","angle":"","hook_direction":"","key_message":"","cta":"","brief":"","reason":"","scheduled_at":"YYYY-MM-DD HH:mm:ss","research_summary":"","key_findings":[],"source_evidence":[{"title":"","url":"","snippet":""}],"generation_mode":"automatic|manual","publishing_mode":"automatic|review"}]}. Gunakan data Agent-Reach dan internal Office. performance_available is false: masukkan kalimat "Data performa belum tersedia." pada summary. Planning horizon: '.$horizon.'. Jika horizon only_analysis, content_plan boleh kosong. Untuk 7/14 hari buat rencana konkret sesuai jumlah hari yang masuk akal; untuk 30 hari buat rencana bulanan bounded dalam bulan tersebut, jangan recurring tanpa batas. '.$brandScope.' Hindari duplicate topic, jadwal aktif, dan jam bentrok. Jangan buat proposal palsu jika evidence tidak cukup. Jadwal timezone Asia/Jakarta.';
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
        if (! $summary || ! $conclusion || ! str_contains($summary, 'Data performa belum tersedia.')) throw new \UnexpectedValueException('Invalid analyst report.');
        $findings = collect($raw['findings'] ?? [])->filter(fn ($item) => is_array($item))->map(fn ($item) => $this->normalizeFinding($item))->filter()->take(12)->values()->all();
        $recommendations = collect($raw['recommendations'] ?? [])->filter(fn ($item) => is_array($item))->map(fn ($item) => $this->normalizeRecommendation($item))->filter()->take(12)->values()->all();
        $nextActions = collect($raw['next_actions'] ?? [])->filter(fn ($item) => is_array($item))->map(fn ($item) => $this->normalizeAction($item, $brands))->filter()->take(10)->values()->all();
        if ($findings === [] || $recommendations === []) throw new \UnexpectedValueException('Incomplete analyst report.');
        $contentPlan = collect($raw['content_plan'] ?? [])->filter(fn($item) => is_array($item))->values()->all();
        return compact('summary', 'findings', 'conclusion', 'recommendations', 'nextActions') + ['next_actions' => $nextActions, 'content_plan' => $contentPlan];
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
        if ($metrics['tasks_failed'] > 0) $findings[] = $this->finding('issue', $metrics['tasks_failed'].' kegagalan task tercatat', 'Terdapat task yang gagal dalam periode ini.', 'office_tasks.status = failed');
        if ($metrics['content_failed'] > 0) $findings[] = $this->finding('issue', $metrics['content_failed'].' kegagalan konten tercatat', 'Konten yang gagal perlu ditinjau.', 'office_content_items.status = failed');
        $withoutTopic = $context['schedules']->filter(fn ($schedule) => blank($schedule->topic))->count();
        if ($withoutTopic > 0) $findings[] = $this->finding('attention', $withoutTopic.' jadwal memerlukan detail topik', 'Jadwal aktif tanpa topik akan lebih sulit ditinjau.', 'office_content_schedules.topic is null');
        foreach ($context['brands'] as $brand) if ($context['schedules']->where('brand_id', $brand->id)->isEmpty()) $findings[] = $this->finding('opportunity', $brand->name.' tidak memiliki jadwal aktif', 'Tidak ada jadwal konten aktif yang ditugaskan ke workspace ini.', 'office_content_schedules brand count = 0');
        if ($findings === []) $findings[] = $this->finding('status', 'Tidak ditemukan anomali pada data Office', 'Tidak ada kegagalan task/konten atau jadwal yang tidak lengkap pada periode ini.', 'Internal Office records only');
        $recommendations = collect($findings)->filter(fn ($finding) => $finding['type'] !== 'status')->map(fn ($finding) => ['priority' => $finding['type'] === 'issue' ? 'high' : 'medium', 'action' => $finding['title'], 'reason' => $finding['description']])->values()->all();
        if ($recommendations === []) $recommendations[] = ['priority' => 'low', 'action' => 'Lanjutkan pemantauan rencana konten.', 'reason' => 'Tidak ada masalah yang tercatat di data Office.'];
        $summary = "Analisis {$type} dari {$start->toDateString()} hingga {$end->toDateString()}: {$metrics['tasks_total']} task, {$metrics['content_total']} konten, dan {$metrics['active_schedules']} jadwal aktif di data Office. Data performa belum tersedia.";
        $conclusion = ($metrics['tasks_failed'] + $metrics['content_failed']) > 0 ? 'Pipeline memerlukan tinjauan owner untuk kegagalan yang tercatat sebelum melanjutkan perencanaan.' : 'Data Office yang tersedia menunjukkan pipeline yang stabil; lanjutkan dengan perencanaan yang ditinjau oleh owner.';
        return ['summary' => $summary, 'findings' => $findings, 'conclusion' => $conclusion, 'recommendations' => $recommendations, 'next_actions' => $this->nextActions($context['tasks'], $context['content'])];
    }

    private function nextActions(Collection $tasks, Collection $content): array
    {
        $actions = [];
        foreach ($tasks->where('status', 'failed')->take(5) as $task) $actions[] = ['id' => (string) Str::uuid(), 'status' => 'proposed', 'action_type' => 'task', 'agent_id' => 'jauki-analyst', 'description' => 'Tinjau task yang gagal: '.$task->title, 'reason' => 'Task ditandai gagal dalam catatan Office.', 'target' => $task->id];
        foreach ($content->where('status', 'failed')->take(5) as $item) $actions[] = ['id' => (string) Str::uuid(), 'status' => 'proposed', 'action_type' => 'task', 'agent_id' => 'jauki-analyst', 'description' => 'Tinjau konten yang gagal: '.($item->title ?? $item->content_type), 'reason' => 'Konten ditandai gagal dalam catatan Office.', 'target' => $item->id];
        return $actions;
    }

    private function createTask(array $action, OfficeAnalystReport $report): void { OfficeTask::firstOrCreate(['agent_id' => $action['agent_id'] ?? 'jauki-analyst', 'external_id' => 'analyst-'.$report->id.'-'.$action['id']], ['title' => $action['description'], 'type' => 'analyst_follow_up', 'status' => 'queued',
            'requested_by' => auth()->id() ?? \App\Models\User::where('is_office_owner', true)->first()?->id, 'progress' => 0, 'target' => $action['target'] ?? null, 'metadata' => ['analyst_report_id' => $report->id, 'analyst_action_id' => $action['id'], 'approval_only' => true]]); }
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