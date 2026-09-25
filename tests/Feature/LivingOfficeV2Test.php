<?php

use App\Models\OfficeCommand;
use App\Models\OfficeContentItem;
use App\Models\OfficeEvent;
use App\Models\OfficeNotification;
use App\Models\OfficeTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    config(['services.office_bridge.token' => 'bridge-test-token']);
});

function bridgeHeaders(string $token = 'bridge-test-token'): array
{
    return ['Authorization' => 'Bearer '.$token];
}

test('owner operations APIs require authentication', function () {
    foreach (['/office/api/tasks', '/office/api/activity', '/office/api/content', '/office/api/notifications', '/office/api/commands', '/office/api/summary'] as $url) {
        $this->getJson($url)->assertUnauthorized();
    }
});

test('owner can access operations APIs and content has an explicit empty response', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner)->getJson('/office/api/tasks')->assertOk()->assertJson(['data' => [], 'empty' => true]);
    $this->actingAs($owner)->getJson('/office/api/content')->assertOk()->assertJson(['data' => [], 'empty' => true]);
    $this->actingAs($owner)->getJson('/office/api/activity')->assertOk()->assertJson(['data' => [], 'empty' => true]);
    $this->actingAs($owner)->getJson('/office/api/summary')->assertOk()->assertJsonStructure(['workers', 'workingNow', 'tasksToday', 'contentThisWeek', 'successRate', 'averageRuntimeSeconds']);
});

test('bridge accepts the configured token and rejects a bad token', function () {
    $payload = ['event' => 'system.heartbeat', 'parent_system' => 'jauki-content-bot'];
    $this->postJson('/api/office/events', $payload, bridgeHeaders())->assertOk();
    $this->postJson('/api/office/events', $payload, bridgeHeaders('wrong'))->assertUnauthorized();
});

test('task lifecycle is persisted and only important events notify', function () {
    $this->postJson('/api/office/events', [
        'event' => 'task.started', 'event_id' => 'event-task-start-42', 'agent_id' => 'jauki-article',
        'task' => ['id' => 'article-42', 'title' => 'Write launch article', 'type' => 'article', 'target' => 'Kauiz'],
    ], bridgeHeaders())->assertOk();
    $this->postJson('/api/office/events', [
        'event' => 'task.progress', 'agent_id' => 'jauki-article', 'progress' => 60, 'activity' => 'Writing body copy',
    ], bridgeHeaders())->assertOk();
    expect(OfficeNotification::count())->toBe(0);

    $this->postJson('/api/office/events', [
        'event' => 'task.completed', 'event_id' => 'event-task-complete-42', 'agent_id' => 'jauki-article', 'result' => ['title' => 'Launch article published'],
    ], bridgeHeaders())->assertOk();
    $this->postJson('/api/office/events', [
        'event' => 'task.completed', 'event_id' => 'event-task-complete-42', 'agent_id' => 'jauki-article', 'result' => ['title' => 'Launch article published'],
    ], bridgeHeaders())->assertOk();

    $task = OfficeTask::first();
    expect($task->status)->toBe('completed')->and($task->progress)->toBe(100);
    expect(OfficeEvent::count())->toBe(3);
    expect(OfficeNotification::count())->toBe(1);
    expect(OfficeNotification::first()->type)->toBe('task.completed');
});

test('task events correlate by task id when the same agent has concurrent tasks', function () {
    foreach ([
        ['id' => 'threads-first', 'title' => 'First Threads task'],
        ['id' => 'threads-second', 'title' => 'Second Threads task'],
    ] as $task) {
        $this->postJson('/api/office/events', [
            'event' => 'task.started', 'agent_id' => 'jauki-threads',
            'task' => ['id' => $task['id'], 'title' => $task['title'], 'type' => 'threads'],
        ], bridgeHeaders())->assertOk();
    }

    $this->postJson('/api/office/events', [
        'event' => 'task.progress', 'agent_id' => 'jauki-threads', 'task_id' => 'threads-first', 'progress' => 40,
    ], bridgeHeaders())->assertOk();
    expect(OfficeTask::where('external_id', 'threads-first')->firstOrFail()->progress)->toBe(40)
        ->and(OfficeTask::where('external_id', 'threads-second')->firstOrFail()->progress)->toBe(0);

    $this->postJson('/api/office/events', [
        'event' => 'task.completed', 'agent_id' => 'jauki-threads', 'task_id' => 'threads-first',
        'result' => ['title' => 'First task complete'],
    ], bridgeHeaders())->assertOk();
    expect(OfficeTask::where('external_id', 'threads-first')->firstOrFail()->status)->toBe('completed')
        ->and(OfficeTask::where('external_id', 'threads-second')->firstOrFail()->status)->toBe('running');

    $this->postJson('/api/office/events', [
        'event' => 'task.failed', 'agent_id' => 'jauki-threads', 'task_id' => 'threads-second',
        'error' => 'Second task failed',
    ], bridgeHeaders())->assertOk();
    expect(OfficeTask::where('external_id', 'threads-first')->firstOrFail()->status)->toBe('completed')
        ->and(OfficeTask::where('external_id', 'threads-second')->firstOrFail()->status)->toBe('failed');
});

test('task event with an unknown task id does not update another running task', function () {
    $this->postJson('/api/office/events', [
        'event' => 'task.started', 'agent_id' => 'jauki-threads',
        'task' => ['id' => 'known-task', 'title' => 'Known task', 'type' => 'threads'],
    ], bridgeHeaders())->assertOk();

    $this->postJson('/api/office/events', [
        'event' => 'task.progress', 'agent_id' => 'jauki-threads', 'task_id' => 'missing-task', 'progress' => 75,
    ], bridgeHeaders())->assertOk();

    expect(OfficeTask::where('external_id', 'known-task')->firstOrFail()->progress)->toBe(0);
});

test('legacy task events without task id still use the latest running task', function () {
    $this->postJson('/api/office/events', [
        'event' => 'task.started', 'agent_id' => 'jauki-threads',
        'task' => ['id' => 'legacy-task', 'title' => 'Legacy task', 'type' => 'threads'],
    ], bridgeHeaders())->assertOk();
    $this->postJson('/api/office/events', [
        'event' => 'task.progress', 'agent_id' => 'jauki-threads', 'progress' => 55,
    ], bridgeHeaders())->assertOk();

    expect(OfficeTask::where('external_id', 'legacy-task')->firstOrFail()->progress)->toBe(55);
});

test('task id validation accepts strings up to 191 characters', function () {
    $this->postJson('/api/office/events', [
        'event' => 'task.progress', 'agent_id' => 'jauki-threads', 'task_id' => str_repeat('a', 191), 'progress' => 10,
    ], bridgeHeaders())->assertOk();
    $this->postJson('/api/office/events', [
        'event' => 'task.progress', 'agent_id' => 'jauki-threads', 'task_id' => str_repeat('a', 192), 'progress' => 10,
    ], bridgeHeaders())->assertUnprocessable();
});

test('heartbeats do not create event or notification spam', function () {
    foreach (range(1, 5) as $_) {
        $this->postJson('/api/office/events', ['event' => 'system.heartbeat', 'parent_system' => 'jauki-content-bot'], bridgeHeaders())->assertOk();
    }
    expect(OfficeEvent::count())->toBe(0)->and(OfficeNotification::count())->toBe(0);
});

test('failed task stores its error and creates only one notification', function () {
    $this->postJson('/api/office/events', [
        'event' => 'task.started', 'event_id' => 'failed-task-start', 'agent_id' => 'jauki-threads',
        'task' => ['id' => 'threads-99', 'title' => 'Publish Threads post', 'type' => 'threads'],
    ], bridgeHeaders())->assertOk();
    $failed = ['event' => 'task.failed', 'event_id' => 'failed-task-end', 'agent_id' => 'jauki-threads', 'error' => 'Provider rejected the post'];
    $this->postJson('/api/office/events', $failed, bridgeHeaders())->assertOk();
    $this->postJson('/api/office/events', $failed, bridgeHeaders())->assertOk();

    $task = OfficeTask::where('external_id', 'threads-99')->firstOrFail();
    expect($task->status)->toBe('failed');
    expect($task->metadata['error'])->toBe('Provider rejected the post');
    expect(OfficeNotification::where('type', 'task.failed')->count())->toBe(1);
});

test('content preview is persisted and creates one important notification', function () {
    $preview = [
        'event' => 'content.preview_ready', 'event_id' => 'preview-feed-55', 'agent_id' => 'jauki-social',
        'content' => ['platform' => 'instagram', 'content_type' => 'feed', 'external_id' => 'media-55', 'title' => 'Launch feed', 'caption' => 'Ready to review'],
    ];
    $this->postJson('/api/office/events', $preview, bridgeHeaders())->assertOk();
    $this->postJson('/api/office/events', $preview, bridgeHeaders())->assertOk();
    expect(OfficeContentItem::count())->toBe(1);
    expect(OfficeContentItem::first()->status)->toBe('preview_ready');
    expect(OfficeNotification::where('type', 'content.preview_ready')->count())->toBe(1);

    $published = [
        'event' => 'content.published', 'event_id' => 'published-feed-55', 'agent_id' => 'jauki-social',
        'content' => ['platform' => 'instagram', 'content_type' => 'feed', 'external_id' => 'media-55', 'title' => 'Launch feed', 'public_url' => 'https://example.test/feed/55'],
    ];
    $this->postJson('/api/office/events', $published, bridgeHeaders())->assertOk();
    $this->postJson('/api/office/events', $published, bridgeHeaders())->assertOk();
    expect(OfficeContentItem::count())->toBe(1);
    expect(OfficeContentItem::first()->status)->toBe('published');
    expect(OfficeNotification::where('type', 'content.published')->count())->toBe(1);
});

test('valid command is queued but unknown and executable actions are rejected', function () {
    Process::fake();
    $owner = User::factory()->create();
    $this->actingAs($owner)->postJson('/office/api/agents/jauki-social/command', [
        'action' => 'generate_feed', 'payload' => [],
    ])->assertStatus(202)->assertJson(['status' => 'queued']);
    $this->actingAs($owner)->postJson('/office/api/agents/jauki-social/command', [
        'action' => 'systemctl', 'payload' => ['command' => 'sudo systemctl restart nginx'],
    ])->assertUnprocessable();
    $this->actingAs($owner)->postJson('/office/api/agents/jauki-social/command', [
        'action' => 'generate_feed', 'payload' => ['shell' => '/bin/sh', 'python' => 'import os'],
    ])->assertUnprocessable();
    expect(OfficeCommand::count())->toBe(1);
    expect(OfficeCommand::first()->status)->toBe('queued');
    Process::assertNothingRan();
});

test('command endpoint requires an authenticated owner', function () {
    $this->postJson('/office/api/agents/jauki-social/command', ['action' => 'generate_feed', 'payload' => []])->assertUnauthorized();
    expect(OfficeCommand::count())->toBe(0);
});

test('bot can claim and report an allowlisted command with server token only', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner)->postJson('/office/api/agents/jauki-threads/command', ['action' => 'generate_threads', 'payload' => []])->assertStatus(202);
    $claim = $this->postJson('/api/office/commands/claim', ['agent_id' => 'jauki-threads'], bridgeHeaders())->assertOk();
    $id = $claim->json('command.id');
    expect($id)->not->toBeNull();
    $this->postJson('/api/office/commands/claim', ['agent_id' => 'jauki-threads'], bridgeHeaders())->assertOk()->assertJson(['command' => null]);
    $this->patchJson('/api/office/commands/'.$id, ['status' => 'running'], bridgeHeaders())->assertOk()->assertJson(['status' => 'running']);
    $this->patchJson('/api/office/commands/'.$id, ['status' => 'completed'], bridgeHeaders())->assertOk()->assertJson(['status' => 'completed']);
});

test('threads claim leaves queued article commands untouched', function () {
    $owner = User::factory()->create();
    $article = OfficeCommand::create([
        'agent_id' => 'jauki-article', 'action' => 'generate_article', 'payload' => [],
        'status' => 'queued', 'requested_by' => $owner->id,
    ]);
    $threads = OfficeCommand::create([
        'agent_id' => 'jauki-threads', 'action' => 'generate_threads', 'payload' => [],
        'status' => 'queued', 'requested_by' => $owner->id,
    ]);

    $this->postJson('/api/office/commands/claim', ['agent_id' => 'jauki-threads'], bridgeHeaders())
        ->assertOk()
        ->assertJsonPath('command.id', $threads->id);

    expect($article->fresh()->status)->toBe('queued')
        ->and($threads->fresh()->status)->toBe('claimed');
});

test('command bridge APIs reject missing and invalid bearer tokens', function () {
    $owner = User::factory()->create();
    $command = OfficeCommand::create([
        'agent_id' => 'jauki-threads',
        'action' => 'generate_threads',
        'payload' => [],
        'status' => 'claimed',
        'requested_by' => $owner->id,
    ]);

    $this->postJson('/api/office/commands/claim', ['agent_id' => 'jauki-threads'])->assertUnauthorized();
    $this->postJson('/api/office/commands/claim', ['agent_id' => 'jauki-threads'], bridgeHeaders('wrong'))->assertUnauthorized();
    $this->patchJson('/api/office/commands/'.$command->id, ['status' => 'running'])->assertUnauthorized();
    $this->patchJson('/api/office/commands/'.$command->id, ['status' => 'running'], bridgeHeaders('wrong'))->assertUnauthorized();

    expect($command->fresh()->status)->toBe('claimed');
});

test('claimed commands cannot skip the running lifecycle state', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner)->postJson('/office/api/agents/jauki-social/command', ['action' => 'generate_feed', 'payload' => []])->assertStatus(202);
    $id = $this->postJson('/api/office/commands/claim', ['agent_id' => 'jauki-social'], bridgeHeaders())->json('command.id');
    $this->patchJson('/api/office/commands/'.$id, ['status' => 'completed'], bridgeHeaders())->assertConflict();
    expect(OfficeCommand::find($id)->status)->toBe('claimed');
});

test('office bridge token is not rendered into the dashboard', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner)->get('/office')->assertOk()->assertDontSee('bridge-test-token');
});
