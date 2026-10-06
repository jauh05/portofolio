<?php

namespace Tests\Feature;

use App\Jobs\SendTelegramNotification;
use App\Models\OfficeCommand;
use App\Models\OfficeTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TelegramNotifierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.telegram.enabled', true);
        Config::set('services.telegram.level', 'all');
        Config::set('services.telegram.bot_token', 'fake-token');
        Config::set('services.telegram.chat_id', '12345');
        Queue::fake();
    }

    public function test_user_action_triggers_notification()
    {
        $command = OfficeCommand::create([
            'agent_id' => 'jauki-social',
            'action' => 'generate_weekly',
            'status' => 'queued',
            'requested_by' => \App\Models\User::factory()->create(['is_office_owner' => true])->id
        ]);

        Queue::assertPushed(SendTelegramNotification::class, function ($job) {
            return str_contains($job->message, 'Office Action') && str_contains($job->message, 'Generate Weekly');
        });
    }

    public function test_task_status_triggers_notification()
    {
        $task = OfficeTask::create([
            'agent_id' => 'jauki-social',
            'title' => 'Weekly Content Planner',
            'status' => 'running',
            'progress' => 0
        ]);

        Queue::assertPushed(SendTelegramNotification::class, function ($job) {
            return str_contains($job->message, 'Weekly Content Planner') && str_contains($job->message, 'Running');
        });
    }
}
