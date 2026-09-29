<?php

namespace App\Services;

use App\Models\OfficeContentItem;
use App\Models\OfficeContentSchedule;
use Illuminate\Support\Str;
use Carbon\CarbonImmutable;

class OfficeContentGenerator
{
    public function __construct(private OfficeAiGateway $ai) {}

    public function ensureDraft(OfficeContentSchedule $schedule, ?string $occurrenceAt = null): ?OfficeContentItem
    {
        $time = $occurrenceAt ? CarbonImmutable::parse($occurrenceAt, $schedule->timezone) : $schedule->next_run_at;
        if (!$time) return null;

        $key = hash('sha256', "schedule:{$schedule->id}:occurrence:{$time->toIso8601String()}");
        
        $item = OfficeContentItem::firstOrCreate([
            'schedule_id' => $schedule->id,
            'deduplication_key' => $key,
        ], [
            'id' => Str::uuid(),
            'agent_id' => $schedule->assigned_agent ?? $schedule->brand->default_agent ?? 'default',
            'platform' => $schedule->platform,
            'content_type' => $schedule->content_type,
            'status' => 'draft',
            'brand_id' => $schedule->brand_id,
            'metadata' => [
                'scheduled_at' => $time->toIso8601String(),
            ],
            'title' => 'Draft: ' . ($schedule->topic ?? $schedule->name),
        ]);

        return $item;
    }

    public function generate(OfficeContentItem $item, OfficeContentSchedule $schedule, ?string $instruction = null): array
    {
        $platform = strtolower($item->platform);
        $contentType = strtolower($item->content_type);
        
        $system = $this->buildSystemPrompt($platform, $contentType);
        
        $user = "Brand: {$schedule->brand->name}\n";
        $user .= "Topic/Brief: " . ($schedule->brief ?? $schedule->topic ?? $schedule->name) . "\n";
        if ($instruction) {
            $user .= "Revision instruction: {$instruction}\n";
            $user .= "Previous content: " . json_encode([
                'title' => $item->title,
                'text' => $item->text,
                'metadata' => $item->metadata
            ]) . "\n";
        }

        try {
            $result = $this->ai->completeJson($system, $user);
            return $result;
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    private function buildSystemPrompt(string $platform, string $contentType): string
    {
        $lang = "All customer-facing content MUST default to Bahasa Indonesia unless explicitly requested otherwise. Use professional, natural, concise, and easy-to-understand Indonesian.";
        
        if ($platform === 'instagram' && $contentType === 'feed') {
            return "You are an expert social media manager. $lang Return JSON only with this schema: {\"title\":\"...\",\"hook\":\"...\",\"content\":\"...\",\"caption\":\"...\",\"cta\":\"...\",\"hashtags\":[\"...\"],\"visual_brief\":\"...\",\"language\":\"id\"}";
        }
        
        if ($platform === 'instagram' && $contentType === 'story') {
            return "You are an expert social media manager. $lang Return JSON only with this schema: {\"title\":\"...\",\"frames\":[{\"frame\":1,\"text\":\"...\",\"visual_brief\":\"...\"}],\"cta\":\"...\",\"language\":\"id\"}";
        }
        
        // Fallback for others
        return "You are an expert content creator. $lang Return JSON only with this schema: {\"title\":\"...\",\"content\":\"...\",\"visual_brief\":\"...\",\"language\":\"id\"}";
    }
}
