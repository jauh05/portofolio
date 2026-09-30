<?php

namespace App\Services;

use App\Models\OfficeCommand;
use App\Models\OfficeContentItem;
use App\Models\OfficeContentPlanItem;
use App\Models\OfficeContentSchedule;
use App\Models\OfficeResearchRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class OfficeScheduledGenerationService
{
    private const BLOCKING_STATUSES = ['queued_for_generation', 'generating', 'ready_for_review', 'ready', 'approved', 'scheduled_for_publish', 'publishing', 'published'];

    public function __construct(private OfficeContentGenerator $generator) {}

    public function runDue(int $limit = 50): array
    {
        $summary = ['eligible' => 0, 'generated' => 0, 'skipped' => 0, 'failed' => 0];
        $schedules = OfficeContentSchedule::with('brand')->where('is_active', true)->whereIn('generation_timing', ['immediate', 'lead_time'])->limit($limit)->get();

        foreach ($schedules as $schedule) {
            $occurrence = $this->nextOccurrence($schedule);
            if (! $occurrence || ! $this->isDue($schedule, $occurrence)) {
                continue;
            }
            $summary['eligible']++;
            $result = $this->generateSchedule($schedule, $occurrence->toIso8601String());
            $summary[$result['status'] === 'generated' ? 'generated' : ($result['status'] === 'failed' ? 'failed' : 'skipped')]++;
        }

        return $summary;
    }

    public function generateSchedule(OfficeContentSchedule $schedule, ?string $occurrenceAt = null, bool $regenerate = false): array
    {
        if (! $schedule->is_active || $schedule->trashed()) {
            return ['status' => 'skipped', 'reason' => 'inactive_or_deleted'];
        }

        return DB::transaction(function () use ($schedule, $occurrenceAt, $regenerate) {
            $schedule = OfficeContentSchedule::with('brand')->lockForUpdate()->find($schedule->id);
            if (! $schedule || ! $schedule->is_active || $schedule->trashed()) {
                return ['status' => 'skipped', 'reason' => 'inactive_or_deleted'];
            }

            $item = $this->generator->ensureDraft($schedule, $occurrenceAt);
            if (! $item) return ['status' => 'failed', 'reason' => 'no_occurrence'];
            $item->refresh();

            if (! $regenerate && in_array($item->status, self::BLOCKING_STATUSES, true)) {
                return ['status' => 'skipped', 'reason' => 'content_exists', 'content_id' => $item->id];
            }

            $commandExists = OfficeCommand::whereIn('status', ['queued', 'claimed', 'running'])
                ->where('payload->content_id', $item->id)
                ->whereIn('action', ['generate_feed', 'generate_story', 'generate_threads', 'generate_article'])
                ->exists();
            if (! $regenerate && $commandExists) {
                return ['status' => 'skipped', 'reason' => 'command_exists', 'content_id' => $item->id];
            }

            [$agent, $action] = $this->routeFor($item->platform, $item->content_type);
            $payload = $this->payloadFor($item, $schedule);
            OfficeCommand::create(['agent_id' => $agent, 'action' => $action, 'payload' => $payload, 'status' => 'queued', 'requested_by' => auth()->id() ?? User::where('is_office_owner', true)->first()?->id]);

            $item->update(['status' => 'queued_for_generation', 'metadata' => array_merge($item->metadata ?? [], ['generation_requested_at' => now('Asia/Jakarta')->toIso8601String(), 'scheduled_at' => $payload['scheduled_at']])]);
            $schedule->update(['last_generated_at' => now('Asia/Jakarta')]);

            return ['status' => 'generated', 'content_id' => $item->id];
        });
    }

    public function setTiming(OfficeContentSchedule $schedule, string $timing, ?int $leadMinutes = null): void
    {
        $schedule->generation_timing = $timing;
        $schedule->generation_lead_minutes = $timing === 'lead_time' ? $leadMinutes : null;
        $schedule->generation_mode = $timing === 'manual' ? 'manual' : 'automatic';
        $schedule->save();
    }

    public function routeFor(string $platform, ?string $contentType): array
    {
        $platform = strtolower($platform);
        $type = strtolower($contentType ?? 'feed');
        if ($platform === 'threads') return ['jauki-threads', 'generate_threads'];
        if (in_array($platform, ['article', 'blog', 'website'], true) || in_array($type, ['article', 'blog'], true)) return ['jauki-article', 'generate_article'];
        return ['jauki-social', $type === 'story' ? 'generate_story' : 'generate_feed'];
    }

    private function isDue(OfficeContentSchedule $schedule, CarbonImmutable $occurrence): bool
    {
        if ($schedule->generation_timing === 'immediate') return true;
        $due = $occurrence->subMinutes((int) ($schedule->generation_lead_minutes ?? 0));
        return CarbonImmutable::now($schedule->timezone ?: 'Asia/Jakarta')->greaterThanOrEqualTo($due);
    }

    private function nextOccurrence(OfficeContentSchedule $schedule): ?CarbonImmutable
    {
        $tz = $schedule->timezone ?: 'Asia/Jakarta';
        if ($schedule->schedule_type === 'one_time') return $schedule->scheduled_at ? CarbonImmutable::instance($schedule->scheduled_at)->setTimezone($tz) : null;
        return $schedule->next_run_at ? CarbonImmutable::instance($schedule->next_run_at)->setTimezone($tz) : null;
    }

    private function payloadFor(OfficeContentItem $item, OfficeContentSchedule $schedule): array
    {
        $metadata = $schedule->metadata ?? [];
        $plan = isset($metadata['plan_item_id']) ? OfficeContentPlanItem::find($metadata['plan_item_id']) : null;
        if ($plan) $metadata = array_merge($plan->metadata ?? [], $metadata);
        $run = $plan?->research_run_id ? OfficeResearchRun::find($plan->research_run_id) : null;
        $scheduledAt = $item->metadata['scheduled_at'] ?? $schedule->next_run_at?->toIso8601String() ?? $schedule->scheduled_at?->toIso8601String();

        return [
            'content_id' => $item->id,
            'brand' => $schedule->brand?->name,
            'platform' => $item->platform,
            'content_type' => $item->content_type,
            'objective' => $metadata['objective'] ?? null,
            'audience' => $metadata['audience'] ?? null,
            'audience_problem' => $metadata['audience_problem'] ?? null,
            'angle' => $metadata['angle'] ?? null,
            'hook_direction' => $metadata['hook_direction'] ?? null,
            'key_message' => $metadata['key_message'] ?? null,
            'cta' => $metadata['cta'] ?? null,
            'topic' => $schedule->topic,
            'brief' => $schedule->brief,
            'research_summary' => $metadata['research_summary'] ?? null,
            'key_findings' => $metadata['key_findings'] ?? $run?->findings,
            'source_evidence' => $metadata['source_evidence'] ?? $run?->sources,
            'scheduled_at' => $scheduledAt,
            'language' => 'id',
            'prompt' => $this->prompt($item, $schedule, $metadata, $run),
        ];
    }

    private function prompt(OfficeContentItem $item, OfficeContentSchedule $schedule, array $metadata, ?OfficeResearchRun $run): string
    {
        return "Brand: ".($schedule->brand?->name ?? '-')."\nPlatform: {$item->platform}\nFormat: {$item->content_type}\nAudience: ".($metadata['audience'] ?? '-')."\nObjective: ".($metadata['objective'] ?? '-')."\nTopic: ".($schedule->topic ?? $item->title)."\nAudience problem: ".($metadata['audience_problem'] ?? '-')."\nAngle: ".($metadata['angle'] ?? '-')."\nHook: ".($metadata['hook_direction'] ?? '-')."\nKey message: ".($metadata['key_message'] ?? '-')."\nCTA: ".($metadata['cta'] ?? '-')."\nBrief: ".($schedule->brief ?? '-')."\nResearch summary: ".($metadata['research_summary'] ?? '-')."\nResearch evidence: ".json_encode(['findings' => $metadata['key_findings'] ?? $run?->findings, 'sources' => $metadata['source_evidence'] ?? $run?->sources], JSON_UNESCAPED_SLASHES)."\nGenerate hook, structure, caption, CTA, hashtags, and visual brief.";
    }
}
