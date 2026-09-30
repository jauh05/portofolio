<?php

namespace App\Http\Controllers;

use App\Models\OfficeContentItem;
use App\Models\OfficeContentBrand;
use App\Models\OfficeContentSchedule;
use App\Services\OfficeAiGatewayException;
use App\Services\OfficeContentPlannerAiService;
use App\Services\OfficeScheduledGenerationService;
use Carbon\CarbonInterface;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OfficeContentPlannerController extends Controller
{
    public function dispatchScheduledPublishing()
    {
        $now = now('Asia/Jakarta');
        $items = \App\Models\OfficeContentItem::whereIn('status', ['approved', 'ready_for_review'])
            ->whereNull('published_at')
            ->get();

        foreach ($items as $item) {
            $schedule = $item->schedule;
            if (!$schedule) continue;

            $pubMode = $schedule->publishing_mode ?? 'review';
            if ($pubMode === 'review' && $item->status !== 'approved') continue;
            if ($pubMode === 'automatic' && !in_array($item->status, ['approved', 'ready_for_review'])) continue;

            $scheduledAt = $item->metadata['scheduled_at'] ?? null;
            if (!$scheduledAt) continue;

            $time = \Carbon\CarbonImmutable::parse($scheduledAt, 'Asia/Jakarta');
            if ($now->lessThan($time)) continue;

            // Mark as publishing
            $item->update(['status' => 'publishing']);

            $agent = match($item->platform) {
                'instagram' => 'jauki-social',
                'threads' => 'jauki-threads',
                'article', 'blog' => 'jauki-article',
                default => null,
            };

            $action = ($agent === 'jauki-article') ? 'publish_article' : 'publish_last';

            if (!$agent || !in_array($action, \App\Services\OfficeRegistry::COMMANDS[$agent] ?? [])) {
                $item->update([
                    'status' => 'ready_to_publish',
                    'metadata' => array_merge($item->metadata ?? [], ['publish_error' => 'Publishing integration belum terhubung'])
                ]);
                continue;
            }

            \App\Models\OfficeCommand::create([
                'agent_id' => $agent,
                'action' => $action,
                'payload' => [
                    'content_id' => $item->id,
                ],
                'status' => 'queued',
            'requested_by' => auth()->id() ?? \App\Models\User::where('is_office_owner', true)->first()?->id,
            ]);
        }
    }
    private const CONTENT_STATUSES = ['idea', 'draft', 'scheduled', 'generating', 'ready', 'published', 'failed'];

    public function planner(Request $request): JsonResponse
    {
        $timezone = $request->string('timezone', 'Asia/Jakarta')->value();
        $start = CarbonImmutable::parse($request->string('start', now($timezone)->startOfMonth()->toDateString())->value(), $timezone)->startOfDay();
        $end = CarbonImmutable::parse($request->string('end', $start->endOfMonth()->toDateString())->value(), $timezone)->endOfDay();
        $brandId = $request->string('brand_id')->value();
        $schedules = OfficeContentSchedule::with('brand')
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->orderBy('next_run_at')->get();
        $occurrences = $schedules->flatMap(fn (OfficeContentSchedule $schedule) => $this->occurrences($schedule, $start, $end))
            ->sortBy('scheduledAt')->values();

        return response()->json([
            'range' => ['start' => $start->toIso8601String(), 'end' => $end->toIso8601String(), 'timezone' => $timezone],
            'occurrences' => $occurrences,
            'upcoming' => $occurrences->filter(fn (array $item) => CarbonImmutable::parse($item['scheduledAt'])->greaterThanOrEqualTo(now($timezone)))->take(12)->values(),
            'schedules' => $schedules->map(fn (OfficeContentSchedule $schedule) => $this->schedulePayload($schedule)),
        ]);
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => OfficeContentSchedule::with('brand')->latest()->get()->map(fn (OfficeContentSchedule $schedule) => $this->schedulePayload($schedule))]);
    }

    public function show(OfficeContentSchedule $schedule): JsonResponse
    {
        return response()->json($this->schedulePayload($schedule->load('brand')));
    }

    public function brands(): JsonResponse
    {
        return response()->json(['data' => OfficeContentBrand::where('is_active', true)->orderBy('name')->get()->map(fn (OfficeContentBrand $brand) => $this->brandPayload($brand))]);
    }

    public function storeBrand(Request $request): JsonResponse
    {
        $brand = OfficeContentBrand::create($request->validate([
            'name' => ['required', 'string', 'max:255'], 'slug' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:office_content_brands,slug'],
            'description' => ['nullable', 'string'], 'default_agent' => ['nullable', 'string', 'max:100'],
            'primary_platform' => ['nullable', 'string', 'max:100'], 'is_active' => ['sometimes', 'boolean'], 'metadata' => ['nullable', 'array'],
        ]));
        return response()->json($this->brandPayload($brand), 201);
    }

    public function updateBrand(Request $request, OfficeContentBrand $brand): JsonResponse
    {
        $brand->update($request->validate([
            'name' => ['sometimes', 'string', 'max:255'], 'slug' => ['sometimes', 'string', 'max:100', 'alpha_dash', Rule::unique('office_content_brands', 'slug')->ignore($brand->id)],
            'description' => ['sometimes', 'nullable', 'string'], 'default_agent' => ['sometimes', 'nullable', 'string', 'max:100'],
            'primary_platform' => ['sometimes', 'nullable', 'string', 'max:100'], 'is_active' => ['sometimes', 'boolean'], 'metadata' => ['sometimes', 'nullable', 'array'],
        ]));
        return response()->json($this->brandPayload($brand->fresh()));
    }

    public function analyze(Request $request, OfficeContentPlannerAiService $planner): JsonResponse
    {
        $prompt = $request->validate(['prompt' => ['required', 'string', 'min:8', 'max:2000']])['prompt'];
        try {
            return response()->json($planner->analyze($prompt, OfficeContentBrand::where('is_active', true)->orderBy('name')->get()));
        } catch (OfficeAiGatewayException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->getCode() ?: 422);
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function store(Request $request, \App\Services\OfficeContentGenerator $generator): JsonResponse
    {
        $schedule = new OfficeContentSchedule;
        $this->fillSchedule($schedule, $request->validate($this->rules()));
        $draft = $generator->ensureDraft($schedule);
        if ($schedule->generation_mode === 'automatic') {
            $this->dispatchGeneration($draft);
        }
        return response()->json($this->schedulePayload($schedule->load('brand')), 201);
    }

    public function update(Request $request, OfficeContentSchedule $schedule): JsonResponse
    {
        $existing = [
            'name' => $schedule->name, 'schedule_type' => $schedule->schedule_type, 'platform' => $schedule->platform,
            'brand_id' => $schedule->brand_id,
            'content_type' => $schedule->content_type, 'topic' => $schedule->topic, 'brief' => $schedule->brief,
            'assigned_agent' => $schedule->assigned_agent, 'timezone' => $schedule->timezone,
            'scheduled_at' => $schedule->scheduled_at?->format('Y-m-d H:i:s'),
            'frequency' => $schedule->recurrence_rule['frequency'] ?? null, 'days' => $schedule->recurrence_rule['days'] ?? [],
            'time' => $schedule->recurrence_rule['time'] ?? null, 'starts_at' => $schedule->starts_at?->format('Y-m-d H:i:s'),
            'ends_at' => $schedule->ends_at?->format('Y-m-d H:i:s'), 'generation_mode' => $schedule->generation_mode,
            'generation_timing' => $schedule->generation_timing ?? 'manual', 'generation_lead_minutes' => $schedule->generation_lead_minutes,
            'publishing_mode' => $schedule->publishing_mode, 'is_active' => $schedule->is_active, 'metadata' => $schedule->metadata,
        ];
        $this->fillSchedule($schedule, array_replace($existing, $request->validate($this->rules(true))));
        return response()->json($this->schedulePayload($schedule->load('brand')));
    }

    public function destroy(OfficeContentSchedule $schedule): JsonResponse
    {
        $schedule->delete();
        return response()->json(['status' => 'deleted']);
    }

    public function bulkAction(Request $request, OfficeScheduledGenerationService $generation): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['pause', 'resume', 'delete', 'generate', 'set_h1'])],
            'schedule_ids' => ['required', 'array', 'min:1'],
            'schedule_ids.*' => ['uuid'],
        ]);

        if ($data['action'] === 'generate') {
            $summary = ['eligible' => 0, 'generated' => 0, 'skipped' => 0, 'failed' => 0];
            OfficeContentSchedule::whereIn('id', $data['schedule_ids'])->get()->each(function (OfficeContentSchedule $schedule) use (&$summary, $generation) {
                $summary['eligible']++;
                $result = $generation->generateSchedule($schedule);
                $summary[$result['status'] === 'generated' ? 'generated' : ($result['status'] === 'failed' ? 'failed' : 'skipped')]++;
            });
            return response()->json(['action' => 'generate', 'summary' => $summary]);
        }

        $affected = DB::transaction(function () use ($data, $generation) {
            $schedules = OfficeContentSchedule::whereIn('id', $data['schedule_ids'])->lockForUpdate()->get();
            foreach ($schedules as $schedule) {
                if ($data['action'] === 'delete') {
                    $schedule->delete();
                    continue;
                }
                if ($data['action'] === 'set_h1') {
                    $generation->setTiming($schedule, 'lead_time', 1440);
                    continue;
                }
                $schedule->is_active = $data['action'] === 'resume';
                $schedule->next_run_at = $schedule->is_active ? $this->nextOccurrence($schedule) : null;
                $schedule->save();
            }
            return $schedules->count();
        });

        return response()->json(['action' => $data['action'], 'affected' => $affected]);
    }

    public function generateSchedule(OfficeContentSchedule $schedule, OfficeScheduledGenerationService $generation): JsonResponse
    {
        return response()->json($generation->generateSchedule($schedule));
    }

    public function generateMonth(Request $request, OfficeScheduledGenerationService $generation): JsonResponse
    {
        $data = $request->validate(['start' => ['required', 'date'], 'end' => ['required', 'date'], 'brand_id' => ['nullable', 'uuid']]);
        $query = OfficeContentSchedule::where('is_active', true)
            ->when($data['brand_id'] ?? null, fn ($q, $brandId) => $q->where('brand_id', $brandId))
            ->where(function ($q) use ($data) {
                $q->whereBetween('scheduled_at', [$data['start'], $data['end']])->orWhere('schedule_type', 'recurring');
            });
        $summary = ['eligible' => 0, 'generated' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ($query->limit(100)->get() as $schedule) {
            $summary['eligible']++;
            $result = $generation->generateSchedule($schedule);
            $summary[$result['status'] === 'generated' ? 'generated' : ($result['status'] === 'failed' ? 'failed' : 'skipped')]++;
        }
        return response()->json(['action' => 'generate_month', 'summary' => $summary]);
    }

    public function generateAllActive(OfficeScheduledGenerationService $generation): JsonResponse
    {
        $summary = ['eligible' => 0, 'generated' => 0, 'skipped' => 0, 'failed' => 0];
        foreach (OfficeContentSchedule::where('is_active', true)->limit(100)->get() as $schedule) {
            $summary['eligible']++;
            $result = $generation->generateSchedule($schedule);
            $summary[$result['status'] === 'generated' ? 'generated' : ($result['status'] === 'failed' ? 'failed' : 'skipped')]++;
        }
        return response()->json(['action' => 'generate_all_active', 'summary' => $summary, 'limit' => 100]);
    }

    public function createContent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'schedule_id' => ['nullable', 'uuid', 'exists:office_content_schedules,id'],
            'brand_id' => ['required', 'uuid', 'exists:office_content_brands,id'],
            'agent_id' => ['required', 'string', 'max:100'], 'platform' => ['required', 'string', 'max:100'],
            'content_type' => ['required', 'string', 'max:100'], 'title' => ['nullable', 'string', 'max:255'],
            'text' => ['nullable', 'string'], 'status' => ['required', Rule::in(self::CONTENT_STATUSES)],
        ]);
        $item = OfficeContentItem::create(array_merge($data, [
            'deduplication_key' => hash('sha256', Str::uuid().'|'.($data['title'] ?? '')),
            'generated_at' => now(),
        ]));
        return response()->json($item->load('brand'), 201);
    }

    public function updateContent(Request $request, OfficeContentItem $content): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:255'], 'text' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', Rule::in(array_merge(self::CONTENT_STATUSES, ['ready_for_review', 'revision_requested', 'approved']))], 'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'brand_id' => ['sometimes', 'nullable', 'uuid', 'exists:office_content_brands,id'],
            'revision_instruction' => ['sometimes', 'nullable', 'string'],
            'source' => ['sometimes', 'string']
        ]);
        
        // save revision
        if ($request->has('text') && $content->text !== $data['text']) {
            \App\Models\OfficeContentRevision::create([
                'id' => \Illuminate\Support\Str::uuid(),
                'content_item_id' => $content->id,
                'version' => \App\Models\OfficeContentRevision::where('content_item_id', $content->id)->max('version') + 1,
                'source' => $data['source'] ?? 'manual',
                'revision_instruction' => $data['revision_instruction'] ?? null,
                'payload' => json_decode($content->text, true),
            ]);
        }
        
        $metadata = $content->metadata ?? [];
        if (array_key_exists('scheduled_at', $data)) {
            $metadata['scheduled_at'] = $data['scheduled_at'];
            unset($data['scheduled_at']);
            $data['metadata'] = $metadata;
        }
        unset($data['revision_instruction'], $data['source']);
        
        $content->update($data);
        return response()->json($content->fresh()->load('brand'));
    }

    public function generateContent(OfficeContentItem $content, \App\Services\OfficeContentGenerator $generator): JsonResponse
    {
        try {
            $content->update(['status' => 'generating']);
            $schedule = $content->schedule;
            if (!$schedule) abort(404, 'Schedule not found');
            
            $result = $generator->generate($content, $schedule);
            
            $content->update([
                'status' => 'ready_for_review',
                'metadata' => array_merge($content->metadata ?? [], ['generated_payload' => $result, 'visual_brief' => $result['visual_brief'] ?? null]),
                'title' => $result['title'] ?? $content->title,
                'text' => json_encode($result),
            ]);
            
            return response()->json($content->fresh()->load('brand'));
        } catch (\Throwable $e) {
            $content->update(['status' => 'failed']);
            return response()->json(['message' => 'Draft belum berhasil dibuat.', 'error' => $e->getMessage()], 500);
        }
    }

    public function reviseContent(Request $request, OfficeContentItem $content, \App\Services\OfficeContentGenerator $generator): JsonResponse
    {
        $instruction = $request->validate(['instruction' => 'required|string'])['instruction'];
        try {
            $schedule = $content->schedule;
            if (!$schedule) abort(404, 'Schedule not found');
            
            $result = $generator->generate($content, $schedule, $instruction);
            
            return response()->json(['preview' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Revision failed.', 'error' => $e->getMessage()], 500);
        }
    }

    public function approveContent(Request $request, OfficeContentItem $content): JsonResponse
    {
        $content->update(['status' => 'approved']);
        return response()->json($content->fresh()->load('brand'));
    }

    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        return [
            'name' => [$required, 'string', 'max:255'], 'schedule_type' => [$required, Rule::in(['one_time', 'recurring'])],
            'brand_id' => $partial ? ['sometimes', 'nullable', 'uuid', 'exists:office_content_brands,id'] : ['required', 'uuid', 'exists:office_content_brands,id'],
            'platform' => [$required, 'string', 'max:100'], 'content_type' => [$required, 'string', 'max:100'],
            'topic' => ['nullable', 'string', 'max:255'], 'brief' => ['nullable', 'string'], 'assigned_agent' => ['nullable', 'string', 'max:100'],
            'timezone' => ['nullable', 'timezone'], 'scheduled_at' => ['nullable', 'date'],
            'frequency' => ['nullable', Rule::in(['daily', 'weekly', 'monthly'])], 'days' => ['nullable', 'array'], 'days.*' => ['integer', 'between:1,7'],
            'time' => ['nullable', 'date_format:H:i'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'generation_mode' => [$required, Rule::in(['manual', 'automatic'])], 'generation_timing' => ['nullable', Rule::in(['manual', 'immediate', 'lead_time'])],
            'generation_lead_minutes' => ['nullable', 'integer', 'min:0', 'max:43200'], 'publishing_mode' => [$required, Rule::in(['review', 'automatic'])],
            'is_active' => ['sometimes', 'boolean'], 'metadata' => ['nullable', 'array'],
        ];
    }

    private function fillSchedule(OfficeContentSchedule $schedule, array $data): void
    {
        $timezone = $data['timezone'] ?? $schedule->timezone ?? 'Asia/Jakarta';
        $isRecurring = $data['schedule_type'] === 'recurring';
        if (! $isRecurring && empty($data['scheduled_at'])) abort(422, 'scheduled_at is required for one-time content.');
        if ($isRecurring && empty($data['frequency'])) abort(422, 'frequency is required for recurring content.');
        if ($isRecurring && $data['frequency'] === 'weekly' && empty($data['days'])) abort(422, 'Select at least one weekday.');

        $rule = $isRecurring ? ['frequency' => $data['frequency'], 'days' => array_values($data['days'] ?? []), 'time' => $data['time'] ?? '09:00'] : null;
        $attributes = array_merge($data, [
            'timezone' => $timezone, 'recurrence_rule' => $rule, 'recurrence_label' => $isRecurring ? $this->recurrenceLabel($rule) : null,
            'scheduled_at' => $isRecurring ? null : CarbonImmutable::parse($data['scheduled_at'], $timezone),
            'starts_at' => $isRecurring ? CarbonImmutable::parse($data['starts_at'] ?? now($timezone)->toDateString(), $timezone)->startOfDay() : null,
            'ends_at' => $isRecurring && ! empty($data['ends_at']) ? CarbonImmutable::parse($data['ends_at'], $timezone)->endOfDay() : null,
        ]);
        unset($attributes['frequency'], $attributes['days'], $attributes['time']);
        $schedule->fill($attributes);
        $schedule->next_run_at = $schedule->is_active ? $this->nextOccurrence($schedule) : null;
        $schedule->save();
    }

    private function recurrenceLabel(array $rule): string
    {
        if ($rule['frequency'] === 'daily') return 'Every day at '.$rule['time'];
        if ($rule['frequency'] === 'monthly') return 'Every month at '.$rule['time'];
        $names = collect($rule['days'])->map(fn (int $day) => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][$day - 1])->implode(' and ');
        return 'Every '.$names.' at '.$rule['time'];
    }

    private function nextOccurrence(OfficeContentSchedule $schedule): ?CarbonImmutable
    {
        $timezone = $schedule->timezone;
        if ($schedule->schedule_type === 'one_time') {
            return $this->immutable($schedule->scheduled_at, $timezone);
        }

        $start = CarbonImmutable::now($timezone)->startOfDay();
        $end = CarbonImmutable::now($timezone)->addDays(400)->endOfDay();
        $occurrence = collect($this->occurrences($schedule, $start, $end))->first();

        return $occurrence ? CarbonImmutable::parse($occurrence['scheduledAt']) : null;
    }

    private function occurrences(OfficeContentSchedule $schedule, CarbonImmutable $start, CarbonImmutable $end): array
    {
        if (!$schedule->is_active) return [];
        $timezone = $schedule->timezone;
        if ($schedule->schedule_type === 'one_time') {
            $when = $this->immutable($schedule->scheduled_at, $timezone);
            return $when && $when->betweenIncluded($start, $end) ? [$this->occurrencePayload($schedule, $when)] : [];
        }
        $rule = $schedule->recurrence_rule ?? [];
        $startsAt = $this->immutable($schedule->starts_at, $timezone);
        $endsAt = $this->immutable($schedule->ends_at, $timezone);
        $from = $start->max($startsAt?->startOfDay() ?? $start);
        $until = $endsAt ? $end->min($endsAt->endOfDay()) : $end;
        $items = [];
        for ($day = $from->startOfDay(); $day->lessThanOrEqualTo($until) && count($items) < 100; $day = $day->addDay()) {
            $matches = match ($rule['frequency'] ?? null) {
                'daily' => true,
                'weekly' => in_array($day->isoWeekday(), $rule['days'] ?? [], true),
                'monthly' => $day->day === ($startsAt?->day ?? $day->day),
                default => false,
            };
            if (! $matches) continue;
            $when = CarbonImmutable::parse($day->toDateString().' '.($rule['time'] ?? '09:00'), $timezone);
            if ($when->betweenIncluded($start, $end)) $items[] = $this->occurrencePayload($schedule, $when);
        }
        return $items;
    }

    private function occurrencePayload(OfficeContentSchedule $schedule, CarbonImmutable $when): array
    {
        return [
            'id' => $schedule->id.'@'.$when->format('YmdHi'), 'scheduleId' => $schedule->id, 'name' => $schedule->name,
            'topic' => $schedule->topic, 'platform' => $schedule->platform, 'contentType' => $schedule->content_type,
            'assignedAgent' => $schedule->assigned_agent, 'scheduleType' => $schedule->schedule_type,
            'brand' => $schedule->brand ? $this->brandPayload($schedule->brand) : null,
            'recurrenceLabel' => $schedule->recurrence_label, 'scheduledAt' => $when->toIso8601String(), 'status' => 'scheduled',
        ];
    }

    private function schedulePayload(OfficeContentSchedule $schedule): array
    {
        return ['id' => $schedule->id, 'name' => $schedule->name, 'brand' => $schedule->brand ? $this->brandPayload($schedule->brand) : null, 'brandId' => $schedule->brand_id, 'scheduleType' => $schedule->schedule_type, 'platform' => $schedule->platform,
            'contentType' => $schedule->content_type, 'topic' => $schedule->topic, 'brief' => $schedule->brief, 'assignedAgent' => $schedule->assigned_agent,
            'timezone' => $schedule->timezone, 'scheduledAt' => $schedule->scheduled_at?->toIso8601String(), 'recurrenceRule' => $schedule->recurrence_rule,
            'recurrenceLabel' => $schedule->recurrence_label, 'generationMode' => $schedule->generation_mode,
            'generationTiming' => $schedule->generation_timing ?? 'manual', 'generationLeadMinutes' => $schedule->generation_lead_minutes,
            'publishingMode' => $schedule->publishing_mode,
            'isActive' => $schedule->is_active, 'startsAt' => $schedule->starts_at?->toIso8601String(), 'endsAt' => $schedule->ends_at?->toIso8601String(),
            'nextRunAt' => $schedule->next_run_at?->toIso8601String(), 'metadata' => $schedule->metadata];
    }

    private function brandPayload(OfficeContentBrand $brand): array
    {
        return ['id' => $brand->id, 'name' => $brand->name, 'slug' => $brand->slug, 'description' => $brand->description,
            'defaultAgent' => $brand->default_agent, 'primaryPlatform' => $brand->primary_platform, 'isActive' => $brand->is_active, 'metadata' => $brand->metadata];
    }

    private function immutable(?CarbonInterface $value, string $timezone): ?CarbonImmutable
    {
        return $value ? CarbonImmutable::instance($value)->setTimezone($timezone) : null;
    }

    public function dispatchGeneration(\App\Models\OfficeContentItem $content)
    {
        $content->update(['status' => 'generating']);
        $schedule = $content->schedule;
        $brand = $content->brand;
        
        $agent = match($content->platform) {
            'instagram' => 'jauki-social',
            'threads' => 'jauki-threads',
            'article', 'blog', 'website' => 'jauki-article',
            default => 'jauki-social'
        };
        $action = match (true) {
            $agent === 'jauki-threads' => 'generate_threads',
            $agent === 'jauki-article' => 'generate_article',
            default => 'generate_' . ($content->content_type ?? 'feed'),
        };

        $intelligence = $schedule?->metadata ?? [];
        $researchId = $intelligence['plan_item_id'] ?? null;
        $evidence = '';
        if ($researchId) {
            $plan = \App\Models\OfficeContentPlanItem::find($researchId);
            if ($plan) $intelligence = array_merge($plan->metadata ?? [], $intelligence);
            if ($plan && $plan->research_run_id) {
                $run = \App\Models\OfficeResearchRun::find($plan->research_run_id);
                if ($run) {
                    $evidence = "\nResearch Evidence:\n";
                    if ($run->findings) $evidence .= json_encode($run->findings) . "\n";
                    if ($run->sources) $evidence .= json_encode($run->sources) . "\n";
                }
            }
        }

        $generationPrompt = sprintf(
            "Brand: %s\nPlatform: %s\nFormat: %s\nAudience: %s\nObjective: %s\nTopik: %s\nAudience problem: %s\nAngle: %s\nHook direction: %s\nKey message: %s\nCTA: %s\nBrief: %s\nBahasa: Indonesia\n%s\nCreate:\n- hook\n- slide/content structure\n- caption\n- CTA\n- hashtags\n- visual brief",
            $brand?->name ?? 'None',
            $content->platform,
            ucfirst($content->content_type ?? 'feed'),
            $intelligence['audience'] ?? '-',
            $intelligence['objective'] ?? '-',
            $schedule?->topic ?? $content->title,
            $intelligence['audience_problem'] ?? '-',
            $intelligence['angle'] ?? '-',
            $intelligence['hook_direction'] ?? '-',
            $intelligence['key_message'] ?? '-',
            $intelligence['cta'] ?? '-',
            $schedule?->brief ?? '-',
            $evidence
        );

        \App\Models\OfficeCommand::create([
            'agent_id' => $agent,
            'action' => $action,
            'payload' => [
                'content_id' => $content->id,
                'prompt' => $generationPrompt,
                'brand' => $brand?->name,
                'platform' => $content->platform,
                'content_type' => $content->content_type,
                'topic' => $schedule?->topic,
                'brief' => $schedule?->brief,
                'language' => 'id',
                'scheduled_at' => $content->metadata['scheduled_at'] ?? null,
            ],
            'status' => 'queued',
            'requested_by' => auth()->id() ?? \App\Models\User::where('is_office_owner', true)->first()?->id,
        ]);
    }
}
