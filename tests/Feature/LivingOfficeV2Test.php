<?php

use App\Models\OfficeAnalystReport;
use App\Models\OfficeCommand;
use App\Models\OfficeContentItem;
use App\Models\OfficeContentBrand;
use App\Models\OfficeContentSchedule;
use App\Models\OfficeEvent;
use App\Models\OfficeNotification;
use App\Models\OfficeTask;
use App\Services\OfficeAiGateway;
use App\Services\OfficeAiGatewayException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

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
    foreach (['/office/api/tasks', '/office/api/activity', '/office/api/content', '/office/api/content-brands', '/office/api/content-planner', '/office/api/content-schedules', '/office/api/notifications', '/office/api/commands', '/office/api/summary'] as $url) {
        $this->getJson($url)->assertUnauthorized();
    }
});

test('owner can create, list, and deactivate content brands', function () {
    $owner = User::factory()->create(['is_office_owner' => true]);
    $this->actingAs($owner)->getJson('/office/api/content-brands')->assertOk()->assertJsonCount(3, 'data');
    $created = $this->actingAs($owner)->postJson('/office/api/content-brands', [
        'name' => 'Client Alpha', 'slug' => 'client-alpha', 'primary_platform' => 'linkedin',
    ])->assertCreated()->assertJsonPath('slug', 'client-alpha');
    $id = $created->json('id');
    $this->actingAs($owner)->patchJson('/office/api/content-brands/'.$id, ['is_active' => false])->assertOk()->assertJsonPath('isActive', false);
    $this->actingAs($owner)->getJson('/office/api/content-brands')->assertOk()->assertJsonMissing(['id' => $id]);
});

function fakePlanner(array $plan): void
{
    config(['services.office_ai' => ['base_url' => 'https://planner.test/v1', 'api_key' => 'test-key', 'model' => 'office-test']]);
    Http::fake(['planner.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode($plan)]]]])]);
}

function fakeOfficeAiResponse(string $content, int $status = 200): void
{
    config(['services.office_ai' => ['base_url' => 'https://planner.test/v1', 'api_key' => 'test-key', 'model' => 'office-test']]);
    Http::fake(['planner.test/*' => Http::response($status === 200 ? ['choices' => [['message' => ['content' => $content]]]] : ['error' => ['message' => 'provider error']], $status)]);
}

test('shared Office AI gateway accepts code-fenced JSON', function () {
    fakeOfficeAiResponse("```json\n{\"ok\":true}\n```");
    expect(app(OfficeAiGateway::class)->completeJson('system', 'user'))->toBe(['ok' => true]);
});

test('shared Office AI gateway exposes controlled provider errors', function () {
    fakeOfficeAiResponse('', 429);
    expect(fn () => app(OfficeAiGateway::class)->completeJson('system', 'user'))->toThrow(OfficeAiGatewayException::class, 'rate-limited');
});

test('AI Planner handles string clarifications and code-fenced JSON without creating a schedule', function () {
    $owner = User::factory()->create(['is_office_owner' => true]);
    fakeOfficeAiResponse("```json\n".json_encode(['type' => 'schedule_plan', 'items' => [[
        'schedule_type' => 'one_time', 'brand_slug' => 'kauiz', 'platform' => 'instagram', 'content_type' => 'feed', 'topic' => 'Study tips', 'time' => '19:00', 'scheduled_at' => '2026-10-10T19:00:00+07:00',
    ]], 'clarifications' => ['Check the content angle.']])."\n```");
    $this->actingAs($owner)->postJson('/office/api/content-planner/analyze', ['prompt' => 'Create a Kauiz feed on 10 October at 19:00.'])
        ->assertOk()->assertJsonPath('plan.items.0.schedule_type', 'one_time')->assertJsonPath('clarifications.0', 'Check the content angle.');
    expect(OfficeContentSchedule::count())->toBe(0)->and(OfficeCommand::count())->toBe(0);
});

test('AI Planner reports invalid provider JSON as a controlled response', function () {
    $owner = User::factory()->create(['is_office_owner' => true]);
    fakeOfficeAiResponse('not json');
    $this->actingAs($owner)->postJson('/office/api/content-planner/analyze', ['prompt' => 'Create a Kauiz feed tomorrow at 19:00.'])
        ->assertStatus(502)->assertJsonPath('message', 'Office AI provider returned invalid JSON.');
});

test('owner receives a safe recurring AI Plan preview without worker execution', function () {
    $owner = User::factory()->create(['is_office_owner' => true]);
    fakePlanner(['type' => 'schedule_plan', 'items' => [[
        'schedule_type' => 'recurring', 'brand_slug' => 'kauiz', 'platform' => 'instagram', 'content_type' => 'feed',
        'topic' => 'tips belajar', 'frequency' => 'weekly', 'weekdays' => ['monday'], 'time' => '19:00', 'starts_at' => '2026-10-05',
    ]], 'clarifications' => []]);
    $this->actingAs($owner)->postJson('/office/api/content-planner/analyze', ['prompt' => 'Setiap Senin buat Feed Kauiz jam 19:00 tentang tips belajar.'])
        ->assertOk()->assertJsonPath('plan.items.0.schedule_type', 'recurring')->assertJsonPath('plan.items.0.brand_id', OfficeContentBrand::where('slug', 'kauiz')->firstOrFail()->id);
    expect(OfficeCommand::count())->toBe(0)->and(OfficeContentSchedule::count())->toBe(0);
});

test('AI Plan returns one-time and multi-schedule previews for user confirmation', function () {
    $owner = User::factory()->create(['is_office_owner' => true]);
    fakePlanner(['type' => 'schedule_plan', 'items' => [
        ['schedule_type' => 'one_time', 'brand_slug' => 'jauki', 'platform' => 'threads', 'content_type' => 'post', 'topic' => 'promo SPSS', 'time' => '19:00', 'scheduled_at' => '2026-10-02T19:00:00+07:00'],
        ['schedule_type' => 'recurring', 'brand_slug' => 'kauiz', 'platform' => 'instagram', 'content_type' => 'story', 'topic' => 'tips belajar', 'frequency' => 'weekly', 'weekdays' => ['monday'], 'time' => '12:00', 'starts_at' => '2026-10-05'],
    ], 'clarifications' => []]);
    $this->actingAs($owner)->postJson('/office/api/content-planner/analyze', ['prompt' => 'Besok jam 19 buat Threads Jauki dan setiap Senin Story Kauiz jam 12.'])
        ->assertOk()->assertJsonCount(2, 'plan.items')->assertJsonPath('plan.items.0.schedule_type', 'one_time');
    expect(OfficeContentSchedule::count())->toBe(0);
});

test('AI Plan requests clarification for unknown workspace', function () {
    $owner = User::factory()->create(['is_office_owner' => true]);
    fakePlanner(['type' => 'schedule_plan', 'items' => [
        ['schedule_type' => 'recurring', 'brand_slug' => 'unknown-brand', 'platform' => 'instagram', 'content_type' => 'feed', 'frequency' => 'weekly', 'weekdays' => ['monday']],
    ], 'clarifications' => []]);
    $this->actingAs($owner)->postJson('/office/api/content-planner/analyze', ['prompt' => 'Setiap Senin buat feed untuk brand baru.'])
        ->assertOk()->assertJsonPath('plan.items', [])->assertJsonFragment(['Workspace \'unknown-brand\' is not available.']);
});

test('AI Plan requests clarification for missing time', function () {
    $owner = User::factory()->create(['is_office_owner' => true]);
    fakePlanner(['type' => 'schedule_plan', 'items' => [
        ['schedule_type' => 'recurring', 'brand_slug' => 'kauiz', 'platform' => 'instagram', 'content_type' => 'feed', 'frequency' => 'weekly', 'weekdays' => ['monday']],
    ], 'clarifications' => []]);
    $this->actingAs($owner)->postJson('/office/api/content-planner/analyze', ['prompt' => 'Setiap Senin buat feed Kauiz.'])
        ->assertOk()->assertJsonFragment(['Schedule item 1 needs a time.']);
});

test('AI Plan endpoint is owner-only', function () {
    $this->postJson('/office/api/content-planner/analyze', ['prompt' => 'Every Monday create a Kauiz feed at 19:00.'])->assertUnauthorized();
});

test('AI Plan preview creates a schedule only after explicit confirmation through the schedule API', function () {
    $owner = User::factory()->create(['is_office_owner' => true]);
    fakePlanner(['type' => 'schedule_plan', 'items' => [[
        'schedule_type' => 'recurring', 'brand_slug' => 'kauiz', 'platform' => 'instagram', 'content_type' => 'feed',
        'topic' => 'tips belajar', 'frequency' => 'weekly', 'weekdays' => ['monday'], 'time' => '19:00', 'starts_at' => '2026-10-05',
    ]], 'clarifications' => []]);
    $preview = $this->actingAs($owner)->postJson('/office/api/content-planner/analyze', ['prompt' => 'Every Monday create a Kauiz feed at 19:00.'])->assertOk();
    $this->actingAs($owner)->postJson('/office/api/content-schedules', $preview->json('plan.items.0'))->assertCreated();
    expect(OfficeContentSchedule::count())->toBe(1)->and(OfficeCommand::count())->toBe(0);
});

test('owner can create one-time content schedules and see them only inside a requested calendar range', function () {
    $owner = User::factory()->create(['is_office_owner' => true]);
    $brand = OfficeContentBrand::where('slug', 'kauiz')->firstOrFail();
    $payload = [
        'name' => 'Portfolio launch post', 'schedule_type' => 'one_time', 'platform' => 'linkedin', 'content_type' => 'post',
        'brand_id' => $brand->id, 'topic' => 'Launch', 'timezone' => 'Asia/Jakarta', 'scheduled_at' => '2026-10-15 09:30:00',
        'generation_mode' => 'manual', 'publishing_mode' => 'review',
    ];
    $this->actingAs($owner)->postJson('/office/api/content-schedules', $payload)->assertCreated()
        ->assertJsonPath('scheduleType', 'one_time')->assertJsonPath('brand.slug', 'kauiz')->assertJsonPath('timezone', 'Asia/Jakarta');

    $this->actingAs($owner)->getJson('/office/api/content-planner?start=2026-10-01&end=2026-10-31&timezone=Asia/Jakarta')
        ->assertOk()->assertJsonCount(1, 'occurrences')->assertJsonPath('occurrences.0.name', 'Portfolio launch post')->assertJsonPath('occurrences.0.brand.slug', 'kauiz');
    $this->actingAs($owner)->getJson('/office/api/content-planner?start=2026-11-01&end=2026-11-30&timezone=Asia/Jakarta')
        ->assertOk()->assertJsonCount(0, 'occurrences');
    expect(OfficeContentSchedule::count())->toBe(1);
});

test('planner occurrence calculation normalizes hydrated Carbon values to CarbonImmutable', function () {
    $owner = User::factory()->create(['is_office_owner' => true]);
    $brand = OfficeContentBrand::where('slug', 'kauiz')->firstOrFail();
    OfficeContentSchedule::create([
        'name' => 'Immutable regression', 'brand_id' => $brand->id, 'schedule_type' => 'one_time', 'platform' => 'instagram', 'content_type' => 'feed',
        'timezone' => 'Asia/Jakarta', 'scheduled_at' => '2026-10-15 19:00:00', 'generation_mode' => 'manual', 'publishing_mode' => 'review', 'is_active' => true,
    ]);
    $this->actingAs($owner)->getJson('/office/api/content-planner?start=2026-10-01&end=2026-10-31&timezone=Asia/Jakarta')
        ->assertOk()->assertJsonCount(1, 'occurrences');
});

test('owner can create recurring schedules without generating unbounded content rows', function () {
    $owner = User::factory()->create(['is_office_owner' => true]);
    $brand = OfficeContentBrand::where('slug', 'jauki')->firstOrFail();
    $payload = [
        'name' => 'Weekly product insight', 'schedule_type' => 'recurring', 'platform' => 'instagram', 'content_type' => 'carousel',
        'brand_id' => $brand->id, 'timezone' => 'Asia/Jakarta', 'frequency' => 'weekly', 'days' => [1, 4], 'time' => '08:00', 'starts_at' => '2026-10-01',
        'generation_mode' => 'manual', 'publishing_mode' => 'review',
    ];
    $this->actingAs($owner)->postJson('/office/api/content-schedules', $payload)->assertCreated()
        ->assertJsonPath('scheduleType', 'recurring')->assertJsonPath('recurrenceRule.frequency', 'weekly');

    $this->actingAs($owner)->getJson('/office/api/content-planner?start=2026-10-01&end=2026-10-31&timezone=Asia/Jakarta')
        ->assertOk()->assertJsonPath('occurrences.0.scheduleType', 'recurring');
    $this->actingAs($owner)->getJson('/office/api/content-planner?start=2026-10-01&end=2026-10-31&timezone=Asia/Jakarta&brand_id='.$brand->id)
        ->assertOk()->assertJsonPath('occurrences.0.brand.slug', 'jauki');
    expect(OfficeContentSchedule::count())->toBe(1)->and(OfficeContentItem::count())->toBe(1);
});

test('owner can update and deactivate a content schedule', function () {
    $owner = User::factory()->create(['is_office_owner' => true]);
    $brand = OfficeContentBrand::where('slug', 'personal-brand')->firstOrFail();
    $schedule = OfficeContentSchedule::create([
        'name' => 'Original', 'schedule_type' => 'one_time', 'platform' => 'blog', 'content_type' => 'article',
        'brand_id' => $brand->id, 'timezone' => 'Asia/Jakarta', 'scheduled_at' => '2026-10-20 10:00:00', 'generation_mode' => 'manual', 'publishing_mode' => 'review', 'is_active' => true,
    ]);
    $payload = [
        'name' => 'Updated', 'brand_id' => $brand->id, 'schedule_type' => 'one_time', 'platform' => 'blog', 'content_type' => 'article',
        'timezone' => 'Asia/Jakarta', 'scheduled_at' => '2026-10-21 10:00:00', 'generation_mode' => 'manual', 'publishing_mode' => 'review',
    ];
    $this->actingAs($owner)->patchJson('/office/api/content-schedules/'.$schedule->id, $payload)->assertOk()->assertJsonPath('name', 'Updated');
    $this->actingAs($owner)->deleteJson('/office/api/content-schedules/'.$schedule->id)->assertOk()->assertJsonPath('status', 'deactivated');
    expect($schedule->fresh()->is_active)->toBeFalse();
});

test('owner can access operations APIs and content has an explicit empty response', function () {
    $owner = User::factory()->create(['is_office_owner' => true]);
    $this->actingAs($owner)->getJson('/office/api/tasks')->assertOk()->assertJson(['data' => [], 'empty' => true]);
    $this->actingAs($owner)->getJson('/office/api/content')->assertOk()->assertJson(['data' => [], 'empty' => true]);
    $this->actingAs($owner)->getJson('/office/api/activity')->assertOk()->assertJson(['data' => [], 'empty' => true]);
    $this->actingAs($owner)->getJson('/office/api/summary')->assertOk()->assertJsonStructure(['workers', 'workingNow', 'tasksToday', 'contentThisWeek', 'completedToday', 'errorsToday', 'successRate', 'averageRuntimeSeconds']);
});

test('summary metrics are calculated from current office records', function () {
    $owner = User::factory()->create(['is_office_owner' => true]);
    $now = now();

    foreach ([
        ['status' => 'claimed', 'title' => 'Claimed task'],
        ['status' => 'running', 'title' => 'Running task'],
        ['status' => 'completed', 'title' => 'Completed task', 'completed_at' => $now],
        ['status' => 'failed', 'title' => 'Failed task', 'failed_at' => $now],
    ] as $index => $attributes) {
        OfficeTask::create(array_merge([
            'agent_id' => 'jauki-article', 'external_id' => 'summary-'.$index, 'type' => 'article',
            'progress' => 0, 'started_at' => $now,
        ], $attributes));
    }
    OfficeContentItem::create([
        'deduplication_key' => 'summary-content', 'agent_id' => 'jauki-article', 'platform' => 'article',
        'content_type' => 'article', 'status' => 'generated', 'generated_at' => $now,
    ]);

    $this->actingAs($owner)->getJson('/office/api/summary')->assertOk()->assertJson([
        'workingNow' => 2, 'tasksToday' => 4, 'contentThisWeek' => 1, 'completedToday' => 1, 'errorsToday' => 1,
    ]);
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
    $owner = User::factory()->create(['is_office_owner' => true]);
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
    $owner = User::factory()->create(['is_office_owner' => true]);
    $this->actingAs($owner)->postJson('/office/api/agents/jauki-threads/command', ['action' => 'generate_threads', 'payload' => []])->assertStatus(202);
    $claim = $this->postJson('/api/office/commands/claim', ['agent_id' => 'jauki-threads'], bridgeHeaders())->assertOk();
    $id = $claim->json('command.id');
    expect($id)->not->toBeNull();
    $this->postJson('/api/office/commands/claim', ['agent_id' => 'jauki-threads'], bridgeHeaders())->assertOk()->assertJson(['command' => null]);
    $this->patchJson('/api/office/commands/'.$id, ['status' => 'running'], bridgeHeaders())->assertOk()->assertJson(['status' => 'running']);
    $this->patchJson('/api/office/commands/'.$id, ['status' => 'completed'], bridgeHeaders())->assertOk()->assertJson(['status' => 'completed']);
});

test('threads claim leaves queued article commands untouched', function () {
    $owner = User::factory()->create(['is_office_owner' => true]);
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
    $owner = User::factory()->create(['is_office_owner' => true]);
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
    $owner = User::factory()->create(['is_office_owner' => true]);
    $this->actingAs($owner)->postJson('/office/api/agents/jauki-social/command', ['action' => 'generate_feed', 'payload' => []])->assertStatus(202);
    $id = $this->postJson('/api/office/commands/claim', ['agent_id' => 'jauki-social'], bridgeHeaders())->json('command.id');
    $this->patchJson('/api/office/commands/'.$id, ['status' => 'completed'], bridgeHeaders())->assertConflict();
    expect(OfficeCommand::find($id)->status)->toBe('claimed');
});

test('analyst reports are owner-only, persist history, and disclose missing metrics inside the generated summary', function () {
    $owner = User::factory()->create(['is_office_owner' => true]);
    $this->getJson('/office/api/analyst-reports')->assertUnauthorized();
    $this->actingAs($owner)->postJson('/office/api/analyst-reports/generate', ['report_type' => 'manual', 'scope' => 'today'])->assertCreated();
    expect(OfficeAnalystReport::count())->toBe(1);
});

test('analyst reports decouple research and use Agent-Reach', function () {
    $owner = User::factory()->create(['is_office_owner' => true]);
    $response = $this->actingAs($owner)->postJson('/office/api/analyst-reports/generate', ['report_type' => 'manual', 'scope' => 'today'])
        ->assertCreated()->assertJsonPath('status', 'generating');
    
    $run = \App\Models\OfficeResearchRun::first();
    expect($run)->not->toBeNull();
    
    fakeOfficeAiResponse(json_encode([
        'summary' => 'Data performa belum tersedia. Analysis complete.',
        'conclusion' => 'Safe to proceed.',
        'findings' => [['type' => 'issue', 'title' => 'Test finding', 'description' => 'A finding', 'evidence' => 'No evidence']],
        'recommendations' => [['priority' => 'medium', 'action' => 'Prepare a Kauiz planning task', 'reason' => 'The brand has no active schedule.']],
        'content_plan' => [['brand_slug' => 'kauiz', 'platform' => 'instagram', 'content_type' => 'feed', 'topic' => 'Topic 1', 'brief' => 'Brief 1', 'reason' => 'Reason', 'scheduled_at' => '2026-10-10 19:00:00', 'generation_mode' => 'manual', 'publishing_mode' => 'review']],
    ]));

    $bridgeToken = config('services.office_bridge.token');
    $this->postJson('/api/office/events', [
        'event' => 'research.completed',
        'agent_id' => 'jauki-analyst',
        'task_id' => $run->command_id,
        'result' => ['sources' => [], 'summary' => 'Findings', 'trend_signals' => []]
    ], ['Authorization' => 'Bearer ' . $bridgeToken])->assertOk();

    $report = OfficeAnalystReport::first();
    expect($report->status)->toBe('ready')
        ->and($report->metadata['analysis_mode'])->toBe('ai');
});

test('approval creates schedule from content plan', function () {
    $owner = User::factory()->create(['is_office_owner' => true]);
    $brand = App\Models\OfficeContentBrand::firstOrCreate(['slug' => 'kauiz'], ['name' => 'Kauiz', 'is_active' => true]);
    $run = \App\Models\OfficeResearchRun::create(['command_id' => '00000000-0000-0000-0000-000000000000', 'status' => 'completed']);
    $report = OfficeAnalystReport::create([
        'report_date' => '2026-10-10', 'report_type' => 'manual', 'status' => 'ready', 'summary' => '', 'findings' => [], 'conclusion' => '', 'recommendations' => [], 'next_actions' => [],
        'metadata' => ['research_run_id' => $run->id], 'generated_at' => now('Asia/Jakarta'),
    ]);
    $plan = \App\Models\OfficeContentPlanItem::create([
        'research_run_id' => $run->id, 'brand_id' => $brand->id, 'platform' => 'instagram', 'content_type' => 'feed', 'topic' => 'T', 'scheduled_at' => now(), 'generation_mode' => 'automatic', 'publishing_mode' => 'review', 'status' => 'proposed'
    ]);

    $this->actingAs($owner)->postJson('/office/api/analyst-reports/'.$report->id.'/approve', ['plan_ids' => [$plan->id]])->assertOk();
    expect(OfficeContentSchedule::count())->toBe(1)
        ->and(\App\Models\OfficeCommand::where('agent_id', 'jauki-social')->count())->toBe(1);
});

test('office bridge token is not rendered into the dashboard', function () {
    $owner = User::factory()->create(['is_office_owner' => true]);
    config(['services.office_bridge.token' => 'secret-bridge-token-xyz']);
    $this->actingAs($owner)->get('/office')->assertDontSee('secret-bridge-token-xyz');
});