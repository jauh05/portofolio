<?php

namespace App\Services;

use App\Models\OfficeContentItem;
use App\Models\OfficeEvent;
use App\Models\OfficeNotification;
use App\Models\OfficeTask;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class OfficeEventService
{
    public function __construct(private OfficeStateStore $state) {}

    public function ingest(array $data): void
    {
        $this->state->apply($data);
        if ($data['event'] === 'system.heartbeat') {
            return;
        }

        DB::transaction(function () use ($data) {
            $event = isset($data['event_id'])
                ? OfficeEvent::firstOrCreate(
                    ['source_event_id' => $data['event_id']],
                    ['agent_id' => $data['agent_id'] ?? null, 'event_type' => $data['event'], 'activity' => $this->activity($data), 'status' => $data['status'] ?? null, 'progress' => $data['progress'] ?? null, 'payload' => Arr::except($data, ['event', 'event_id', 'agent_id', 'status', 'progress']), 'created_at' => now()]
                )
                : null;
            if ($event && ! $event->wasRecentlyCreated) {
                return;
            }
            $task = $this->updateTask($data);
            $event ??= OfficeEvent::create([
                'source_event_id' => null,
                'agent_id' => $data['agent_id'] ?? null,
                'event_type' => $data['event'],
                'activity' => $this->activity($data),
                'status' => $data['status'] ?? $task?->status,
                'progress' => $data['progress'] ?? $task?->progress,
                'payload' => Arr::except($data, ['event', 'agent_id', 'status', 'progress']),
                'created_at' => now(),
            ]);
            $event->update(['status' => $data['status'] ?? $task?->status, 'progress' => $data['progress'] ?? $task?->progress]);
            $content = $this->updateContent($data);
            $this->notify($data, $task, $content);
        });
    }

    private function updateTask(array $data): ?OfficeTask
    {
        $type = $data['event'];
        $agentId = $data['agent_id'] ?? null;
        if (! $agentId || ! str_starts_with($type, 'task.')) {
            return null;
        }

        if ($type === 'task.started') {
            $externalId = $data['task']['id'] ?? null;
            if ($externalId) {
                $existing = OfficeTask::where('agent_id', $agentId)->where('external_id', $externalId)->first();
                if ($existing) return $existing;
            }
            return OfficeTask::create([
                'agent_id' => $agentId,
                'external_id' => $externalId,
                'title' => $data['task']['title'] ?? 'Untitled task',
                'type' => $data['task']['type'] ?? 'task',
                'status' => 'running', 'progress' => 0,
                'target' => $data['task']['target'] ?? null,
                'metadata' => $data['task']['metadata'] ?? null,
                'started_at' => now(),
            ]);
        }

        if (array_key_exists('task_id', $data) && $data['task_id'] !== null && $data['task_id'] !== '') {
            $task = OfficeTask::where('agent_id', $agentId)->where('external_id', $data['task_id'])->first();
        } else {
            $task = OfficeTask::where('agent_id', $agentId)->whereIn('status', ['running', 'queued'])->latest('started_at')->first();
            if (! $task && in_array($type, ['task.completed', 'task.failed'], true)) {
                $finalStatus = $type === 'task.completed' ? 'completed' : 'failed';
                $task = OfficeTask::where('agent_id', $agentId)->where('status', $finalStatus)->latest($type === 'task.completed' ? 'completed_at' : 'failed_at')->first();
            }
        }
        if (! $task) {
            return null;
        }
        if ($type === 'task.progress') {
            $task->update(['progress' => $data['progress'] ?? $task->progress]);
        } elseif ($type === 'task.completed') {
            $task->update(['status' => 'completed', 'progress' => 100, 'completed_at' => now(), 'metadata' => array_merge($task->metadata ?? [], ['result' => $data['result'] ?? null])]);
        } elseif ($type === 'task.failed') {
            $task->update(['status' => 'failed', 'failed_at' => now(), 'metadata' => array_merge($task->metadata ?? [], ['error' => $data['error'] ?? 'Unknown error'])]);
        }
        return $task->fresh();
    }

    private function updateContent(array $data): ?OfficeContentItem
    {
        if (! in_array($data['event'], ['content.generated', 'content.preview_ready', 'content.published'], true)) {
            return null;
        }
        $content = $data['content'] ?? $data['result'] ?? [];
        $platform = $content['platform'] ?? $this->platform($data['agent_id']);
        $contentType = $content['content_type'] ?? $content['type'] ?? 'post';
        $externalId = $content['external_id'] ?? $content['media_id'] ?? null;
        $deduplicationKey = hash('sha256', implode('|', [
            $data['agent_id'], $platform, $contentType, $externalId ?? '',
            $content['title'] ?? '', $content['text'] ?? $content['caption'] ?? '', $content['image_url'] ?? '',
        ]));
        $item = $externalId
            ? OfficeContentItem::where('platform', $platform)->where('external_id', $externalId)->first()
            : OfficeContentItem::where('deduplication_key', $deduplicationKey)->first();
        if (! $item && $data['event'] === 'content.published' && ! empty($content['title'])) {
            $item = OfficeContentItem::where('agent_id', $data['agent_id'])->where('platform', $platform)->where('content_type', $contentType)->where('title', $content['title'])->latest('generated_at')->first();
        }
        $item ??= new OfficeContentItem;
        $published = $data['event'] === 'content.published';
        $item->fill([
            'deduplication_key' => $item->deduplication_key ?? $deduplicationKey,
            'agent_id' => $data['agent_id'],
            'platform' => $platform,
            'content_type' => $contentType,
            'title' => $content['title'] ?? $item->title,
            'text' => $content['text'] ?? $content['caption'] ?? $item->text,
            'image_url' => $content['image_url'] ?? $item->image_url,
            'external_id' => $externalId ?? $item->external_id,
            'public_url' => $content['public_url'] ?? $content['url'] ?? $item->public_url,
            'status' => $published ? 'published' : ($data['event'] === 'content.preview_ready' ? 'preview_ready' : 'generated'),
            'metadata' => array_merge($item->metadata ?? [], $content['metadata'] ?? []),
            'generated_at' => $item->generated_at ?? now(),
            'published_at' => $published ? now() : $item->published_at,
        ])->save();
        return $item->fresh();
    }

    private function notify(array $data, ?OfficeTask $task, ?OfficeContentItem $content): void
    {
        $type = $data['event'];
        if (! in_array($type, ['task.completed', 'task.failed', 'content.preview_ready', 'content.published', 'server.warning', 'server.critical'], true)) {
            return;
        }
        $failed = in_array($type, ['task.failed', 'server.critical'], true);
        $warning = $type === 'server.warning';
        $entityId = $task?->id ?? $content?->id ?? ($data['event_id'] ?? null);
        $attributes = [
            'type' => $type,
            'agent_id' => $data['agent_id'] ?? null,
            'title' => OfficeRegistry::displayName($data['agent_id'] ?? null),
            'message' => $data['error'] ?? $data['activity'] ?? $data['result']['title'] ?? $data['content']['title'] ?? str($type)->replace('.', ' ')->headline(),
            'severity' => $failed ? 'error' : ($warning ? 'warning' : 'success'),
            'data' => array_merge(Arr::except($data, ['event']), array_filter(['entity_id' => $entityId])),
            'created_at' => now(),
        ];
        if ($entityId) {
            OfficeNotification::firstOrCreate(
                ['deduplication_key' => hash('sha256', $type.'|'.$entityId)],
                $attributes,
            );
            return;
        }
        OfficeNotification::create($attributes);
    }

    private function activity(array $data): string
    {
        return $data['activity'] ?? $data['task']['title'] ?? $data['result']['title'] ?? $data['content']['title'] ?? $data['error'] ?? str($data['event'])->replace('.', ' ')->headline();
    }

    private function platform(string $agentId): string
    {
        return ['jauki-social' => 'instagram', 'jauki-threads' => 'threads', 'jauki-article' => 'article'][$agentId] ?? 'other';
    }
}
