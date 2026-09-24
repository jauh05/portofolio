<?php

namespace App\Http\Controllers;

use App\Models\OfficeCommand;
use App\Models\OfficeContentItem;
use App\Models\OfficeEvent;
use App\Models\OfficeNotification;
use App\Models\OfficeTask;
use App\Services\OfficeRegistry;
use App\Services\OfficeStateStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class LivingOfficeController extends Controller
{
    public function __construct(private OfficeStateStore $state) {}

    public function getAgents(): JsonResponse
    {
        return response()->json(array_values($this->state->get()['agents']));
    }

    public function getAgent(string $id): JsonResponse
    {
        $agent = $this->state->get()['agents'][$id] ?? null;
        return $agent ? response()->json($agent) : response()->json(['message' => 'Agent not found.'], 404);
    }

    public function getSystemStatus(): JsonResponse
    {
        return response()->json($this->state->get()['systems']);
    }

    public function getTasks(): JsonResponse
    {
        $tasks = OfficeTask::latest('started_at')->limit(100)->get()->map(fn (OfficeTask $task) => [
            'id' => $task->id, 'agentId' => $task->agent_id, 'title' => $task->title, 'type' => $task->type,
            'status' => $task->status, 'progress' => $task->progress, 'target' => $task->target,
            'startedAt' => $task->started_at?->toIso8601String(),
            'finishedAt' => ($task->completed_at ?? $task->failed_at)?->toIso8601String(),
            'error' => $task->metadata['error'] ?? null,
        ]);
        return response()->json(['data' => $tasks, 'empty' => $tasks->isEmpty()]);
    }

    public function getActivity(): JsonResponse
    {
        $events = OfficeEvent::whereNotIn('event_type', ['system.heartbeat', 'task.progress', 'agent.status.changed'])
            ->latest()->limit(100)->get()->map(fn (OfficeEvent $event) => [
                'id' => $event->id, 'agentId' => $event->agent_id, 'type' => $event->event_type,
                'activity' => $event->activity, 'status' => $event->status, 'progress' => $event->progress,
                'createdAt' => $event->created_at?->toIso8601String(),
            ]);
        return response()->json(['data' => $events, 'empty' => $events->isEmpty()]);
    }

    public function getContent(Request $request): JsonResponse
    {
        $filter = $request->string('filter')->lower()->value();
        $query = OfficeContentItem::latest('generated_at');
        if (in_array($filter, ['article', 'instagram', 'threads'], true)) {
            $query->where('platform', $filter);
        }
        $items = $query->limit(100)->get()->map(fn (OfficeContentItem $item) => [
            'id' => $item->id, 'agentId' => $item->agent_id, 'platform' => $item->platform,
            'contentType' => $item->content_type, 'title' => $item->title, 'text' => $item->text,
            'imageUrl' => $item->image_url, 'externalId' => $item->external_id,
            'publicUrl' => $item->public_url, 'status' => $item->status,
            'generatedAt' => $item->generated_at?->toIso8601String(), 'publishedAt' => $item->published_at?->toIso8601String(),
        ]);
        return response()->json(['data' => $items, 'empty' => $items->isEmpty()]);
    }

    public function getNotifications(): JsonResponse
    {
        $notifications = OfficeNotification::latest()->limit(50)->get()->map(fn (OfficeNotification $item) => [
            'id' => $item->id, 'type' => $item->type, 'agentId' => $item->agent_id,
            'title' => $item->title, 'message' => $item->message, 'severity' => $item->severity,
            'readAt' => $item->read_at?->toIso8601String(), 'createdAt' => $item->created_at?->toIso8601String(),
        ]);
        return response()->json(['data' => $notifications, 'unread' => $notifications->whereNull('readAt')->count()]);
    }

    public function readNotification(OfficeNotification $notification): JsonResponse
    {
        $notification->update(['read_at' => $notification->read_at ?? now()]);
        return response()->json(['status' => 'read']);
    }

    public function readAllNotifications(): JsonResponse
    {
        OfficeNotification::whereNull('read_at')->update(['read_at' => now()]);
        return response()->json(['status' => 'read']);
    }

    public function commandAgent(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate(['action' => ['required', 'string', 'max:80'], 'payload' => ['sometimes', 'array']]);
        if (! isset(OfficeRegistry::COMMANDS[$id]) || ! in_array($validated['action'], OfficeRegistry::COMMANDS[$id], true)) {
            return response()->json(['message' => 'This command is not allowed for the selected agent.'], 422);
        }
        $payload = $validated['payload'] ?? [];
        $allowedKeys = OfficeRegistry::COMMAND_PAYLOAD_KEYS[$validated['action']] ?? [];
        if (array_diff(array_keys($payload), $allowedKeys)) {
            return response()->json(['message' => 'The command payload contains unsupported fields.'], 422);
        }
        if ($validated['action'] === 'schedule_article') {
            $payloadValidator = Validator::make($payload, ['scheduled_at' => ['required', 'date']]);
            if ($payloadValidator->fails()) {
                return response()->json(['message' => 'A valid scheduled_at value is required.', 'errors' => $payloadValidator->errors()], 422);
            }
        }
        $command = OfficeCommand::create([
            'agent_id' => $id, 'action' => $validated['action'], 'payload' => $payload,
            'status' => 'queued', 'requested_by' => $request->user()->id,
        ]);
        return response()->json(['id' => $command->id, 'agentId' => $id, 'action' => $command->action, 'status' => 'queued'], 202);
    }

    public function getCommands(Request $request): JsonResponse
    {
        $query = OfficeCommand::latest();
        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->string('agent_id'));
        }
        return response()->json(['data' => $query->limit(100)->get()->map(fn (OfficeCommand $command) => [
            'id' => $command->id, 'agentId' => $command->agent_id, 'action' => $command->action,
            'status' => $command->status, 'error' => $command->error,
            'createdAt' => $command->created_at?->toIso8601String(), 'completedAt' => $command->completed_at?->toIso8601String(),
        ])]);
    }

    public function summary(): JsonResponse
    {
        $agents = collect($this->state->get()['agents']);
        $today = now()->startOfDay();
        $week = now()->startOfWeek();
        $completed = OfficeTask::where('started_at', '>=', $today)->where('status', 'completed')->count();
        $failed = OfficeTask::where('started_at', '>=', $today)->where('status', 'failed')->count();
        $finished = $completed + $failed;
        $durations = OfficeTask::whereNotNull('completed_at')->get(['started_at', 'completed_at'])->map(fn ($task) => Carbon::parse($task->started_at)->diffInSeconds(Carbon::parse($task->completed_at)));
        return response()->json([
            'workers' => $agents->reject(fn ($agent) => in_array($agent['status'], ['not_connected', 'not_installed'], true))->count(),
            'workingNow' => $agents->whereIn('status', ['working', 'generating', 'planning', 'monitoring', 'analyzing'])->count(),
            'tasksToday' => OfficeTask::where('started_at', '>=', $today)->count(),
            'contentThisWeek' => OfficeContentItem::where('generated_at', '>=', $week)->count(),
            'successRate' => $finished ? round(($completed / $finished) * 100, 1) : null,
            'averageRuntimeSeconds' => $durations->isNotEmpty() ? (int) round($durations->average()) : null,
        ]);
    }
}
