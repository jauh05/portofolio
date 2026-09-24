<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class OfficeStateStore
{
    private const FILE = 'office_state.json';

    public function get(): array
    {
        $state = Storage::exists(self::FILE) ? json_decode(Storage::get(self::FILE), true) : null;
        $changed = false;
        if (! is_array($state)) {
            $state = ['systems' => OfficeRegistry::systems(), 'agents' => OfficeRegistry::agents(), 'tasks' => [], 'activity' => []];
            $changed = true;
        }

        $systems = array_replace(OfficeRegistry::systems(), $state['systems'] ?? []);
        $agents = array_replace(OfficeRegistry::agents(), $state['agents'] ?? []);
        $changed = $changed || $systems !== ($state['systems'] ?? []) || $agents !== ($state['agents'] ?? []);
        $state['systems'] = $systems;
        $state['agents'] = $agents;
        foreach ($state['systems'] as $systemId => &$system) {
            if (($system['status'] ?? null) === 'online' && ! empty($system['lastHeartbeat']) && now()->diffInSeconds(Carbon::parse($system['lastHeartbeat'])) > 90) {
                $system['status'] = 'offline';
                $changed = true;
            }
            if (($system['status'] ?? null) === 'offline') {
                foreach ($state['agents'] as &$agent) {
                    if (($agent['parentSystem'] ?? null) === $systemId && $agent['status'] !== 'offline') {
                        $agent['status'] = 'offline';
                        $agent['currentTask'] = 'System offline';
                        $changed = true;
                    }
                }
                unset($agent);
            }
        }
        unset($system);
        if ($changed) {
            $this->put($state);
        }

        return $state;
    }

    public function put(array $state): void
    {
        Storage::put(self::FILE, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function apply(array $event): void
    {
        $state = $this->get();
        $type = $event['event'];
        if ($type === 'system.heartbeat') {
            $systemId = $event['parent_system'] ?? null;
            if ($systemId && isset($state['systems'][$systemId])) {
                $state['systems'][$systemId] = ['status' => 'online', 'lastHeartbeat' => now()->toIso8601String()];
                foreach ($state['agents'] as &$agent) {
                    if ($agent['parentSystem'] === $systemId && $agent['status'] === 'offline') {
                        $agent['status'] = 'idle';
                        $agent['currentTask'] = 'Waiting for tasks';
                        $agent['progress'] = 0;
                    }
                }
                unset($agent);
            }
            $this->put($state);
            return;
        }

        $agentId = $event['agent_id'] ?? null;
        if (! $agentId || ! isset($state['agents'][$agentId])) {
            return;
        }

        $agent = &$state['agents'][$agentId];
        $systemId = $agent['parentSystem'];
        $state['systems'][$systemId] = ['status' => 'online', 'lastHeartbeat' => now()->toIso8601String()];
        $activity = $event['activity'] ?? $event['task']['title'] ?? $event['result']['title'] ?? null;

        if ($type === 'agent.status.changed') {
            $agent['status'] = $event['status'] ?? $agent['status'];
        } elseif ($type === 'task.started') {
            $agent['status'] = 'working';
            $agent['progress'] = 0;
        } elseif ($type === 'task.progress') {
            $agent['status'] = 'working';
        } elseif ($type === 'task.completed') {
            $agent['status'] = 'completed';
            $agent['progress'] = 100;
            if ($activity) {
                array_unshift($agent['recentResults'], $activity);
                $agent['recentResults'] = array_slice($agent['recentResults'], 0, 3);
            }
        } elseif ($type === 'task.failed') {
            $agent['status'] = 'error';
            $activity = $event['error'] ?? 'Task failed';
        }

        if (isset($event['progress'])) {
            $agent['progress'] = $event['progress'];
        }
        if ($activity) {
            $agent['currentTask'] = $activity;
            $agent['lastActivity'] = $activity.' · now';
        }
        $this->put($state);
    }
}
