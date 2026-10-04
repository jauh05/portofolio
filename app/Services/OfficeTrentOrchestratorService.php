<?php

namespace App\Services;

use App\Models\OfficeAnalystReport;
use App\Models\OfficeContentPlanItem;
use App\Models\OfficeContentSchedule;
use App\Models\OfficeContentItem;
use App\Models\OfficeContentBrand;
use Illuminate\Support\Collection;
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
        $proposal = $this->proposePlan($message, $brand, $context);

        if (empty($proposal['items']) || !is_array($proposal['items'])) {
            return [
                'status' => 'error',
                'summary' => 'Saya tidak bisa membuat rencana konten dari permintaan tersebut.',
                'items' => []
            ];
        }

        // Filter out unsupported platforms (like article)
        $validItems = [];
        $unsupportedCount = 0;
        foreach ($proposal['items'] as $item) {
            if (in_array(strtolower($item['platform'] ?? ''), ['article', 'blog', 'website'])) {
                $unsupportedCount++;
                continue;
            }

            // Provide sensible defaults for timing based on AI suggestion
            $timingStr = $item['generation_timing'] ?? 'H-1';
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

        if (empty($validItems)) {
             return [
                'status' => 'error',
                'summary' => 'Permintaan Anda mengarah ke artikel/blog, tetapi Worker artikel belum tersedia.',
                'items' => []
            ];
        }

        // 4. Create Analyst Report to persist the proposal
        $report = $this->persistProposal($validItems, $brand, $message);

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
            $summary .= "\n(Ada {$unsupportedCount} usulan artikel yang saya lewati karena Worker artikel belum tersedia)\n";
        }

        $summary .= "\nPembuatan: Sesuai usulan per item\nPublikasi: Review\n\nApakah rencana ini dibuat?";

        return [
            'status' => 'needs_confirmation',
            'report_id' => $report->id,
            'summary' => $summary,
            'items' => collect($validItems)->map(function($i) {
                return [
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
            'recent_topics' => $recentContent->pluck('topic')->filter()->values()->all(),
        ];
    }

    private function proposePlan(string $message, OfficeContentBrand $brand, array $context): array
    {
        $brands = collect([$brand]);
        $enrichedMessage = $message . "\n\nAvoid these existing topics: " . implode(', ', $context['active_topics']);
        $enrichedMessage .= "\n\nIf the user doesn't specify time, date, or topics, generate sensible defaults instead of asking for clarification. Start dates should be tomorrow. Pick sensible topics for the brand. Generate daily content if frequency is not specified."
                          . "\nCRITICAL INSTRUCTION: You MUST predict a generation_timing field for each item string exact match: 'H-1' (default), 'H-2', 'now', or 'manual'.";

        if (stripos($message, 'now') !== false || stripos($message, 'sekarang') !== false || stripos($message, 'immediate') !== false) {
            $enrichedMessage .= "\nUser asked for immediate preparation. You MUST set generation_timing = 'now' for all items.";
        }

        $result = $this->plannerAi->analyze($enrichedMessage, $brands);

        return $result['plan'] ?? ['items' => []];
    }

    private function persistProposal(array $items, OfficeContentBrand $brand, string $prompt): OfficeAnalystReport
    {
        $report = OfficeAnalystReport::create([
            'report_date' => now('Asia/Jakarta')->toDateString(),
            'report_type' => 'manual',
            'status' => 'approved', // Auto approve report itself to hold items
            'summary' => 'Proposal based on: ' . $prompt,
            'metadata' => [
                'trent_proposal' => true,
                'prompt' => $prompt
            ]
        ]);

        $batchId = Str::uuid()->toString();

        foreach ($items as $item) {
            OfficeContentPlanItem::create([
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

        return $report;
    }
}