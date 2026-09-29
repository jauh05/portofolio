<?php
$file = 'app/Http/Controllers/OfficeContentPlannerController.php';
$content = file_get_contents($file);

$dispatchPublishing = <<<'PHP'
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
            ]);
        }
    }
PHP;

$content = str_replace(
    "class OfficeContentPlannerController extends Controller\n{",
    "class OfficeContentPlannerController extends Controller\n{\n" . $dispatchPublishing,
    $content
);
file_put_contents($file, $content);
