<?php

namespace App\Services;

class OfficeRegistry
{
    public const COMMANDS = [
        'jauki-social' => ['generate_feed', 'generate_story', 'publish_last'],
        'jauki-threads' => ['generate_threads', 'publish_last'],
        'jauki-article' => ['generate_article', 'publish_article', 'schedule_article'],
        'jauki-planner' => ['run_weekly_analysis'],
    ];

    public const COMMAND_PAYLOAD_KEYS = [
        'generate_feed' => [],
        'generate_story' => [],
        'publish_last' => [],
        'generate_threads' => [],
        'generate_article' => [],
        'publish_article' => [],
        'schedule_article' => ['scheduled_at'],
        'run_weekly_analysis' => [],
    ];

    public static function agents(): array
    {
        return [
            'trent' => self::agent('trent', 'trent', 'not_connected', 'Integration pending'),
            'jauki-social' => self::agent('jauki-social', 'jauki-content-bot'),
            'jauki-threads' => self::agent('jauki-threads', 'jauki-content-bot'),
            'jauki-article' => self::agent('jauki-article', 'jauki-content-bot'),
            'jauki-planner' => self::agent('jauki-planner', 'jauki-content-bot'),
            'data-analyst' => self::agent('data-analyst', 'future-data-agent', 'not_connected', 'Integration pending'),
            'finance-analyst' => self::agent('finance-analyst', 'future-finance-agent', 'not_connected', 'Integration pending'),
        ];
    }

    public static function systems(): array
    {
        return [
            'trent' => ['status' => 'not_connected', 'lastHeartbeat' => null],
            'jauki-content-bot' => ['status' => 'offline', 'lastHeartbeat' => null],
            'future-data-agent' => ['status' => 'not_connected', 'lastHeartbeat' => null],
            'future-finance-agent' => ['status' => 'not_connected', 'lastHeartbeat' => null],
        ];
    }

    public static function displayName(?string $id): string
    {
        return [
            'trent' => 'Trent', 'jauki-social' => 'Social Worker', 'jauki-threads' => 'Threads Worker',
            'jauki-article' => 'Article Worker', 'jauki-planner' => 'Weekly Planner',
            'data-analyst' => 'Data Analyst', 'finance-analyst' => 'Finance Analyst',
        ][$id] ?? 'Office';
    }

    private static function agent(string $id, string $system, string $status = 'idle', string $task = 'Waiting for tasks'): array
    {
        return [
            'id' => $id, 'parentSystem' => $system, 'status' => $status, 'currentTask' => $task,
            'progress' => 0, 'lastActivity' => $status === 'idle' ? 'Idle' : $task, 'recentResults' => [],
        ];
    }
}
