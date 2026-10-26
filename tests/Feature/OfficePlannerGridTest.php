<?php

namespace Tests\Feature;

use App\Models\OfficeContentBrand;
use App\Models\OfficeContentItem;
use App\Models\OfficeContentSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfficePlannerGridTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['is_office_owner' => true]);
        $this->dir = sys_get_temp_dir().'/planner-grid-'.uniqid();
        File::ensureDirectoryExists($this->dir);
        config([
            'reels.slots_path' => $this->dir.'/reels-slots.json',
            'reels.history_path' => $this->dir.'/history.json',
            'reels.public_base_url' => 'https://example.test/reels',
            'kauiz.slots_path' => $this->dir.'/kauiz-slots.json',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function brand(string $slug, string $name): OfficeContentBrand
    {
        return OfficeContentBrand::firstOrCreate(['slug' => $slug], ['id' => (string) Str::uuid(), 'name' => $name]);
    }

    public function test_reels_slot_can_be_created_moved_and_deleted(): void
    {
        $created = $this->actingAs($this->owner)->postJson('/office/api/reels-slots', [
            'label' => 'Reels Jauki', 'day' => 1, 'time' => '16:00',
        ])->assertCreated()->json('slot');

        $this->assertSame(120, $created['lead_minutes']);
        $this->assertSame('jauki', $created['brand']);

        $this->actingAs($this->owner)->patchJson("/office/api/reels-slots/{$created['id']}", [
            'day' => 4, 'time' => '19:30',
        ])->assertOk()->assertJsonPath('slot.day', 4)->assertJsonPath('slot.time', '19:30');

        $this->actingAs($this->owner)->getJson('/office/api/reels-slots')
            ->assertJsonPath('slots.0.day', 4)->assertJsonPath('slots.0.lead_minutes', 120);

        $this->actingAs($this->owner)->deleteJson("/office/api/reels-slots/{$created['id']}")->assertOk();
        $this->actingAs($this->owner)->getJson('/office/api/reels-slots')->assertJsonPath('slots', []);
    }

    public function test_reels_slot_rejects_bad_day_and_time(): void
    {
        $this->actingAs($this->owner)->postJson('/office/api/reels-slots', ['label' => 'x', 'day' => 8, 'time' => '16:00'])
            ->assertStatus(422)->assertJsonValidationErrors(['day']);
        $this->actingAs($this->owner)->postJson('/office/api/reels-slots', ['label' => 'x', 'day' => 2, 'time' => '25:00'])
            ->assertStatus(422)->assertJsonValidationErrors(['time']);
    }

    public function test_kauiz_schedules_are_written_as_slots_for_worker(): void
    {
        $kauiz = $this->brand('kauiz', 'Kauiz');
        $jauki = $this->brand('jauki', 'Jauki');

        OfficeContentSchedule::create([
            'brand_id' => $kauiz->id, 'name' => 'Feed Kauiz', 'schedule_type' => 'recurring', 'platform' => 'instagram',
            'content_type' => 'feed', 'timezone' => 'Asia/Jakarta', 'is_active' => true,
            'recurrence_rule' => ['frequency' => 'weekly', 'days' => [1, 3], 'time' => '09:00'],
            'generation_mode' => 'manual', 'publishing_mode' => 'automatic',
        ]);
        OfficeContentSchedule::create([
            'brand_id' => $jauki->id, 'name' => 'Feed Jauki', 'schedule_type' => 'recurring', 'platform' => 'instagram',
            'content_type' => 'feed', 'timezone' => 'Asia/Jakarta', 'is_active' => true,
            'recurrence_rule' => ['frequency' => 'weekly', 'days' => [2], 'time' => '16:00'],
            'generation_mode' => 'manual', 'publishing_mode' => 'automatic',
        ]);

        $slots = json_decode(File::get($this->dir.'/kauiz-slots.json'), true);
        $this->assertCount(2, $slots);
        $this->assertSame([1, 3], array_column($slots, 'day'));
        $this->assertSame(['09:00', '09:00'], array_column($slots, 'time'));
        $this->assertSame(['feed', 'feed'], array_column($slots, 'format'));
    }

    public function test_content_results_merge_database_items_and_reels_history(): void
    {
        File::put($this->dir.'/history.json', json_encode([[
            'id' => 'abc12345', 'created_at' => '2026-10-26T16:00:00+07:00', 'overlay' => 'Teks uji', 'caption' => 'Caption uji',
            'output' => 'reel_uji.mp4', 'instagram_media_id' => '1799', 'post_error' => null,
        ]]));
        $brand = $this->brand('jauki', 'Jauki');
        OfficeContentItem::create([
            'deduplication_key' => 'k1', 'agent_id' => 'jauki-social', 'platform' => 'instagram', 'content_type' => 'feed',
            'brand_id' => $brand->id, 'title' => 'Feed uji', 'status' => 'failed',
            'metadata' => ['scheduled_at' => '2026-10-27T09:00:00+07:00', 'publish_error' => 'token kedaluwarsa'],
        ]);

        $rows = $this->actingAs($this->owner)->getJson('/office/api/content-results')->assertOk()->json('results');

        $this->assertCount(2, $rows);
        $reels = collect($rows)->firstWhere('source', 'reels');
        $this->assertSame('published', $reels['status']);
        $this->assertSame('https://example.test/reels/reel_uji.mp4', $reels['videoUrl']);
        $feed = collect($rows)->firstWhere('source', 'db');
        $this->assertSame('failed', $feed['status']);
        $this->assertSame('token kedaluwarsa', $feed['error']);
    }
}
