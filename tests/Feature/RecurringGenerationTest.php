<?php

use App\Models\OfficeCommand;
use App\Models\OfficeContentBrand;
use App\Models\OfficeContentItem;
use App\Models\OfficeContentSchedule;
use App\Models\User;
use App\Services\OfficeScheduledGenerationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->dir = sys_get_temp_dir().'/recurring-'.uniqid();
    File::ensureDirectoryExists($this->dir);
    config(['kauiz.slots_path' => $this->dir.'/kauiz.json', 'jauki.slots_path' => $this->dir.'/jauki.json']);
    User::factory()->create(['is_office_owner' => true]);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    File::deleteDirectory($this->dir);
});

function recurring(array $rule, array $extra = []): OfficeContentSchedule
{
    return OfficeContentSchedule::create(array_merge([
        'name' => 'Threads Jauki', 'topic' => '', 'brief' => '', 'schedule_type' => 'recurring', 'platform' => 'threads', 'content_type' => 'thread',
        'brand_id' => OfficeContentBrand::where('slug', 'jauki')->firstOrFail()->id, 'timezone' => 'Asia/Jakarta',
        'recurrence_rule' => $rule, 'starts_at' => '2026-10-01 00:00:00',
        // next_run_at lama: dihitung sekali saat jadwal disimpan dan tidak pernah dimajukan, sama seperti di produksi.
        'next_run_at' => '2026-10-15 14:00:00', 'generation_mode' => 'automatic', 'generation_timing' => 'lead_time',
        'generation_lead_minutes' => 1440, 'publishing_mode' => 'automatic', 'is_active' => true,
    ], $extra));
}

function at(string $time): void
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse($time, 'Asia/Jakarta'));
}

function run(): array
{
    return app(OfficeScheduledGenerationService::class)->runDue();
}

function scheduledTimes(): array
{
    return OfficeContentItem::orderBy('created_at')->get()->map(fn ($item) => substr($item->metadata['scheduled_at'], 0, 16))->sort()->values()->all();
}

test('a weekly schedule generates its next occurrence even when next_run_at is long past', function () {
    recurring(['frequency' => 'weekly', 'days' => [4], 'time' => '14:00']); // Kamis 14:00
    at('2026-11-04 15:00'); // Rabu 15:00: jendela sehari sebelum Kamis 14:00 sudah terbuka
    expect(run()['generated'])->toBe(1);
    expect(scheduledTimes())->toBe(['2026-11-05T14:00']);
    expect(OfficeCommand::where('agent_id', 'jauki-threads')->where('action', 'generate_threads')->count())->toBe(1);
});

test('running again does not create a second item or command for the same occurrence', function () {
    recurring(['frequency' => 'weekly', 'days' => [4], 'time' => '14:00']);
    at('2026-11-04 15:00');
    run();
    at('2026-11-04 15:05');
    $second = run();
    expect($second['generated'])->toBe(0)->and($second['skipped'])->toBe(1);
    expect(OfficeContentItem::count())->toBe(1)->and(OfficeCommand::count())->toBe(1);
});

test('the following week is generated too, not only the first occurrence', function () {
    recurring(['frequency' => 'weekly', 'days' => [4], 'time' => '14:00']);
    at('2026-11-04 15:00');
    run();
    at('2026-11-11 15:00');
    expect(run()['generated'])->toBe(1);
    expect(scheduledTimes())->toBe(['2026-11-05T14:00', '2026-11-12T14:00']);
});

test('nothing is generated before the lead window opens or for an occurrence that already passed', function () {
    recurring(['frequency' => 'weekly', 'days' => [4], 'time' => '14:00']);
    at('2026-11-04 13:00'); // 25 jam sebelum
    expect(run()['generated'])->toBe(0);
    at('2026-11-05 15:00'); // Kamis 14:00 sudah lewat, kemunculan berikutnya seminggu lagi
    expect(run()['generated'])->toBe(0);
    expect(OfficeContentItem::count())->toBe(0);
});

test('every occurrence whose lead window is open is generated', function () {
    recurring(['frequency' => 'weekly', 'days' => [1, 2], 'time' => '09:00'], ['generation_lead_minutes' => 2880]);
    at('2026-11-01 20:00'); // Minggu malam: Senin 09:00 dan Selasa 09:00 sama-sama dalam 48 jam
    expect(run()['generated'])->toBe(2);
    expect(scheduledTimes())->toBe(['2026-11-02T09:00', '2026-11-03T09:00']);
});

test('a daily schedule prepares tomorrow and skips the time that already passed today', function () {
    recurring(['frequency' => 'daily', 'time' => '09:00']);
    at('2026-11-01 20:00');
    expect(run()['generated'])->toBe(1);
    expect(scheduledTimes())->toBe(['2026-11-02T09:00']);
});

test('an immediate recurring schedule prepares only its next occurrence', function () {
    recurring(['frequency' => 'weekly', 'days' => [1, 3, 5], 'time' => '09:00'], ['generation_timing' => 'immediate', 'generation_lead_minutes' => null]);
    at('2026-11-01 20:00');
    expect(run()['generated'])->toBe(1);
    expect(scheduledTimes())->toBe(['2026-11-02T09:00']);
});

test('schedules without a time or past their end date are ignored', function () {
    recurring(['frequency' => 'weekly', 'days' => [1], 'time' => null]);
    recurring(['frequency' => 'weekly', 'days' => [1], 'time' => '09:00'], ['ends_at' => '2026-10-20 00:00:00']);
    at('2026-11-01 20:00');
    expect(run()['eligible'])->toBe(0);
});

test('one-time schedules still use their own date', function () {
    recurring(['frequency' => 'weekly', 'days' => [1], 'time' => '09:00'], [
        'schedule_type' => 'one_time', 'recurrence_rule' => null, 'scheduled_at' => '2026-11-02 09:00:00', 'next_run_at' => '2026-11-02 09:00:00',
    ]);
    at('2026-11-01 20:00');
    expect(run()['generated'])->toBe(1);
    expect(scheduledTimes())->toBe(['2026-11-02T09:00']);
});

function failLatest(string $error): void
{
    OfficeCommand::query()->update(['status' => 'failed', 'error' => $error]);
    $item = OfficeContentItem::latest('created_at')->first();
    $item->update(['status' => 'failed', 'metadata' => array_merge($item->metadata ?? [], ['generation_error' => $error])]);
}

test('a failed generation is retried a few times with growing pauses, not every five minutes', function () {
    recurring(['frequency' => 'weekly', 'days' => [4], 'time' => '14:00']);
    at('2026-11-04 15:00');
    expect(run()['generated'])->toBe(1);
    failLatest('Tidak ada hasil dari worker.');
    expect(OfficeContentItem::first()->metadata['generation_attempts'])->toBe(1);

    at('2026-11-04 15:05');
    $early = run();
    expect($early['generated'])->toBe(0)->and($early['skipped'])->toBe(1)->and(OfficeCommand::count())->toBe(1);

    at('2026-11-04 15:31'); // 30 menit setelah percobaan pertama
    expect(run()['generated'])->toBe(1);
    expect(OfficeContentItem::first()->metadata['generation_attempts'])->toBe(2);
    failLatest('Tidak ada hasil dari worker.');

    at('2026-11-04 16:00'); // jeda kedua 60 menit belum lewat
    expect(run()['generated'])->toBe(0);
    at('2026-11-04 16:32');
    expect(run()['generated'])->toBe(1);
    failLatest('Tidak ada hasil dari worker.');

    at('2026-11-04 23:00'); // percobaan ketiga sudah habis
    expect(run()['generated'])->toBe(0);
    expect(OfficeCommand::count())->toBe(3);
});

test('credential and budget errors are not retried because they cannot heal on their own', function () {
    recurring(['frequency' => 'weekly', 'days' => [4], 'time' => '14:00']);
    at('2026-11-04 15:00');
    run();
    failLatest('9Router HTTP 403: {"error":{"message":"Workspace lifetime budget of $2.00 exceeded."}}');

    at('2026-11-04 19:00'); // jauh melewati jeda mana pun
    $later = run();
    expect($later['generated'])->toBe(0)->and($later['skipped'])->toBe(1);
    expect(OfficeCommand::count())->toBe(1);

    $schedule = OfficeContentSchedule::first();
    $result = app(OfficeScheduledGenerationService::class)->generateSchedule($schedule, CarbonImmutable::parse('2026-11-05 14:00', 'Asia/Jakarta')->toIso8601String());
    expect($result['reason'])->toBe('retry_blocked_credentials');
});

test('each kind of permanent error is recognised and a plain timeout is not', function () {
    foreach (['Workspace lifetime budget exceeded', 'insufficient credits', 'token API artikel kauiz ditolak (401)', 'situs menolak (HTTP 403)', 'quota habis'] as $error) {
        expect(preg_match('/budget|quota|credit|insufficient|billing|unauthori[sz]ed|invalid api key|rejected its configured credentials|HTTP 40[123]\b|\b40[123]\b/i', $error))->toBe(1, $error);
    }
    foreach (['Office AI provider timed out.', 'koneksi terputus', 'Tidak ada hasil dari bot setelah 60 menit', 'HTTP 429 rate limit', 'HTTP 503'] as $error) {
        expect(preg_match('/budget|quota|credit|insufficient|billing|unauthori[sz]ed|invalid api key|rejected its configured credentials|HTTP 40[123]\b|\b40[123]\b/i', $error))->toBe(0, $error);
    }
});

test('a manual regenerate always works and starts the attempt count again', function () {
    recurring(['frequency' => 'weekly', 'days' => [4], 'time' => '14:00']);
    at('2026-11-04 15:00');
    run();
    failLatest('9Router HTTP 403: Workspace lifetime budget exceeded');
    expect(run()['generated'])->toBe(0);

    $schedule = OfficeContentSchedule::first();
    $result = app(OfficeScheduledGenerationService::class)->generateSchedule($schedule, CarbonImmutable::parse('2026-11-05 14:00', 'Asia/Jakarta')->toIso8601String(), true);
    expect($result['status'])->toBe('generated');
    expect(OfficeContentItem::first()->metadata['generation_attempts'])->toBe(1);
    expect(OfficeCommand::count())->toBe(2);
});
