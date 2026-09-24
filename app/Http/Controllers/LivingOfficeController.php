<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class LivingOfficeController extends Controller
{
    private $stateFile = 'office_state.json';

    private function getDefaultState()
    {
        return [
            'systems' => [
                'trent' => ['status' => 'not_connected', 'lastHeartbeat' => null],
                'jauki-content-bot' => ['status' => 'online', 'lastHeartbeat' => now()->toIso8601String()],
                'future-data-agent' => ['status' => 'not_installed', 'lastHeartbeat' => null],
                'future-finance-agent' => ['status' => 'integration_pending', 'lastHeartbeat' => null],
            ],
            'agents' => [
                'trent' => [
                    'id' => 'trent',
                    'parentSystem' => 'trent',
                    'status' => 'not_connected',
                    'currentTask' => 'Awaiting connection...',
                    'progress' => 0,
                    'lastActivity' => 'System offline',
                    'recentResults' => []
                ],
                'jauki-social' => [
                    'id' => 'jauki-social',
                    'parentSystem' => 'jauki-content-bot',
                    'status' => 'idle',
                    'currentTask' => 'Waiting for tasks',
                    'progress' => 0,
                    'lastActivity' => 'Idle',
                    'recentResults' => []
                ],
                'jauki-threads' => [
                    'id' => 'jauki-threads',
                    'parentSystem' => 'jauki-content-bot',
                    'status' => 'idle',
                    'currentTask' => 'Waiting for tasks',
                    'progress' => 0,
                    'lastActivity' => 'Idle',
                    'recentResults' => []
                ],
                'jauki-article' => [
                    'id' => 'jauki-article',
                    'parentSystem' => 'jauki-content-bot',
                    'status' => 'idle',
                    'currentTask' => 'Waiting for tasks',
                    'progress' => 0,
                    'lastActivity' => 'Idle',
                    'recentResults' => []
                ],
                'jauki-planner' => [
                    'id' => 'jauki-planner',
                    'parentSystem' => 'jauki-content-bot',
                    'status' => 'idle',
                    'currentTask' => 'Waiting for tasks',
                    'progress' => 0,
                    'lastActivity' => 'Idle',
                    'recentResults' => []
                ],
                'data-analyst' => [
                    'id' => 'data-analyst',
                    'parentSystem' => 'future-data-agent',
                    'status' => 'not_installed',
                    'currentTask' => 'Not Installed',
                    'progress' => 0,
                    'lastActivity' => 'No adapter connected',
                    'recentResults' => []
                ],
                'finance-analyst' => [
                    'id' => 'finance-analyst',
                    'parentSystem' => 'future-finance-agent',
                    'status' => 'not_installed',
                    'currentTask' => 'Integration Pending',
                    'progress' => 0,
                    'lastActivity' => 'No adapter connected',
                    'recentResults' => []
                ]
            ],
            'tasks' => [],
            'activity' => []
        ];
    }

    private function getState()
    {
        if (!Storage::exists($this->stateFile)) {
            $default = $this->getDefaultState();
            $this->saveState($default);
            return $default;
        }

        $content = Storage::get($this->stateFile);
        $state = json_decode($content, true);
        
        // Simple heartbeat check for JaukiContentBot (offline if > 60s)
        $now = now();
        $updated = false;
        foreach ($state['systems'] as $sysId => &$sys) {
            if ($sys['status'] === 'online' && $sys['lastHeartbeat']) {
                $last = \Carbon\Carbon::parse($sys['lastHeartbeat']);
                if ($now->diffInSeconds($last) > 60) {
                    $sys['status'] = 'offline';
                    $updated = true;
                    // set agents of this system to offline
                    foreach ($state['agents'] as &$ag) {
                        if ($ag['parentSystem'] === $sysId) {
                            $ag['status'] = 'offline';
                            $ag['currentTask'] = 'System offline';
                        }
                    }
                }
            }
        }
        
        if ($updated) {
            $this->saveState($state);
        }
        
        return $state;
    }

    private function saveState($state)
    {
        Storage::put($this->stateFile, json_encode($state, JSON_PRETTY_PRINT));
    }

    public function getAgents()
    {
        $state = $this->getState();
        return response()->json(array_values($state['agents']));
    }

    public function getAgent($id)
    {
        $state = $this->getState();
        if (!isset($state['agents'][$id])) {
            return response()->json(['error' => 'Agent not found'], 404);
        }
        return response()->json($state['agents'][$id]);
    }

    public function getTasks()
    {
        $state = $this->getState();
        return response()->json($state['tasks'] ?? []);
    }

    public function getActivity()
    {
        $state = $this->getState();
        return response()->json($state['activity'] ?? []);
    }

    public function getSystemStatus()
    {
        $state = $this->getState();
        return response()->json($state['systems']);
    }

    public function ingestEvent(Request $request)
    {
        // Simple token check (middleware is also an option, but inline is fine for MVP)
        $token = env('OFFICE_BRIDGE_TOKEN');
        $auth = $request->header('Authorization');
        
        if (!$token || $auth !== "Bearer {$token}") {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'event' => 'required|string',
            'agent_id' => 'nullable|string',
            'parent_system' => 'nullable|string',
            'status' => 'nullable|string',
            'activity' => 'nullable|string',
            'progress' => 'nullable|integer|min:0|max:100',
            'task' => 'nullable|array',
            'result' => 'nullable|array',
            'error' => 'nullable|string'
        ]);

        $state = $this->getState();
        $event = $validated['event'];
        
        // Handle heartbeat
        if ($event === 'system.heartbeat') {
            $sysId = $validated['parent_system'] ?? null;
            if ($sysId && isset($state['systems'][$sysId])) {
                $state['systems'][$sysId]['status'] = 'online';
                $state['systems'][$sysId]['lastHeartbeat'] = now()->toIso8601String();
                
                // If system comes online, agents move from offline to idle
                foreach ($state['agents'] as &$ag) {
                    if ($ag['parentSystem'] === $sysId && $ag['status'] === 'offline') {
                        $ag['status'] = 'idle';
                        $ag['currentTask'] = 'Waiting for tasks';
                        $ag['progress'] = 0;
                    }
                }
            }
        } else {
            // Handle agent events
            $agentId = $validated['agent_id'] ?? null;
            if ($agentId && !isset($state['agents'][$agentId])) {
                Log::warning("Unknown agent_id received: {$agentId}");
                return response()->json(['error' => 'Unknown agent_id'], 400);
            }
            
            if ($agentId) {
                $agent = &$state['agents'][$agentId];
                
                // Update system heartbeat implicitly
                $sysId = $agent['parentSystem'];
                if (isset($state['systems'][$sysId])) {
                    $state['systems'][$sysId]['status'] = 'online';
                    $state['systems'][$sysId]['lastHeartbeat'] = now()->toIso8601String();
                }

                if ($event === 'agent.status.changed') {
                    $agent['status'] = $validated['status'] ?? $agent['status'];
                    if (isset($validated['activity'])) {
                        $agent['currentTask'] = $validated['activity'];
                        $agent['lastActivity'] = $validated['activity'] . ' · now';
                    }
                    if (isset($validated['progress'])) {
                        $agent['progress'] = $validated['progress'];
                    }
                } elseif ($event === 'task.started') {
                    $agent['status'] = 'working';
                    $agent['progress'] = 0;
                    if (isset($validated['task']['title'])) {
                        $agent['currentTask'] = $validated['task']['title'];
                        $agent['lastActivity'] = 'Started: ' . $validated['task']['title'] . ' · now';
                    }
                    
                    // Add to tasks
                    $taskId = $validated['task']['id'] ?? uniqid();
                    $state['tasks'][] = [
                        'id' => $taskId,
                        'agentId' => $agentId,
                        'parentSystem' => $agent['parentSystem'],
                        'title' => $validated['task']['title'] ?? 'Unknown Task',
                        'status' => 'running',
                        'progress' => 0,
                        'startedAt' => now()->toIso8601String(),
                        'finishedAt' => null
                    ];
                } elseif ($event === 'task.progress') {
                    if (isset($validated['progress'])) {
                        $agent['progress'] = $validated['progress'];
                    }
                    if (isset($validated['activity'])) {
                        $agent['currentTask'] = $validated['activity'];
                        $agent['lastActivity'] = $validated['activity'] . ' · now';
                    }
                    
                    // Update latest task progress
                    for ($i = count($state['tasks']) - 1; $i >= 0; $i--) {
                        if ($state['tasks'][$i]['agentId'] === $agentId && $state['tasks'][$i]['status'] === 'running') {
                            $state['tasks'][$i]['progress'] = $validated['progress'] ?? $state['tasks'][$i]['progress'];
                            break;
                        }
                    }
                } elseif ($event === 'task.completed') {
                    $agent['status'] = 'completed';
                    $agent['progress'] = 100;
                    $resTitle = $validated['result']['title'] ?? 'Task Completed';
                    $agent['lastActivity'] = 'Completed: ' . $resTitle . ' · now';
                    
                    // Add to recent results (keep max 3)
                    array_unshift($agent['recentResults'], $resTitle);
                    if (count($agent['recentResults']) > 3) array_pop($agent['recentResults']);
                    
                    // Update task
                    for ($i = count($state['tasks']) - 1; $i >= 0; $i--) {
                        if ($state['tasks'][$i]['agentId'] === $agentId && $state['tasks'][$i]['status'] === 'running') {
                            $state['tasks'][$i]['status'] = 'completed';
                            $state['tasks'][$i]['progress'] = 100;
                            $state['tasks'][$i]['finishedAt'] = now()->toIso8601String();
                            $state['tasks'][$i]['result'] = $resTitle;
                            break;
                        }
                    }
                } elseif ($event === 'task.failed') {
                    $agent['status'] = 'error';
                    $agent['lastActivity'] = 'Error: ' . ($validated['error'] ?? 'Unknown error');
                    
                    for ($i = count($state['tasks']) - 1; $i >= 0; $i--) {
                        if ($state['tasks'][$i]['agentId'] === $agentId && $state['tasks'][$i]['status'] === 'running') {
                            $state['tasks'][$i]['status'] = 'error';
                            $state['tasks'][$i]['error'] = $validated['error'] ?? 'Unknown error';
                            $state['tasks'][$i]['finishedAt'] = now()->toIso8601String();
                            break;
                        }
                    }
                }
            }
        }
        
        // Add to global activity log
        array_unshift($state['activity'], [
            'event' => $event,
            'agent_id' => $validated['agent_id'] ?? null,
            'timestamp' => now()->toIso8601String()
        ]);
        // Keep max 50 activity items
        if (count($state['activity']) > 50) {
            array_pop($state['activity']);
        }
        
        $this->saveState($state);
        
        return response()->json(['status' => 'ok']);
    }

    public function commandAgent(Request $request, $id)
    {
        $token = env('OFFICE_BRIDGE_TOKEN');
        $auth = $request->header('Authorization');
        
        // Note: Currently UI might not send the token (as it's a frontend action). 
        // We will just disable real execution and return mock response for MVP phase 2.
        // The user explicitly stated: "JANGAN langsung menjalankan shell/systemctl. Jika command real belum tersedia, UI boleh disabled."
        return response()->json([
            'message' => 'Command execution is disabled for safety in Phase 2.',
            'agent_id' => $id,
            'command' => $request->input('command')
        ], 200); // We return 200 so UI can show a "Simulated" message instead of failure
    }
}
