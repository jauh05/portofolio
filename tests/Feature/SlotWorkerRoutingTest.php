<?php

use App\Models\OfficeCommand;
use App\Models\OfficeContentBrand;
use App\Models\OfficeContentItem;
use App\Models\OfficeContentSchedule;
use App\Models\User;
use App\Services\OfficeScheduledGenerationService;
use App\Services\SlotFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->dir = sys_get_temp_dir().'/slot-workers-'.uniqid();
    File::ensureDirectoryExists($this->dir);
    config([
        'kauiz.slots_path' => $this->dir.'/kauiz.json',
        'jauki.slots_path' => $this->dir.'/jauki.json',
        'reels.slots_path' => $this->dir.'/reels.json',
        'slotworkers.brands' => ['kauiz', 'jauki'],
        'slotworkers.lead_minutes' => 1440,
    ]);
});

afterEach(fn () => File::deleteDirectory($this->dir));

function weekly(string $brand, string $platform, string $type, string $name, array $days = [1], string $time = '09:00', array $extra = []): OfficeContentSchedule
{
    return OfficeContentSchedule::create(array_merge([
        'name' => $name, 'topic' => '', 'brief' => '', 'schedule_type' => 'recurring', 'platform' => $platform, 'content_type' => $type,
        'brand_id' => OfficeContentBrand::where('slug', $brand)->firstOrFail()->id, 'timezone' => 'Asia/Jakarta',
        'recurrence_rule' => ['frequency' => 'weekly', 'days' => $days, 'time' => $time], 'starts_at' => now('Asia/Jakarta')->subDay(),
        'next_run_at' => now('Asia/Jakarta')->addHours(3), 'generation_mode' => 'automatic', 'generation_timing' => 'lead_time',
        'generation_lead_minutes' => 120, 'publishing_mode' => 'automatic', 'is_active' => true,
    ], $extra));
}

test('each brand gets its own slot file with only instagram feed and story slots, a day ahead', function () {
    weekly('jauki', 'instagram', 'story', 'Story Jauki', [1, 3]);
    weekly('kauiz', 'instagram', 'feed', 'Feed Kauiz', [4], '15:00');
    weekly('kauiz', 'article', 'article', 'Artikel Kauiz', [7], '13:00');
    weekly('jauki', 'threads', 'thread', 'Threads Jauki', [2], '14:00');
    weekly('personal-brand', 'instagram', 'feed', 'Feed pribadi', [5]);

    $jauki = SlotFile::read(config('jauki.slots_path'));
    $kauiz = SlotFile::read(config('kauiz.slots_path'));

    expect(collect($jauki)->map(fn ($s) => [$s['brand'], $s['format'], $s['platform'], $s['day'], $s['time'], $s['lead_minutes']])->all())
        ->toBe([['jauki', 'story', 'instagram', 1, '09:00', 1440], ['jauki', 'story', 'instagram', 3, '09:00', 1440]]);
    expect(collect($kauiz)->map(fn ($s) => [$s['brand'], $s['format'], $s['day'], $s['time']])->all())->toBe([['kauiz', 'feed', 4, '15:00']]);
    expect(SlotFile::read(config('reels.slots_path')))->toBe([]);
});

test('the lead time of slot files is configurable and ignores the value stored on the schedule', function () {
    config(['slotworkers.lead_minutes' => 720]);
    weekly('jauki', 'instagram', 'story', 'Story Jauki', [1], '09:00', ['generation_lead_minutes' => 120]);
    expect(SlotFile::read(config('jauki.slots_path'))[0]['lead_minutes'])->toBe(720);
});

test('pausing or deleting a schedule removes its slot from the worker file', function () {
    $story = weekly('jauki', 'instagram', 'story', 'Story Jauki', [1]);
    expect(SlotFile::read(config('jauki.slots_path')))->toHaveCount(1);
    $story->update(['is_active' => false]);
    expect(SlotFile::read(config('jauki.slots_path')))->toBe([]);
    $story->update(['is_active' => true]);
    expect(SlotFile::read(config('jauki.slots_path')))->toHaveCount(1);
    $story->delete();
    expect(SlotFile::read(config('jauki.slots_path')))->toBe([]);
});

test('instagram schedules of slot-worker brands are never generated through office commands', function () {
    $owner = User::factory()->create(['is_office_owner' => true]);
    $kauizFeed = weekly('kauiz', 'instagram', 'feed', 'Feed Kauiz');
    $jaukiStory = weekly('jauki', 'instagram', 'story', 'Story Jauki');

    foreach ([$kauizFeed, $jaukiStory] as $schedule) {
        $this->actingAs($owner)->postJson('/office/api/content-schedules/'.$schedule->id.'/generate')
            ->assertOk()->assertJsonPath('status', 'skipped')->assertJsonPath('reason', 'managed_by_slot_worker');
    }
    $summary = app(OfficeScheduledGenerationService::class)->runDue();
    expect($summary['generated'])->toBe(0);
    expect(OfficeCommand::count())->toBe(0)->and(OfficeContentItem::count())->toBe(0);
});

test('other brands and other platforms still use the office command path', function () {
    $owner = User::factory()->create(['is_office_owner' => true]);
    $personal = weekly('personal-brand', 'instagram', 'feed', 'Feed pribadi');
    $threads = weekly('jauki', 'threads', 'thread', 'Threads Jauki');
    $article = weekly('kauiz', 'article', 'article', 'Artikel Kauiz');

    foreach ([$personal, $threads, $article] as $schedule) {
        $this->actingAs($owner)->postJson('/office/api/content-schedules/'.$schedule->id.'/generate')->assertOk()->assertJsonPath('status', 'generated');
    }
    expect(OfficeCommand::pluck('agent_id')->sort()->values()->all())->toBe(['jauki-article', 'jauki-social', 'jauki-threads']);
});

test('the safeguard can be switched off to return everything to the office path', function () {
    config(['slotworkers.brands' => []]);
    $owner = User::factory()->create(['is_office_owner' => true]);
    $story = weekly('jauki', 'instagram', 'story', 'Story Jauki');
    $this->actingAs($owner)->postJson('/office/api/content-schedules/'.$story->id.'/generate')->assertOk()->assertJsonPath('status', 'generated');
});

test('scheduled publishing never posts slot-worker instagram items, but still publishes threads and articles', function () {
    User::factory()->create(['is_office_owner' => true]);
    $kauizFeed = weekly('kauiz', 'instagram', 'feed', 'Feed Kauiz');
    $threads = weekly('jauki', 'threads', 'thread', 'Threads Jauki');
    $due = now('Asia/Jakarta')->subMinutes(5)->toIso8601String();
    $make = fn (OfficeContentSchedule $schedule) => OfficeContentItem::create([
        'schedule_id' => $schedule->id, 'brand_id' => $schedule->brand_id, 'agent_id' => 'x', 'platform' => $schedule->platform,
        'content_type' => $schedule->content_type, 'status' => 'ready_for_review', 'deduplication_key' => uniqid('k', true),
        'metadata' => ['scheduled_at' => $due], 'title' => 'T', 'text' => 'isi',
    ]);
    $feedItem = $make($kauizFeed);
    $threadsItem = $make($threads);

    app(\App\Http\Controllers\OfficeContentPlannerController::class)->dispatchScheduledPublishing();

    expect($feedItem->fresh()->status)->toBe('ready_for_review');
    expect(OfficeCommand::where('agent_id', 'jauki-social')->count())->toBe(0);
    expect($threadsItem->fresh()->status)->toBe('publishing');
    expect(OfficeCommand::where('agent_id', 'jauki-threads')->where('action', 'publish_last')->count())->toBe(1);
});

test('office:set-lead-time moves threads and article schedules to a day ahead and leaves slot-worker schedules alone', function () {
    $threads = weekly('jauki', 'threads', 'thread', 'Threads Jauki', [2], '14:00', ['generation_lead_minutes' => 120]);
    $story = weekly('jauki', 'instagram', 'story', 'Story Jauki', [1], '09:00', ['generation_lead_minutes' => 120]);
    $manual = weekly('kauiz', 'article', 'article', 'Artikel Kauiz', [7], '13:00', ['generation_timing' => 'manual', 'generation_mode' => 'manual']);

    $this->artisan('office:set-lead-time', ['--dry-run' => true])->assertExitCode(0);
    expect($threads->fresh()->generation_lead_minutes)->toBe(120);

    $this->artisan('office:set-lead-time')->assertExitCode(0);
    expect($threads->fresh()->generation_lead_minutes)->toBe(1440);
    expect($story->fresh()->generation_lead_minutes)->toBe(120);
    expect($manual->fresh()->generation_timing)->toBe('manual');

    $this->artisan('office:set-lead-time', ['--include-manual' => true])->assertExitCode(0);
    expect($manual->fresh()->generation_timing)->toBe('lead_time')->and($manual->fresh()->generation_lead_minutes)->toBe(1440);
});

test('office:sync-slots rebuilds missing slot files from the active schedules', function () {
    weekly('jauki', 'instagram', 'story', 'Story Jauki', [1, 2]);
    File::delete([config('jauki.slots_path'), config('kauiz.slots_path')]);
    expect(File::exists(config('jauki.slots_path')))->toBeFalse();

    $this->artisan('office:sync-slots')->assertExitCode(0);

    expect(SlotFile::read(config('jauki.slots_path')))->toHaveCount(2);
    expect(File::exists(config('kauiz.slots_path')))->toBeTrue()->and(SlotFile::read(config('kauiz.slots_path')))->toBe([]);
});

test('the hourly slot sync is registered with the scheduler', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('office:sync-slots')->assertExitCode(0);
});
