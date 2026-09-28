<?php

use App\Models\OfficeMemory;
use App\Models\OfficeReport;
use App\Models\User;
use App\Services\OfficeMemoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => config(['services.office_bridge.token' => 'bridge-test-token']));

test('brand context includes only its own and global memories', function () {
    $service = app(OfficeMemoryService::class);
    $service->writeMemory(['brand_id' => 'jauki', 'source_agent' => 'jauki-analyst', 'memory_type' => 'insight', 'title' => 'Jauki', 'summary' => 'Jauki only']);
    $service->writeMemory(['brand_id' => 'kauiz', 'source_agent' => 'jauki-analyst', 'memory_type' => 'insight', 'title' => 'Kauiz', 'summary' => 'Kauiz only']);
    $service->writeMemory(['brand_id' => 'global', 'source_agent' => 'jauki-planner', 'memory_type' => 'decision', 'title' => 'Global', 'summary' => 'For all']);

    expect($service->getRelevantMemories('jauki')->pluck('title')->all())->toEqualCanonicalizing(['Jauki', 'Global']);
    expect($service->getRelevantMemories('kauiz')->pluck('title')->all())->toEqualCanonicalizing(['Kauiz', 'Global']);
});

test('completed structured analyst event persists a report and selected memories', function () {
    $this->postJson('/api/office/events', ['event' => 'task.completed', 'event_id' => 'analyst-report-1', 'agent_id' => 'jauki-analyst', 'result' => [
        'topic' => 'Indonesian AI', 'brand' => 'jauki', 'executive_summary' => 'Demand is increasing.', 'key_findings' => [['title' => 'Video growth', 'summary' => 'Short video engagement rose.']], 'trends' => ['short video'], 'sources' => [['title' => 'Industry source', 'url' => 'https://example.test/source']], 'recommendations' => ['Create a short video'], 'confidence' => 82,
    ]], ['Authorization' => 'Bearer bridge-test-token'])->assertOk();

    expect(OfficeReport::count())->toBe(1)->and(OfficeReport::first()->brand_id)->toBe('jauki')->and(OfficeMemory::count())->toBe(2);
    $this->postJson('/api/office/events', ['event' => 'task.completed', 'event_id' => 'analyst-report-1', 'agent_id' => 'jauki-analyst', 'result' => ['executive_summary' => 'ignored', 'key_findings' => ['ignored']]], ['Authorization' => 'Bearer bridge-test-token'])->assertOk();
    expect(OfficeReport::count())->toBe(1);
});

test('knowledge endpoints require office authentication and expose reports to owners', function () {
    $this->getJson('/office/api/memories')->assertUnauthorized();
    $this->postJson('/api/office/reports', ['agent_id' => 'jauki-analyst', 'result' => []])->assertUnauthorized();
    $owner = User::factory()->create();
    OfficeReport::create(['agent_id' => 'jauki-analyst', 'brand_id' => 'global', 'report_type' => 'trend_research', 'title' => 'Report', 'summary' => 'Summary', 'body_json' => ['key_findings' => []]]);
    $this->actingAs($owner)->getJson('/office/api/reports')->assertOk()->assertJsonPath('data.0.title', 'Report');
    $this->actingAs($owner)->getJson('/office/api/memories/context?brand=kauiz')->assertOk()->assertJsonPath('brand', 'kauiz');
});
