<?php

namespace App\Services;

use App\Models\OfficeMemory;
use Illuminate\Database\Eloquent\Collection;

class OfficeMemoryService
{
    public const BRANDS = ['global', 'jauki', 'kauiz'];
    public const TYPES = ['research', 'insight', 'decision', 'result', 'performance', 'preference', 'lesson', 'metric'];

    public function writeMemory(array $attributes): OfficeMemory
    {
        $brand = $attributes['brand_id'] ?? null;
        if ($brand !== null && ! in_array($brand, self::BRANDS, true)) {
            throw new \InvalidArgumentException('Unknown office brand scope.');
        }
        if (! in_array($attributes['memory_type'] ?? null, self::TYPES, true)) {
            throw new \InvalidArgumentException('Unknown office memory type.');
        }
        if (isset($attributes['confidence']) && ($attributes['confidence'] < 0 || $attributes['confidence'] > 100)) {
            throw new \InvalidArgumentException('Confidence must be between 0 and 100.');
        }
        return OfficeMemory::create(array_merge(['importance' => 50], $attributes));
    }

    /** Brand context is always explicitly scoped to its own brand plus global. */
    public function getRelevantMemories(?string $brandId, ?string $type = null, int $limit = 20): Collection
    {
        $query = OfficeMemory::query()->where(fn ($q) => $brandId === null || $brandId === 'global'
            ? $q->whereNull('brand_id')->orWhere('brand_id', 'global')
            : $q->whereIn('brand_id', [$brandId, 'global'])->orWhereNull('brand_id'))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
        if ($type) $query->where('memory_type', $type);
        return $query->orderByDesc('importance')->latest()->limit(min($limit, 100))->get();
    }

    public function getBrandMemories(?string $brandId, int $limit = 50): Collection { return $this->getRelevantMemories($brandId, null, $limit); }
    public function getRecentInsights(?string $brandId, int $limit = 10): Collection { return $this->getRelevantMemories($brandId, 'insight', $limit); }

    public function getAgentContext(?string $brandId, int $limit = 8): array
    {
        $group = fn (string $type) => $this->getRelevantMemories($brandId, $type, $limit)->map(fn ($m) => $this->compact($m))->values();
        return ['brand' => $brandId ?? 'global', 'recent_research' => $group('research'), 'recent_decisions' => $group('decision'), 'recent_results' => $group('result'), 'recent_metrics' => $group('metric'), 'global_context' => $this->getRelevantMemories('global', null, $limit)->map(fn ($m) => $this->compact($m))->values()];
    }

    private function compact(OfficeMemory $memory): array
    {
        return ['id' => $memory->id, 'type' => $memory->memory_type, 'title' => $memory->title, 'summary' => $memory->summary, 'confidence' => $memory->confidence, 'source_url' => $memory->source_url, 'source_reference' => $memory->source_reference];
    }
}
