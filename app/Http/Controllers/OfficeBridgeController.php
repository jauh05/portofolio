<?php

namespace App\Http\Controllers;

use App\Models\OfficeCommand;
use App\Services\OfficeEventService;
use App\Services\OfficeRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OfficeBridgeController extends Controller
{
    public function ingest(Request $request, OfficeEventService $events): JsonResponse
    {
        if (! $this->authorized($request)) return response()->json(['message' => 'Unauthorized'], 401);
        $data = $request->validate([
            'event' => ['required', 'string', 'max:100'], 'event_id' => ['nullable', 'string', 'max:191'], 'agent_id' => ['nullable', 'string', 'max:100'],
            'parent_system' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'string', 'max:50'],
            'activity' => ['nullable', 'string', 'max:500'], 'progress' => ['nullable', 'integer', 'between:0,100'],
            'task' => ['nullable', 'array'], 'result' => ['nullable', 'array'], 'content' => ['nullable', 'array'],
            'error' => ['nullable', 'string', 'max:2000'],
        ]);
        if (! empty($data['agent_id']) && ! array_key_exists($data['agent_id'], OfficeRegistry::agents())) {
            return response()->json(['message' => 'Unknown agent_id.'], 422);
        }
        if ((str_starts_with($data['event'], 'task.') || str_starts_with($data['event'], 'content.')) && empty($data['agent_id'])) {
            return response()->json(['message' => 'agent_id is required for task and content events.'], 422);
        }
        $events->ingest($data);
        return response()->json(['status' => 'ok']);
    }

    public function claim(Request $request): JsonResponse
    {
        if (! $this->authorized($request)) return response()->json(['message' => 'Unauthorized'], 401);
        $request->validate(['agent_id' => ['nullable', 'string', Rule::in(array_keys(OfficeRegistry::COMMANDS))]]);
        $command = DB::transaction(function () use ($request) {
            $query = OfficeCommand::where('status', 'queued')->oldest()->lockForUpdate();
            if ($request->filled('agent_id')) $query->where('agent_id', $request->string('agent_id'));
            $command = $query->first();
            if ($command) {
                $claimed = OfficeCommand::whereKey($command->id)->where('status', 'queued')->update(['status' => 'claimed', 'claimed_at' => now(), 'updated_at' => now()]);
                if ($claimed !== 1) return null;
                $command->refresh();
            }
            return $command;
        });
        return response()->json(['command' => $command ? [
            'id' => $command->id, 'agent_id' => $command->agent_id, 'action' => $command->action,
            'payload' => $command->payload ?? [], 'status' => $command->status,
        ] : null]);
    }

    public function updateCommand(Request $request, OfficeCommand $command): JsonResponse
    {
        if (! $this->authorized($request)) return response()->json(['message' => 'Unauthorized'], 401);
        $data = $request->validate(['status' => ['required', Rule::in(['running', 'completed', 'failed'])], 'error' => ['nullable', 'string', 'max:2000']]);
        $allowedTransitions = ['claimed' => ['running'], 'running' => ['completed', 'failed']];
        if (! in_array($data['status'], $allowedTransitions[$command->status] ?? [], true)) return response()->json(['message' => 'Invalid command status transition.'], 409);
        $command->update([
            'status' => $data['status'], 'error' => $data['status'] === 'failed' ? ($data['error'] ?? 'Unknown error') : null,
            'completed_at' => in_array($data['status'], ['completed', 'failed'], true) ? now() : null,
        ]);
        return response()->json(['id' => $command->id, 'status' => $command->status]);
    }

    private function authorized(Request $request): bool
    {
        $token = (string) config('services.office_bridge.token');
        return $token !== '' && hash_equals('Bearer '.$token, (string) $request->header('Authorization'));
    }
}
