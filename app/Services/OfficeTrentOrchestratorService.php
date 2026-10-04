<?php

namespace App\Services;

use App\Models\OfficeAnalystReport;
use App\Models\OfficeContentPlanItem;
use App\Models\OfficeContentSchedule;
use App\Models\OfficeContentItem;
use App\Models\OfficeContentBrand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\CarbonImmutable;

class OfficeTrentOrchestratorService
{
    public function __construct(
        private OfficeAiGateway $ai,
        private OfficeContentPlannerAiService $plannerAi,
        private OfficeAnalystReportService $analyst
    ) {}

    public function processIntent(string $message, string $workspaceId): array
    {
        $brand = OfficeContentBrand::find($workspaceId);
        if (!$brand) {
            return ['status' => 'error', 'message' => 'Workspace tidak ditemukan.'];
        }

        // 1. Detect Intent and Ambiguity
        $intent = $this->analyzeIntent($message);

        if ($intent['is_ambiguous']) {
            return [
                'status' => 'ambiguous',
                'summary' => $intent['clarification_question'],
                'items' => []
            ];
        }

        // 2. Load Context (Existing schedules, recent content, analyst report)
        $context = $this->gatherContext($brand);

        // 3. Propose Plan
        $analysis = $this->proposePlan($message, $brand, $context);
        if (!empty($analysis['clarifications'])) {
            return ['status' => 'ambiguous', 'summary' => implode(' ', $analysis['clarifications']), 'items' => []];
        }
        $proposal = $analysis['plan'] ?? ['items' => []];

        if (empty($proposal['items']) || !is_array($proposal['items'])) {
            return [
                'status' => 'error',
                'summary' => 'Saya tidak bisa membuat rencana konten dari permintaan tersebut.',
                'items' => []
            ];
        }

        $validItems = [];
        $unsupportedCount = 0;
        $duplicateCount = 0;
        $unsupportedRecurring = false;
        $seen = [];
        foreach ($proposal['items'] as $item) {
            $platform = strtolower((string) ($item['platform'] ?? ''));
            $type = strtolower((string) ($item['content_type'] ?? ''));
            if (!in_array($platform . ':' . $type, ['instagram:feed', 'instagram:story', 'threads:thread', 'threads:threads'], true)) {
                $unsupportedCount++;
                continue;
            }
            if (($item['schedule_type'] ?? '') !== 'one_time' || empty($item['scheduled_at'])) {
                $unsupportedRecurring = true;
                continue;
            }
            $signature = $platform.':'.$type.':'.mb_strtolower(trim((string) ($item['topic'] ?? ''))).':'.substr((string) $item['scheduled_at'], 0, 10);
            if (isset($seen[$signature]) || $this->isDuplicate($item, $context)) {
                $duplicateCount++;
                continue;
            }
            $seen[$signature] = true;

            $timingStr = $this->timingFor($item, $message);
            if ($timingStr === 'H-1') {
                 $item['generation_timing_enum'] = 'lead_time';
                 $item['generation_lead_minutes'] = 1440;
                 $item['generation_mode'] = 'automatic';
            } elseif ($timingStr === 'H-2') {
                 $item['generation_timing_enum'] = 'lead_time';
                 $item['generation_lead_minutes'] = 2880;
                 $item['generation_mode'] = 'automatic';
            } elseif ($timingStr === 'now') {
                 $item['generation_timing_enum'] = 'immediate';
                 $item['generation_lead_minutes'] = null;
                 $item['generation_mode'] = 'automatic';
            } else {
                 $item['generation_timing_enum'] = 'manual';
                 $item['generation_lead_minutes'] = null;
                 $item['generation_mode'] = 'manual';
                 $timingStr = 'manual';
            }
            $item['generation_timing_label'] = $timingStr;
            $validItems[] = $item;
        }

        if ($unsupportedRecurring) return ['status' => 'ambiguous', 'summary' => 'Untuk rencana berulang, tentukan tanggal dan waktu konten satu kali terlebih dahulu.', 'items' => []];
        if (empty($validItems)) {
            $message = $duplicateCount ? 'Semua usulan sudah ada di jadwal aktif. Ubah topik atau tanggalnya.' : 'Format konten belum didukung. Worker artikel belum tersedia; gunakan Instagram Feed, Story, atau Threads.';
            return ['status' => 'error', 'summary' => $message, 'items' => []];
        }

        // 4. Create Analyst Report to persist the proposal
        [$report, $planItems] = $this->persistProposal($validItems, $brand, $message);

        $summary = "Saya menemukan {$context['active_schedules_count']} jadwal aktif.\n\nUsulan konten:\n";

        $dayMap = [
            'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'
        ];

        foreach ($validItems as $item) {
            $dt = CarbonImmutable::parse($item['scheduled_at'])->timezone('Asia/Jakarta');
            $dayName = $dayMap[$dt->format('l')] ?? $dt->format('l');

            $summary .= "\n{$dayName}\n";
            $platform = ucfirst($item['platform']);
            if (strtolower($item['platform']) === 'instagram') {
                $platform .= ' ' . ucfirst($item['content_type']);
            }
            $summary .= "{$item['topic']}\n";
        }

        if ($unsupportedCount > 0) {
            $summary .= "\n({$unsupportedCount} usulan format yang belum didukung dilewati)\n";
        }
        if ($duplicateCount > 0) $summary .= "\n({$duplicateCount} usulan duplikat dilewati)\n";

        $summary .= "\nPembuatan: Sesuai usulan per item\nPublikasi: Review\n\nApakah rencana ini dibuat?";

        return [
            'status' => 'needs_confirmation',
            'report_id' => $report->id,
            'summary' => $summary,
            'items' => collect($validItems)->map(function($i, $index) use ($planItems) {
                return [
                    'plan_id' => $planItems[$index]->id,
                    'topic' => $i['topic'],
                    'platform' => $i['platform'],
                    'content_type' => $i['content_type'],
                    'scheduled_at' => $i['scheduled_at'],
                    'generation_timing' => $i['generation_timing_enum'],
                    'generation_timing_label' => $i['generation_timing_label'],
                    'publishing_mode' => 'review'
                ];
            })->all()
        ];
    }

    private function analyzeIntent(string $message): array
    {
        $system = "You are Trent, an AI orchestrator assistant. Analyze the user's intent about content planning.\n"
                . "Determine if the request is ambiguous. For example, 'Aku ingin konten seminggu sekali' is ambiguous because it could mean 'one content per week' or 'plan content for the next week'.\n"
                . "Return JSON: {\"is_ambiguous\": boolean, \"clarification_question\": string|null, \"intent\": string}.\n"
                . "If ambiguous, provide a clarification question in Bahasa Indonesia. Like: 'Maksud Anda satu konten setiap minggu, atau rencana beberapa konten untuk satu minggu ke depan?'.";

        try {
            $response = $this->ai->completeJson($system, $message);
            return [
                'is_ambiguous' => $response['is_ambiguous'] ?? false,
                'clarification_question' => $response['clarification_question'] ?? null,
                'intent' => $response['intent'] ?? 'plan_content'
            ];
        } catch (\Exception $e) {
             return ['is_ambiguous' => false, 'clarification_question' => null, 'intent' => 'plan_content'];
        }
    }

    private function gatherContext(OfficeContentBrand $brand): array
    {
        $activeSchedules = OfficeContentSchedule::where('brand_id', $brand->id)
            ->where('is_active', true)
            ->get();

        $recentContent = OfficeContentItem::where('brand_id', $brand->id)
            ->latest('created_at')
            ->limit(10)
            ->get();

        return [
            'active_schedules_count' => $activeSchedules->count(),
            'active_topics' => $activeSchedules->pluck('topic')->filter()->values()->all(),
            'recent_topics' => $recentContent->pluck('title')->filter()->values()->all(),
            'active_schedules' => $activeSchedules->map(fn ($schedule) => [
                'platform' => $schedule->platform, 'content_type' => $schedule->content_type, 'schedule_type' => $schedule->schedule_type,
                'topic' => $schedule->topic, 'date' => $schedule->scheduled_at?->toDateString(),
            ])->all(),
        ];
    }

    private function proposePlan(string $message, OfficeContentBrand $brand, array $context): array
    {
        $brands = collect([$brand]);
        $enrichedMessage = $message . "\n\nAvoid these existing topics: " . implode(', ', array_merge($context['active_topics'], $context['recent_topics']));
        $enrichedMessage .= "\n\nIf the user doesn't specify time, date, or topics, generate sensible defaults instead of asking for clarification. Start dates should be tomorrow. Pick sensible topics for the brand. Generate daily content if frequency is not specified."
                          . "\nCRITICAL INSTRUCTION: You MUST predict a generation_timing field for each item string exact match: 'H-1' (default), 'H-2', 'now', or 'manual'.";

        if (stripos($message, 'now') !== false || stripos($message, 'sekarang') !== false || stripos($message, 'immediate') !== false) {
            $enrichedMessage .= "\nUser asked for immediate preparation. You MUST set generation_timing = 'now' for all items.";
        }

        $result = $this->plannerAi->analyze($enrichedMessage, $brands);

        return $result;
    }

    private function timingFor(array $item, string $message): string
    {
        if (preg_match('/\b(manual|h-?2|h-?1|now|immediate|sekarang)\b/i', $message, $matches)) {
            return match (strtolower($matches[1])) {
                'h-2', 'h2' => 'H-2', 'h-1', 'h1' => 'H-1', 'now', 'immediate', 'sekarang' => 'now', default => 'manual',
            };
        }
        return match (strtolower(trim((string) ($item['generation_timing'] ?? 'h-1')))) {
            'h-2', 'h2' => 'H-2', 'now', 'immediate' => 'now', 'manual' => 'manual', default => 'H-1',
        };
    }

    private function isDuplicate(array $item, array $context): bool
    {
        $topic = mb_strtolower(trim((string) ($item['topic'] ?? '')));
        $date = substr((string) $item['scheduled_at'], 0, 10);
        if ($topic === '') return false;
        foreach ($context['active_schedules'] as $existing) {
            if (mb_strtolower(trim((string) $existing['topic'])) === $topic && $existing['platform'] === $item['platform']
                && $existing['content_type'] === $item['content_type'] && ($existing['date'] === $date || $existing['schedule_type'] === 'recurring')) return true;
        }
        return false;
    }

    private function persistProposal(array $items, OfficeContentBrand $brand, string $prompt): array
    {
        return DB::transaction(function () use ($items, $brand, $prompt) {
        $report = OfficeAnalystReport::create([
            'report_date' => now('Asia/Jakarta')->toDateString(),
            'report_type' => 'manual',
            'status' => 'draft',
            'summary' => 'Proposal based on: ' . $prompt,
            'metadata' => [
                'trent_proposal' => true,
                'prompt' => $prompt
            ]
        ]);

        $batchId = Str::uuid()->toString();

        $planItems = [];
        foreach ($items as $item) {
            $planItems[] = OfficeContentPlanItem::create([
                'brand_id' => $brand->id,
                'platform' => $item['platform'],
                'content_type' => $item['content_type'],
                'topic' => $item['topic'] ?? 'Konten',
                'scheduled_at' => $item['scheduled_at'],
                'generation_mode' => $item['generation_mode'],
                'publishing_mode' => 'review',
                'status' => 'proposed',
                'metadata' => [
                    'source' => 'trent',
                    'analyst_report_id' => $report->id,
                    'batch_id' => $batchId,
                    'generation_timing' => $item['generation_timing_enum'],
                    'generation_lead_minutes' => $item['generation_lead_minutes']
                ]
            ]);
        }

        // Let the controller know about it via metadata, we don't have research run
        $report->metadata = array_merge($report->metadata ?? [], ['has_trent_items' => true]);
        $report->save();

        return [$report, $planItems];
        });
    }
}
