<?php

namespace Tests\Feature;

use App\Models\OfficeContentBrand;
use App\Models\OfficeContentItem;
use App\Models\OfficeContentSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use App\Services\OfficeRegistry;
use App\Models\OfficeCommand;

class LivingOfficePublishingDeadlineTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private OfficeContentBrand $brand;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['is_office_owner' => true]);
        $this->brand = OfficeContentBrand::create([
            'id' => (string)\Illuminate\Support\Str::uuid(),
            'name' => 'Jauki Test',
            'slug' => 'jauki-test',
        ]);
        Carbon::setTestNow('2026-10-10 14:00:00');
    }

    public function test_automatic_approved_early_does_not_publish_immediately()
    {
        $schedule = OfficeContentSchedule::create([
            'id' => (string)\Illuminate\Support\Str::uuid(),
            'name' => 'Test Schedule',
            'schedule_type' => 'one_time',
            'platform' => 'instagram',
            'content_type' => 'feed',
            'brand_id' => $this->brand->id,
            'scheduled_at' => Carbon::parse('2026-10-10 18:00:00', 'Asia/Jakarta'),
            'publishing_mode' => 'automatic',
            'generation_mode' => 'manual',
            'timezone' => 'Asia/Jakarta',
        ]);

        $item = OfficeContentItem::create([
            'id' => (string)\Illuminate\Support\Str::uuid(),
            'schedule_id' => $schedule->id,
            'brand_id' => $this->brand->id,
            'platform' => 'instagram',
            'content_type' => 'feed',
            'status' => 'ready_for_review',
            'metadata' => ['scheduled_at' => '2026-10-10T18:00:00+07:00'],
            'agent_id' => 'jauki-social',
            'deduplication_key' => 'test-1',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-10 15:00:00', 'Asia/Jakarta'));
        
        $response = $this->actingAs($this->owner)
                         ->postJson("/office/api/content/{$item->id}/approve");

        $response->assertOk();
        $this->assertEquals('approved', $item->fresh()->status);
        $this->assertNull($item->fresh()->published_at);

        // Run scheduler at 15:00
        app(\App\Http\Controllers\OfficeContentPlannerController::class)->dispatchScheduledPublishing();
        $this->assertEquals('approved', $item->fresh()->status);

        // Run scheduler at 17:59
        Carbon::setTestNow(Carbon::parse('2026-10-10 17:59:00', 'Asia/Jakarta'));
        app(\App\Http\Controllers\OfficeContentPlannerController::class)->dispatchScheduledPublishing();
        $this->assertEquals('approved', $item->fresh()->status);

        // Run scheduler at 18:00
        Carbon::setTestNow(Carbon::parse('2026-10-10 18:00:00', 'Asia/Jakarta'));
        app(\App\Http\Controllers\OfficeContentPlannerController::class)->dispatchScheduledPublishing();
        
        $this->assertEquals('publishing', $item->fresh()->status);
        $this->assertDatabaseHas('office_commands', [
            'action' => 'publish_last',
            'agent_id' => 'jauki-social'
        ]);
    }

    public function test_automatic_never_approved_publishes_at_deadline()
    {
        $schedule = OfficeContentSchedule::create([
            'id' => (string)\Illuminate\Support\Str::uuid(),
            'name' => 'Test Schedule',
            'schedule_type' => 'one_time',
            'platform' => 'instagram',
            'content_type' => 'feed',
            'brand_id' => $this->brand->id,
            'scheduled_at' => Carbon::parse('2026-10-10 18:00:00', 'Asia/Jakarta'),
            'publishing_mode' => 'automatic',
            'generation_mode' => 'manual',
            'timezone' => 'Asia/Jakarta',
        ]);

        $item = OfficeContentItem::create([
            'id' => (string)\Illuminate\Support\Str::uuid(),
            'schedule_id' => $schedule->id,
            'brand_id' => $this->brand->id,
            'platform' => 'instagram',
            'content_type' => 'feed',
            'status' => 'ready_for_review',
            'metadata' => ['scheduled_at' => '2026-10-10T18:00:00+07:00'],
            'agent_id' => 'jauki-social',
            'deduplication_key' => 'test-2',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-10 17:59:00', 'Asia/Jakarta'));
        app(\App\Http\Controllers\OfficeContentPlannerController::class)->dispatchScheduledPublishing();
        $this->assertEquals('ready_for_review', $item->fresh()->status);

        Carbon::setTestNow(Carbon::parse('2026-10-10 18:00:00', 'Asia/Jakarta'));
        app(\App\Http\Controllers\OfficeContentPlannerController::class)->dispatchScheduledPublishing();
        
        $this->assertEquals('publishing', $item->fresh()->status);
    }
    
    public function test_review_approved_early_publishes_at_deadline()
    {
        $schedule = OfficeContentSchedule::create([
            'id' => (string)\Illuminate\Support\Str::uuid(),
            'name' => 'Test Schedule',
            'schedule_type' => 'one_time',
            'platform' => 'instagram',
            'content_type' => 'feed',
            'brand_id' => $this->brand->id,
            'scheduled_at' => Carbon::parse('2026-10-10 18:00:00', 'Asia/Jakarta'),
            'publishing_mode' => 'review',
            'generation_mode' => 'manual',
            'timezone' => 'Asia/Jakarta',
        ]);

        $item = OfficeContentItem::create([
            'id' => (string)\Illuminate\Support\Str::uuid(),
            'schedule_id' => $schedule->id,
            'brand_id' => $this->brand->id,
            'platform' => 'instagram',
            'content_type' => 'feed',
            'status' => 'ready_for_review',
            'metadata' => ['scheduled_at' => '2026-10-10T18:00:00+07:00'],
            'agent_id' => 'jauki-social',
            'deduplication_key' => 'test-3',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-10 15:00:00', 'Asia/Jakarta'));
        $this->actingAs($this->owner)->postJson("/office/api/content/{$item->id}/approve");

        Carbon::setTestNow(Carbon::parse('2026-10-10 17:59:00', 'Asia/Jakarta'));
        app(\App\Http\Controllers\OfficeContentPlannerController::class)->dispatchScheduledPublishing();
        $this->assertEquals('approved', $item->fresh()->status);

        Carbon::setTestNow(Carbon::parse('2026-10-10 18:00:00', 'Asia/Jakarta'));
        app(\App\Http\Controllers\OfficeContentPlannerController::class)->dispatchScheduledPublishing();
        $this->assertEquals('publishing', $item->fresh()->status);
    }
    
    public function test_review_never_approved_does_not_publish()
    {
        $schedule = OfficeContentSchedule::create([
            'id' => (string)\Illuminate\Support\Str::uuid(),
            'name' => 'Test Schedule',
            'schedule_type' => 'one_time',
            'platform' => 'instagram',
            'content_type' => 'feed',
            'brand_id' => $this->brand->id,
            'scheduled_at' => Carbon::parse('2026-10-10 18:00:00', 'Asia/Jakarta'),
            'publishing_mode' => 'review',
            'generation_mode' => 'manual',
            'timezone' => 'Asia/Jakarta',
        ]);

        $item = OfficeContentItem::create([
            'id' => (string)\Illuminate\Support\Str::uuid(),
            'schedule_id' => $schedule->id,
            'brand_id' => $this->brand->id,
            'platform' => 'instagram',
            'content_type' => 'feed',
            'status' => 'ready_for_review',
            'metadata' => ['scheduled_at' => '2026-10-10T18:00:00+07:00'],
            'agent_id' => 'jauki-social',
            'deduplication_key' => 'test-4',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-10 18:00:00', 'Asia/Jakarta'));
        app(\App\Http\Controllers\OfficeContentPlannerController::class)->dispatchScheduledPublishing();
        $this->assertEquals('ready_for_review', $item->fresh()->status);
    }
    
    public function test_scheduler_runs_twice_is_idempotent()
    {
        $schedule = OfficeContentSchedule::create([
            'id' => (string)\Illuminate\Support\Str::uuid(),
            'name' => 'Test Schedule',
            'schedule_type' => 'one_time',
            'platform' => 'instagram',
            'content_type' => 'feed',
            'brand_id' => $this->brand->id,
            'scheduled_at' => Carbon::parse('2026-10-10 18:00:00', 'Asia/Jakarta'),
            'publishing_mode' => 'automatic',
            'generation_mode' => 'manual',
            'timezone' => 'Asia/Jakarta',
        ]);

        $item = OfficeContentItem::create([
            'id' => (string)\Illuminate\Support\Str::uuid(),
            'schedule_id' => $schedule->id,
            'brand_id' => $this->brand->id,
            'platform' => 'instagram',
            'content_type' => 'feed',
            'status' => 'ready_for_review',
            'metadata' => ['scheduled_at' => '2026-10-10T18:00:00+07:00'],
            'agent_id' => 'jauki-social',
            'deduplication_key' => 'test-5',
        ]);
        
        Carbon::setTestNow(Carbon::parse('2026-10-10 18:00:00', 'Asia/Jakarta'));
        app(\App\Http\Controllers\OfficeContentPlannerController::class)->dispatchScheduledPublishing();
        
        $this->assertEquals('publishing', $item->fresh()->status);
        $this->assertDatabaseCount('office_commands', 1);
        
        Carbon::setTestNow(Carbon::parse('2026-10-10 18:01:00', 'Asia/Jakarta'));
        app(\App\Http\Controllers\OfficeContentPlannerController::class)->dispatchScheduledPublishing();
        
        // No duplicate command should be created since status is already 'publishing'
        $this->assertDatabaseCount('office_commands', 1);
    }
}
