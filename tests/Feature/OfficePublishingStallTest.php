<?php

namespace Tests\Feature;

use App\Http\Controllers\OfficeContentPlannerController;
use App\Models\OfficeCommand;
use App\Models\OfficeContentBrand;
use App\Models\OfficeContentItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfficePublishingStallTest extends TestCase
{
    use RefreshDatabase;

    private OfficeContentBrand $brand;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.office_bridge.token' => 'secret']);
        $this->owner = User::factory()->create(['is_office_owner' => true]);
        $this->brand = OfficeContentBrand::firstOrCreate(['slug' => 'jauki'], ['id' => (string) Str::uuid(), 'name' => 'Jauki']);
    }

    public function test_bot_reporting_failure_marks_the_waiting_content_as_failed(): void
    {
        $item = $this->item('publishing');
        $command = $this->command($item, 'running');

        $this->patchJson('/api/office/commands/'.$command->id, ['status' => 'failed', 'error' => 'Login Instagram gagal'], ['X-Office-Bridge-Token' => 'secret'])
            ->assertOk();

        $this->assertSame('failed', $item->fresh()->status);
        $this->assertSame('Login Instagram gagal', $item->fresh()->metadata['publish_error']);
    }

    public function test_publishing_without_a_result_for_an_hour_is_marked_failed_together_with_its_command(): void
    {
        $item = $this->item('publishing');
        $item->timestamps = false;
        $item->updated_at = now()->subMinutes(61);
        $item->save();
        $command = $this->command($item, 'queued');

        app(OfficeContentPlannerController::class)->dispatchScheduledPublishing();

        $this->assertSame('failed', $item->fresh()->status);
        $this->assertSame('failed', $command->fresh()->status);
        $this->assertArrayHasKey('publish_error', $item->fresh()->metadata);
    }

    public function test_recent_publishing_is_left_alone(): void
    {
        $item = $this->item('publishing');
        $command = $this->command($item, 'queued');

        app(OfficeContentPlannerController::class)->dispatchScheduledPublishing();

        $this->assertSame('publishing', $item->fresh()->status);
        $this->assertSame('queued', $command->fresh()->status);
    }

    public function test_bot_reporting_a_failed_generation_marks_the_content_as_failed_so_it_can_be_regenerated(): void
    {
        $item = $this->item('queued_for_generation');
        $command = $this->command($item, 'running', 'generate_feed');

        $this->patchJson('/api/office/commands/'.$command->id, ['status' => 'failed', 'error' => 'Batas gambar harian tercapai (10/10).'], ['X-Office-Bridge-Token' => 'secret'])
            ->assertOk();

        $this->assertSame('failed', $item->fresh()->status);
        $this->assertSame('Batas gambar harian tercapai (10/10).', $item->fresh()->metadata['generation_error']);
    }

    public function test_generation_without_a_result_for_an_hour_is_marked_failed_together_with_its_command(): void
    {
        $item = $this->item('queued_for_generation');
        $item->timestamps = false;
        $item->updated_at = now()->subMinutes(61);
        $item->save();
        $command = $this->command($item, 'queued', 'generate_feed');

        app(OfficeContentPlannerController::class)->dispatchScheduledPublishing();

        $this->assertSame('failed', $item->fresh()->status);
        $this->assertSame('failed', $command->fresh()->status);
        $this->assertArrayHasKey('generation_error', $item->fresh()->metadata);
    }

    private function item(string $status): OfficeContentItem
    {
        return OfficeContentItem::create([
            'deduplication_key' => (string) Str::uuid(), 'agent_id' => 'jauki-social', 'platform' => 'instagram', 'content_type' => 'feed',
            'brand_id' => $this->brand->id, 'title' => 'Tes', 'status' => $status, 'metadata' => [],
        ]);
    }

    private function command(OfficeContentItem $item, string $status, string $action = 'publish_last'): OfficeCommand
    {
        return OfficeCommand::create([
            'agent_id' => 'jauki-social', 'action' => $action, 'payload' => ['content_id' => $item->id], 'status' => $status,
            'requested_by' => $this->owner->id,
        ]);
    }
}
