<?php

namespace App\Services;

use App\Jobs\SendTelegramNotification;
use App\Models\OfficeCommand;
use App\Models\OfficeContentItem;
use App\Models\OfficeNotification;
use App\Models\OfficeResearchRun;
use App\Models\OfficeTask;
use App\Services\OfficeRegistry;
use Illuminate\Support\Facades\Cache;

class OfficeTelegramNotifier
{
    public function handleUserAction(OfficeCommand $command)
    {
        if (!$this->shouldNotify('all')) return;

        $target = OfficeRegistry::displayName($command->agent_id) ?? $command->agent_id ?? 'System';
        $action = $this->formatAction($command->action);

        $message = "👤 <b>Office Action</b>\n\n";
        $message .= "Action: <b>{$action}</b>\n";
        $message .= "Target: {$target}\n";
        $message .= "Status: ✅ Queued\n";

        $this->dispatch($message, "cmd_{$command->id}");
    }

    public function handleCommandStatus(OfficeCommand $command)
    {
        if (!$this->shouldNotify('all')) return;

        // Command updates mapped to worker actions
        $statusIcons = [
            'claimed' => '👀',
            'running' => '▶️',
            'completed' => '✅',
            'failed' => '❌',
        ];
        $icon = $statusIcons[$command->status] ?? '⚙️';
        $target = OfficeRegistry::displayName($command->agent_id) ?? $command->agent_id ?? 'System';
        $action = $this->formatAction($command->action);
        $status = ucfirst($command->status);

        $message = "🤖 <b>{$target}</b>\n\n";
        $message .= "Task: {$action}\n";
        $message .= "Status: {$icon} {$status}\n";

        if ($command->status === 'failed' && $command->error) {
            $message .= "\nError:\n" . substr($command->error, 0, 200) . (strlen($command->error) > 200 ? '...' : '');
        }

        $this->dispatch($message, "cmd_stat_{$command->id}_{$command->status}");
    }

    public function handleTask(OfficeTask $task)
    {
        if (!$this->shouldNotify('all') && !in_array($task->status, ['completed', 'failed'])) return;

        $statusIcons = [
            'running' => '▶️',
            'completed' => '✅',
            'failed' => '❌',
        ];
        $icon = $statusIcons[$task->status] ?? '⚙️';
        $target = OfficeRegistry::displayName($task->agent_id) ?? $task->agent_id;
        $title = $task->title ?? 'Untitled Task';
        $status = ucfirst($task->status);

        $message = "🤖 <b>{$target}</b>\n\n";
        $message .= "Task: {$title}\n";
        $message .= "Status: {$icon} {$status}\n";

        if ($task->status === 'completed' && !empty($task->metadata['result']['summary'])) {
            $message .= "Result:\n" . $task->metadata['result']['summary'] . "\n";
        } elseif ($task->status === 'failed' && !empty($task->metadata['error'])) {
            $message .= "Error:\n" . substr($task->metadata['error'], 0, 200) . "\n";
        }

        $this->dispatch($message, "task_stat_{$task->id}_{$task->status}");
    }

    public function handleContent(OfficeContentItem $item)
    {
        $importantStatuses = ['ready_for_review', 'published', 'failed'];
        if (!$this->shouldNotify('all') && !in_array($item->status, $importantStatuses)) return;

        $statusIcons = [
            'planned' => '📝',
            'proposed' => '📝',
            'generating' => '🔄',
            'ready_for_review' => '👀',
            'approved' => '👍',
            'published' => '🚀',
            'failed' => '❌'
        ];
        $icon = $statusIcons[$item->status] ?? '📝';
        $target = OfficeRegistry::displayName($item->agent_id) ?? $item->agent_id;
        $status = str_replace('_', ' ', ucfirst($item->status));

        $message = "{$icon} <b>Content {$status}</b>\n\n";
        $message .= "Worker: {$target}\n";
        $message .= "Content: " . ($item->title ?? 'Untitled') . "\n";
        $message .= "Platform: " . ucfirst($item->platform ?? 'unknown') . "\n";

        if ($item->status === 'published' && $item->public_url) {
            $message .= "\n🔗 <a href=\"{$item->public_url}\">View Post</a>\n";
        }

        $appUrl = config('app.url');
        if ($appUrl && in_array($item->status, ['ready_for_review', 'published'])) {
            $message .= "\n<a href=\"{$appUrl}/living-office\">Open Living AI Office</a>";
        }

        $this->dispatch($message, "content_{$item->id}_{$item->status}");
    }

    public function handleResearch(OfficeResearchRun $run)
    {
        if (!$this->shouldNotify('all') && !in_array($run->status, ['completed', 'failed'])) return;

        $statusIcons = [
            'running' => '▶️',
            'completed' => '✅',
            'failed' => '❌',
        ];
        $icon = $statusIcons[$run->status] ?? '🔬';

        $message = "🔬 <b>Analyst Research</b>\n\n";
        $message .= "Topic: " . ($run->topic ?? 'Unknown Topic') . "\n";
        $message .= "Status: {$icon} " . ucfirst($run->status) . "\n";

        $this->dispatch($message, "research_{$run->id}_{$run->status}");
    }

    public function handleError(OfficeNotification $notification)
    {
        if (!$this->shouldNotify('errors')) return;

        $target = OfficeRegistry::displayName($notification->agent_id) ?? $notification->agent_id ?? 'System';
        $message = "❌ <b>Error: {$target}</b>\n\n";
        $message .= "Type: {$notification->type}\n";
        $message .= "Message:\n" . substr($notification->message, 0, 300) . "\n";

        $this->dispatch($message, "error_{$notification->id}");
    }

    private function shouldNotify(string $level): bool
    {
        if (!config('services.telegram.enabled')) return false;

        $configLevel = config('services.telegram.level', 'all');
        if ($configLevel === 'all') return true;

        if ($configLevel === 'important') {
            return in_array($level, ['important', 'errors']);
        }

        if ($configLevel === 'errors') {
            return $level === 'errors';
        }

        return false;
    }

    private function dispatch(string $message, string $dedupKey)
    {
        // Deduplication for 5 minutes
        $cacheKey = "tg_notify_{$dedupKey}";
        if (Cache::has($cacheKey)) {
            return;
        }
        Cache::put($cacheKey, true, now()->addMinutes(5));

        SendTelegramNotification::dispatch($message);
    }

    private function formatAction(string $action): string
    {
        return str($action)->replace('_', ' ')->title();
    }
}
