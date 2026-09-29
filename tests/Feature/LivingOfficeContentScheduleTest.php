<?php

namespace Tests\Feature;

use App\Models\OfficeContentBrand;
use App\Models\OfficeContentItem;
use App\Models\OfficeContentSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LivingOfficeContentScheduleTest extends TestCase
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
    }

    public function test_can_edit_one_time_schedule()
    {
        $schedule = OfficeContentSchedule::create([
            'brand_id' => $this->brand->id,
            'name' => 'Old Post',
            'schedule_type' => 'one_time',
            'platform' => 'instagram',
            'content_type' => 'post',
            'scheduled_at' => Carbon::now('Asia/Jakarta')->addDays(2),
            'generation_mode' => 'manual',
            'publishing_mode' => 'review',
            'is_active' => true,
        ]);

        $this->actingAs($this->owner)
            ->patchJson("/office/api/content-schedules/{$schedule->id}", [
                'name' => 'New Post',
                'schedule_type' => 'one_time',
                'brand_id' => $this->brand->id,
                'platform' => 'instagram',
                'content_type' => 'post',
                'scheduled_at' => Carbon::now('Asia/Jakarta')->addDays(3)->format('Y-m-d H:i:s'),
                'generation_mode' => 'manual',
                'publishing_mode' => 'review',
            ])
            ->assertOk();

        $schedule->refresh();
        $this->assertEquals('New Post', $schedule->name);
        $this->assertNotNull($schedule->next_run_at);
    }

    public function test_can_edit_recurring_schedule_and_recalculate_next_run()
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29 10:00:00', 'Asia/Jakarta')); // Tuesday

        $schedule = OfficeContentSchedule::create([
            'brand_id' => $this->brand->id,
            'name' => 'Weekly Post',
            'schedule_type' => 'recurring',
            'platform' => 'instagram',
            'content_type' => 'post',
            'recurrence_rule' => ['frequency' => 'weekly', 'days' => [1], 'time' => '09:00'],
            'starts_at' => Carbon::parse('2026-09-28 00:00:00', 'Asia/Jakarta'), // Monday
            'generation_mode' => 'manual',
            'publishing_mode' => 'review',
            'is_active' => true,
        ]);
        
        $this->actingAs($this->owner)
            ->patchJson("/office/api/content-schedules/{$schedule->id}", [
                'name' => 'Weekly Post Updated',
                'schedule_type' => 'recurring',
                'brand_id' => $this->brand->id,
                'platform' => 'instagram',
                'content_type' => 'post',
                'frequency' => 'weekly',
                'days' => [3], // Wednesday
                'time' => '19:00',
                'starts_at' => '2026-09-28',
                'generation_mode' => 'manual',
                'publishing_mode' => 'review',
            ])
            ->assertOk();

        $schedule->refresh();
        $this->assertEquals([3], $schedule->recurrence_rule['days']);
        $this->assertEquals('19:00', $schedule->recurrence_rule['time']);
        $this->assertEquals('2026-09-30 19:00:00', $schedule->next_run_at->format('Y-m-d H:i:s'));
    }

    public function test_can_pause_and_resume_schedule()
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29 10:00:00', 'Asia/Jakarta'));

        $schedule = OfficeContentSchedule::create([
            'brand_id' => $this->brand->id,
            'name' => 'Active Post',
            'schedule_type' => 'one_time',
            'platform' => 'instagram',
            'content_type' => 'post',
            'scheduled_at' => Carbon::parse('2026-09-30 10:00:00', 'Asia/Jakarta'),
            'next_run_at' => Carbon::parse('2026-09-30 10:00:00', 'Asia/Jakarta'),
            'generation_mode' => 'manual',
            'publishing_mode' => 'review',
            'is_active' => true,
        ]);

        $this->actingAs($this->owner)
            ->patchJson("/office/api/content-schedules/{$schedule->id}", [
                'is_active' => false,
            ])
            ->assertOk();

        $schedule->refresh();
        $this->assertFalse($schedule->is_active);
        $this->assertNull($schedule->next_run_at);

        $this->actingAs($this->owner)
            ->patchJson("/office/api/content-schedules/{$schedule->id}", [
                'is_active' => true,
            ])
            ->assertOk();

        $schedule->refresh();
        $this->assertTrue($schedule->is_active);
        $this->assertNotNull($schedule->next_run_at);
        $this->assertEquals('2026-09-30 10:00:00', $schedule->next_run_at->format('Y-m-d H:i:s'));
    }

    public function test_can_delete_schedule()
    {
        $schedule = OfficeContentSchedule::create([
            'brand_id' => $this->brand->id,
            'name' => 'To Delete',
            'schedule_type' => 'one_time',
            'platform' => 'instagram',
            'content_type' => 'post',
            'scheduled_at' => Carbon::now('Asia/Jakarta')->addDays(2),
            'generation_mode' => 'manual',
            'publishing_mode' => 'review',
            'is_active' => true,
        ]);

        $this->actingAs($this->owner)
            ->deleteJson("/office/api/content-schedules/{$schedule->id}")
            ->assertOk();

        $schedule->refresh();
        $this->assertFalse($schedule->is_active);
        $this->assertNull($schedule->next_run_at);
    }
}
