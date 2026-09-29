<?php

namespace App\Services;

use App\Models\OfficeContentBrand;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OfficeContentPlannerAiService
{
    public function analyze(string $prompt, Collection $brands): array
    {
        $baseUrl = rtrim((string) config('services.office_ai.base_url'), '/');
        $apiKey = (string) config('services.office_ai.api_key');
        $model = (string) config('services.office_ai.model');
        if ($baseUrl === '' || $apiKey === '' || $model === '') {
            throw new RuntimeException('AI Planner is not configured yet. Add the Office AI provider environment settings first.');
        }

        try {
            $response = Http::acceptJson()->withToken($apiKey)->timeout(20)->post($baseUrl.'/chat/completions', [
                'model' => $model,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt($brands)],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);
        } catch (\Throwable $exception) {
            throw new RuntimeException('AI Planner is taking too long. Try again.');
        }
        if (! $response->successful()) {
            throw new RuntimeException('Planner could not analyze this request.');
        }

        $content = $response->json('choices.0.message.content');
        $decoded = is_string($content) ? json_decode($content, true) : null;
        if (! is_array($decoded)) {
            throw new RuntimeException('Planner returned an invalid schedule preview. Try again.');
        }

        return $this->normalize($decoded, $brands);
    }

    private function systemPrompt(Collection $brands): string
    {
        $brandList = $brands->map(fn (OfficeContentBrand $brand) => ['name' => $brand->name, 'slug' => $brand->slug])->values()->all();

        return 'You are a scheduling analyst. Return JSON only with this exact top-level schema: '
            .'{"type":"schedule_plan","items":[],"clarifications":[]}. '
            .'Today is '.now('Asia/Jakarta')->toDateString().' in Asia/Jakarta. '
            .'Each item needs schedule_type (one_time or recurring), brand_slug, platform, content_type, topic, timezone, and time in HH:MM. '
            .'One-time requires scheduled_at as ISO-8601. Recurring requires frequency (daily, weekly, monthly), weekdays as English weekday names when weekly, and starts_at as ISO-8601 date. '
            .'If any requested detail is ambiguous, put a concise message in clarifications instead of guessing. '
            .'Available brands: '.json_encode($brandList, JSON_THROW_ON_ERROR);
    }

    private function normalize(array $raw, Collection $brands): array
    {
        $clarifications = collect($raw['clarifications'] ?? [])->filter('is_string')->map('trim')->filter()->values()->all();
        $items = [];
        foreach (array_slice(is_array($raw['items'] ?? null) ? $raw['items'] : [], 0, 10) as $index => $item) {
            if (! is_array($item)) {
                $clarifications[] = 'Schedule item '.($index + 1).' could not be understood.';
                continue;
            }
            $normalized = $this->normalizeItem($item, $brands, $clarifications, $index + 1);
            if ($normalized) $items[] = $normalized;
        }
        if ($items === [] && $clarifications === []) $clarifications[] = 'Please include a brand, content type, and schedule time.';

        return ['plan' => ['type' => 'schedule_plan', 'items' => $items], 'clarifications' => array_values(array_unique($clarifications))];
    }

    private function normalizeItem(array $item, Collection $brands, array &$clarifications, int $number): ?array
    {
        $brandSlug = strtolower(trim((string) ($item['brand_slug'] ?? '')));
        $brand = $brands->firstWhere('slug', $brandSlug);
        if (! $brand) {
            $clarifications[] = $brandSlug === '' ? "Schedule item {$number} needs a workspace/brand." : "Workspace '{$brandSlug}' is not available.";
            return null;
        }
        $type = $item['schedule_type'] ?? null;
        $platform = strtolower(trim((string) ($item['platform'] ?? '')));
        $contentType = strtolower(trim((string) ($item['content_type'] ?? '')));
        $time = trim((string) ($item['time'] ?? ''));
        if (! in_array($type, ['one_time', 'recurring'], true) || $platform === '' || $contentType === '') {
            $clarifications[] = "Schedule item {$number} needs a platform and content type.";
            return null;
        }
        if (! preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $time)) {
            $clarifications[] = "Schedule item {$number} needs a time.";
            return null;
        }
        $timezone = 'Asia/Jakarta';
        $topic = trim((string) ($item['topic'] ?? ''));
        $base = [
            'name' => $topic !== '' ? $topic : ucfirst($contentType).' for '.$brand->name,
            'brand_id' => $brand->id, 'schedule_type' => $type, 'platform' => $platform, 'content_type' => $contentType,
            'topic' => $topic ?: null, 'timezone' => $timezone, 'generation_mode' => 'manual', 'publishing_mode' => 'review',
        ];
        if ($type === 'one_time') {
            try {
                $when = CarbonImmutable::parse((string) ($item['scheduled_at'] ?? ''), $timezone);
            } catch (\Throwable) {
                $clarifications[] = "Schedule item {$number} needs a valid date.";
                return null;
            }
            return array_merge($base, ['scheduled_at' => $when->format('Y-m-d H:i:s')]);
        }
        $frequency = $item['frequency'] ?? null;
        if (! in_array($frequency, ['daily', 'weekly', 'monthly'], true)) {
            $clarifications[] = "Schedule item {$number} needs a recurrence frequency.";
            return null;
        }
        $days = collect($item['weekdays'] ?? [])->map(fn ($day) => ['monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6, 'sunday' => 7][strtolower((string) $day)] ?? null)->filter()->values()->all();
        if ($frequency === 'weekly' && $days === []) {
            $clarifications[] = "Schedule item {$number} needs at least one weekday.";
            return null;
        }
        try {
            $startsAt = CarbonImmutable::parse((string) ($item['starts_at'] ?? now($timezone)->toDateString()), $timezone)->toDateString();
        } catch (\Throwable) {
            $clarifications[] = "Schedule item {$number} has an invalid start date.";
            return null;
        }
        return array_merge($base, ['frequency' => $frequency, 'days' => $days, 'time' => $time, 'starts_at' => $startsAt]);
    }
}
