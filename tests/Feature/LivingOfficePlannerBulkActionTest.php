<?php

namespace Tests\Feature;

use App\Models\OfficeContentBrand;
use App\Models\OfficeContentItem;
use App\Models\OfficeContentSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LivingOfficePlannerBulkActionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private OfficeContentBrand $brand;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['is_office_owner' => true]);
        $this->brand = OfficeContentBrand::create(['name' => 'Jauki', 'slug' => 'jauki']);
    }

    public function test_manual_ai_and_analyst_schedules_are_listed_for_management(): void
    {
        $this->schedule('Manual', ['source' => 'manual']);
        $this->schedule('AI', ['source' => 'ai_plan', 'ai_plan_batch_id' => 'batch-ai']);
        $this->schedule('Analyst', ['source' => 'analyst', 'analyst_report_id' => 'report-1']);
        $paused = $this->schedule('Paused', ['source' => 'manual']);
        $paused->update(['is_active' => false, 'next_run_at' => null]);

        $response = $this->actingAs($this->owner)->getJson('/office/api/content-planner');

        $response->assertOk()->assertJsonCount(4, 'schedules');
    }

    public function test_bulk_pause_resume_and_delete_preserve_history(): void
    {
        $schedule = $this->schedule('Bulk', ['source' => 'analyst', 'research_run_id' => 'research-1']);
        $content = OfficeContentItem::create([
            'schedule_id' => $schedule->id,
            'brand_id' => $this->brand->id,
            'agent_id' => 'jauki-social',
            'platform' => 'instagram',
            'content_type' => 'feed',
            'status' => 'ready_for_review',
            'deduplication_key' => 'history-1',
        ]);

        $this->actingAs($this->owner)->postJson('/office/api/content-schedules/bulk-action', [
            'action' => 'pause',
            'schedule_ids' => [$schedule->id],
        ])->assertOk()->assertJson(['affected' => 1]);
        $this->assertFalse($schedule->fresh()->is_active);

        $this->actingAs($this->owner)->postJson('/office/api/content-schedules/bulk-action', [
            'action' => 'resume',
            'schedule_ids' => [$schedule->id],
        ])->assertOk();
        $this->assertTrue($schedule->fresh()->is_active);

        $this->actingAs($this->owner)->postJson('/office/api/content-schedules/bulk-action', [
            'action' => 'delete',
            'schedule_ids' => [$schedule->id],
        ])->assertOk();

        $this->assertNotNull(OfficeContentSchedule::withTrashed()->find($schedule->id)->deleted_at);
        $this->assertNotNull($content->fresh());
        $this->assertSame('research-1', OfficeContentSchedule::withTrashed()->find($schedule->id)->metadata['research_run_id']);
    }

    public function test_upcoming_is_scoped_to_requested_month(): void
    {
        $this->schedule('September', [], '2026-09-30 10:00:00');
        $this->schedule('October', [], '2026-10-02 10:00:00');

        $response = $this->actingAs($this->owner)->getJson('/office/api/content-planner?start=2026-10-01&end=2026-10-31');

        $response->assertOk();
        $this->assertCount(1, $response->json('upcoming'));
        $this->assertSame('October', $response->json('upcoming.0.name'));
    }

    private function schedule(string $name, array $metadata = [], string $scheduledAt = '2026-10-02 10:00:00'): OfficeContentSchedule
    {
        return OfficeContentSchedule::create([
            'brand_id' => $this->brand->id,
            'name' => $name,
            'schedule_type' => 'one_time',
            'platform' => 'instagram',
            'content_type' => 'feed',
            'topic' => $name,
            'scheduled_at' => Carbon::parse($scheduledAt, 'Asia/Jakarta'),
            'next_run_at' => Carbon::parse($scheduledAt, 'Asia/Jakarta'),
            'generation_mode' => 'manual',
            'publishing_mode' => 'review',
            'is_active' => true,
            'metadata' => $metadata,
        ]);
    }
}
