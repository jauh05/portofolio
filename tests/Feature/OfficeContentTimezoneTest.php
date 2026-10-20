<?php

namespace Tests\Feature;

use App\Models\OfficeContentBrand;
use App\Models\OfficeContentPlanItem;
use App\Models\OfficeContentSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OfficeContentTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_jakarta_wall_time_is_stored_as_utc_and_shown_in_jakarta(): void
    {
        $brand = OfficeContentBrand::where('slug', 'jauki')->firstOrFail();
        $schedule = OfficeContentSchedule::create([
            'brand_id' => $brand->id, 'name' => 'Timezone probe', 'schedule_type' => 'one_time',
            'platform' => 'instagram', 'content_type' => 'feed', 'timezone' => 'Asia/Jakarta', 'is_active' => true,
            'scheduled_at' => CarbonImmutable::parse('2026-10-21 10:00', 'Asia/Jakarta'),
            'generation_mode' => 'manual', 'generation_timing' => 'manual', 'publishing_mode' => 'review',
        ]);

        $this->assertSame('2026-10-21 03:00:00', DB::table('office_content_schedules')->where('id', $schedule->id)->value('scheduled_at'));

        $owner = User::factory()->create(['is_office_owner' => true]);
        $this->actingAs($owner)->getJson('/office/api/content-planner?start=2026-10-21&end=2026-10-21')
            ->assertOk()
            ->assertJsonPath('occurrences.0.scheduledAt', '2026-10-21T10:00:00+07:00');
    }

    public function test_plan_items_store_wall_time_from_the_proposal_as_utc(): void
    {
        $brand = OfficeContentBrand::where('slug', 'jauki')->firstOrFail();
        $item = OfficeContentPlanItem::create([
            'brand_id' => $brand->id, 'platform' => 'threads', 'content_type' => 'thread', 'topic' => 'Tips',
            'scheduled_at' => '2026-10-22 19:00:00', 'status' => 'proposed',
        ]);

        $this->assertSame('2026-10-22 12:00:00', DB::table('office_content_plan_items')->where('id', $item->id)->value('scheduled_at'));
        $this->assertSame('2026-10-22 19:00:00', $item->fresh()->scheduled_at->copy()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
    }
}
