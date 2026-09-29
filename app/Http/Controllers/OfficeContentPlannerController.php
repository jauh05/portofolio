<?php

namespace App\Http\Controllers;

use App\Models\OfficeContentItem;
use App\Models\OfficeContentBrand;
use App\Models\OfficeContentSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OfficeContentPlannerController extends Controller
{
    private const CONTENT_STATUSES = ['idea', 'draft', 'scheduled', 'generating', 'ready', 'published', 'failed'];

    public function planner(Request $request): JsonResponse
    {
        $timezone = $request->string('timezone', 'Asia/Jakarta')->value();
        $start = CarbonImmutable::parse($request->string('start', now($timezone)->startOfMonth()->toDateString())->value(), $timezone)->startOfDay();
        $end = CarbonImmutable::parse($request->string('end', $start->endOfMonth()->toDateString())->value(), $timezone)->endOfDay();
        $brandId = $request->string('brand_id')->value();
        $schedules = OfficeContentSchedule::with('brand')->where('is_active', true)
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->orderBy('next_run_at')->get();
        $occurrences = $schedules->flatMap(fn (OfficeContentSchedule $schedule) => $this->occurrences($schedule, $start, $end))
            ->sortBy('scheduledAt')->values();

        return response()->json([
            'range' => ['start' => $start->toIso8601String(), 'end' => $end->toIso8601String(), 'timezone' => $timezone],
            'occurrences' => $occurrences,
            'upcoming' => $occurrences->filter(fn (array $item) => CarbonImmutable::parse($item['scheduledAt'])->greaterThanOrEqualTo(now($timezone)))->take(12)->values(),
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

    public function store(Request $request): JsonResponse
    {
        $schedule = new OfficeContentSchedule;
        $this->fillSchedule($schedule, $request->validate($this->rules()));
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
            'publishing_mode' => $schedule->publishing_mode, 'is_active' => $schedule->is_active, 'metadata' => $schedule->metadata,
        ];
        $this->fillSchedule($schedule, array_replace($existing, $request->validate($this->rules(true))));
        return response()->json($this->schedulePayload($schedule->load('brand')));
    }

    public function destroy(OfficeContentSchedule $schedule): JsonResponse
    {
        $schedule->update(['is_active' => false]);
        return response()->json(['status' => 'deactivated']);
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
            'status' => ['sometimes', Rule::in(self::CONTENT_STATUSES)], 'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'brand_id' => ['sometimes', 'nullable', 'uuid', 'exists:office_content_brands,id'],
        ]);
        $metadata = $content->metadata ?? [];
        if (array_key_exists('scheduled_at', $data)) {
            $metadata['scheduled_at'] = $data['scheduled_at'];
            unset($data['scheduled_at']);
            $data['metadata'] = $metadata;
        }
        $content->update($data);
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
            'generation_mode' => [$required, Rule::in(['manual', 'automatic'])], 'publishing_mode' => [$required, Rule::in(['review', 'automatic'])],
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
        $schedule->next_run_at = $this->nextOccurrence($schedule);
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
        $occurrence = collect($this->occurrences($schedule, now($timezone)->startOfDay(), now($timezone)->addDays(400)->endOfDay()))->first();

        return $occurrence ? CarbonImmutable::parse($occurrence['scheduledAt']) : null;
    }

    private function occurrences(OfficeContentSchedule $schedule, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $timezone = $schedule->timezone;
        if ($schedule->schedule_type === 'one_time') {
            $when = $schedule->scheduled_at?->setTimezone($timezone);
            return $when && $when->betweenIncluded($start, $end) ? [$this->occurrencePayload($schedule, $when)] : [];
        }
        $rule = $schedule->recurrence_rule ?? [];
        $from = $start->max($schedule->starts_at?->setTimezone($timezone)->startOfDay() ?? $start);
        $until = $schedule->ends_at ? $end->min($schedule->ends_at->setTimezone($timezone)->endOfDay()) : $end;
        $items = [];
        for ($day = $from->startOfDay(); $day->lessThanOrEqualTo($until) && count($items) < 100; $day = $day->addDay()) {
            $matches = match ($rule['frequency'] ?? null) {
                'daily' => true,
                'weekly' => in_array($day->isoWeekday(), $rule['days'] ?? [], true),
                'monthly' => $day->day === ($schedule->starts_at?->setTimezone($timezone)->day ?? $day->day),
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
            'recurrenceLabel' => $schedule->recurrence_label, 'generationMode' => $schedule->generation_mode, 'publishingMode' => $schedule->publishing_mode,
            'isActive' => $schedule->is_active, 'startsAt' => $schedule->starts_at?->toIso8601String(), 'endsAt' => $schedule->ends_at?->toIso8601String(),
            'nextRunAt' => $schedule->next_run_at?->toIso8601String(), 'metadata' => $schedule->metadata];
    }

    private function brandPayload(OfficeContentBrand $brand): array
    {
        return ['id' => $brand->id, 'name' => $brand->name, 'slug' => $brand->slug, 'description' => $brand->description,
            'defaultAgent' => $brand->default_agent, 'primaryPlatform' => $brand->primary_platform, 'isActive' => $brand->is_active, 'metadata' => $brand->metadata];
    }
}
