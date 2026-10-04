<?php

namespace Tests\Feature;

use App\Models\OfficeAnalystReport;
use App\Models\OfficeContentBrand;
use App\Models\OfficeContentPlanItem;
use App\Models\OfficeContentSchedule;
use App\Services\OfficeAiGateway;
use App\Services\OfficeAnalystReportService;
use App\Services\OfficeContentPlannerAiService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OfficeTrentOrchestratorTest extends TestCase
{
    private OfficeContentBrand $brand;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.office_bridge.token' => 'test-bridge-token']);

        // Isolated schema avoids the pre-existing SQLite migration ordering issue.
        foreach (['office_commands', 'office_content_items', 'office_content_plan_items', 'office_content_schedules', 'office_analyst_reports', 'office_content_brands', 'users'] as $table) Schema::dropIfExists($table);
        Schema::create('office_content_brands', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->string('name'); $table->string('slug'); $table->string('default_agent')->nullable(); $table->boolean('is_active')->default(true); $table->timestamps();
        });
        Schema::create('office_analyst_reports', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->date('report_date'); $table->string('report_type'); $table->string('status'); $table->text('summary'); $table->json('metadata')->nullable(); $table->timestamp('generated_at')->nullable(); $table->timestamps();
        });
        Schema::create('office_content_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->uuid('brand_id'); $table->string('name'); $table->string('schedule_type'); $table->string('platform'); $table->string('content_type'); $table->string('topic')->nullable(); $table->text('brief')->nullable(); $table->timestamp('scheduled_at')->nullable(); $table->timestamp('next_run_at')->nullable(); $table->string('timezone')->nullable(); $table->string('generation_mode'); $table->string('generation_timing')->nullable(); $table->integer('generation_lead_minutes')->nullable(); $table->string('publishing_mode'); $table->boolean('is_active')->default(true); $table->json('metadata')->nullable(); $table->softDeletes(); $table->timestamps();
        });
        Schema::create('office_content_plan_items', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->uuid('research_run_id')->nullable(); $table->uuid('brand_id'); $table->uuid('schedule_id')->nullable(); $table->uuid('content_item_id')->nullable(); $table->string('platform'); $table->string('content_type'); $table->string('topic'); $table->text('brief')->nullable(); $table->timestamp('scheduled_at'); $table->string('generation_mode'); $table->string('publishing_mode'); $table->string('status'); $table->json('metadata')->nullable(); $table->timestamps();
        });
        Schema::create('office_content_items', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->uuid('schedule_id'); $table->uuid('brand_id'); $table->string('agent_id'); $table->string('platform'); $table->string('content_type'); $table->string('status'); $table->string('deduplication_key'); $table->string('title')->nullable(); $table->json('metadata')->nullable(); $table->timestamps();
        });
        Schema::create('office_commands', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->string('agent_id'); $table->string('action'); $table->json('payload')->nullable(); $table->string('status'); $table->unsignedBigInteger('requested_by')->nullable(); $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table) { $table->id(); $table->boolean('is_office_owner')->default(false); $table->timestamps(); });
        $this->brand = OfficeContentBrand::create(['name' => 'Jauki', 'slug' => 'jauki']);
    }

    private function proposal(string $topic = 'Tips Belajar', string $timing = 'H-1'): array
    {
        return ['plan' => ['items' => [['schedule_type' => 'one_time', 'platform' => 'instagram', 'content_type' => 'feed', 'topic' => $topic, 'scheduled_at' => '2026-10-10 09:00:00', 'generation_timing' => $timing]]], 'clarifications' => []];
    }

    private function fakeAi(array $proposal, array $intent = ['is_ambiguous' => false]): void
    {
        $this->mock(OfficeAiGateway::class)->shouldReceive('completeJson')->once()->andReturn($intent);
        $this->mock(OfficeContentPlannerAiService::class)->shouldReceive('analyze')->atMost()->once()->andReturn($proposal);
    }

    private function requestIntent(string $message): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders(['X-Office-Bridge-Token' => 'test-bridge-token'])
            ->postJson('/api/office/trent/intent', ['message' => $message, 'workspace_id' => $this->brand->id]);
    }

    public function test_bridge_authentication_and_validation(): void
    {
        $this->postJson('/api/office/trent/intent', ['message' => 'Buat konten'])->assertUnauthorized();
        $this->withHeaders(['X-Office-Bridge-Token' => 'test-bridge-token'])
            ->postJson('/api/office/trent/intent', ['message' => 'x', 'workspace_id' => 'not-a-uuid'])->assertUnprocessable();
    }

    public function test_ambiguous_request_asks_clarification_without_persisting(): void
    {
        $this->fakeAi($this->proposal(), ['is_ambiguous' => true, 'clarification_question' => 'Satu konten per minggu atau beberapa konten minggu depan?']);
        $response = $this->requestIntent('Konten seminggu sekali')->assertOk()->assertJsonPath('status', 'ambiguous');
        $this->assertStringContainsString('Satu konten', $response->json('summary'));
        $this->assertSame(0, OfficeAnalystReport::count());
        $this->assertSame(0, OfficeContentSchedule::count());
    }

    public function test_proposal_selected_approval_and_repeated_approval(): void
    {
        $proposal = $this->proposal();
        $proposal['plan']['items'][] = array_merge($proposal['plan']['items'][0], ['topic' => 'Catatan Belajar', 'scheduled_at' => '2026-10-11 14:00:00']);
        $this->fakeAi($proposal);
        $response = $this->requestIntent('Buat dua feed untuk akhir pekan')->assertOk()->assertJsonPath('status', 'needs_confirmation');
        $this->assertSame(0, OfficeContentSchedule::count());
        $this->assertSame(2, OfficeContentPlanItem::count());
        $report = OfficeAnalystReport::findOrFail($response->json('report_id'));
        $this->assertSame('draft', $report->status);
        $selected = $response->json('items.0.plan_id');
        $this->assertNotEmpty($selected);
        $analyst = app(OfficeAnalystReportService::class);
        $analyst->approve($report, [$selected]);
        $analyst->approve($report->fresh(), [$selected]);
        $this->assertSame(1, OfficeContentSchedule::count());
        $this->assertSame(1, OfficeContentPlanItem::where('status', 'proposed')->count());
        $schedule = OfficeContentSchedule::firstOrFail();
        $this->assertSame('review', $schedule->publishing_mode);
        $this->assertSame('lead_time', $schedule->generation_timing);
        $this->assertSame(1440, $schedule->generation_lead_minutes);
        $this->assertSame(0, \App\Models\OfficeCommand::where('action', 'like', 'publish%')->count());
    }

    public function test_cancel_dismisses_plan_without_creating_schedule(): void
    {
        $this->fakeAi($this->proposal());
        $response = $this->requestIntent('Buat satu feed untuk Sabtu')->assertOk();
        $report = OfficeAnalystReport::findOrFail($response->json('report_id'));
        app(OfficeAnalystReportService::class)->dismiss($report, [$response->json('items.0.plan_id')]);
        $this->assertSame('dismissed', OfficeContentPlanItem::firstOrFail()->status);
        $this->assertSame('reviewed', $report->fresh()->status);
        $this->assertSame(0, OfficeContentSchedule::count());
    }

    public function test_manual_now_h1_and_h2_keep_review_publishing(): void
    {
        foreach ([['manual', 'manual', null, 'manual'], ['sekarang', 'immediate', null, 'automatic'], ['H-1', 'lead_time', 1440, 'automatic'], ['H-2', 'lead_time', 2880, 'automatic']] as [$word, $timing, $lead, $mode]) {
            $this->forgetMock(OfficeAiGateway::class);
            $this->forgetMock(OfficeContentPlannerAiService::class);
            $this->fakeAi($this->proposal('Topik '.$word));
            $response = $this->requestIntent('Buat feed '.$word)->assertOk()->assertJsonPath('status', 'needs_confirmation');
            $plan = OfficeContentPlanItem::findOrFail($response->json('items.0.plan_id'));
            $this->assertSame($mode, $plan->generation_mode);
            $this->assertSame($timing, $plan->metadata['generation_timing']);
            $this->assertSame($lead, $plan->metadata['generation_lead_minutes']);
            $this->assertSame('review', $plan->publishing_mode);
            if ($word === 'manual') {
                app(OfficeAnalystReportService::class)->approve(OfficeAnalystReport::findOrFail($response->json('report_id')), [$plan->id]);
                $this->assertSame('manual', OfficeContentSchedule::where('topic', 'Topik manual')->firstOrFail()->generation_timing);
                $this->assertSame(0, \App\Models\OfficeCommand::count());
            }
            if ($word === 'sekarang') {
                app(OfficeAnalystReportService::class)->approve(OfficeAnalystReport::findOrFail($response->json('report_id')), [$plan->id]);
                $this->assertSame('immediate', OfficeContentSchedule::where('topic', 'Topik sekarang')->firstOrFail()->generation_timing);
                $this->assertSame(1, \App\Models\OfficeCommand::where('action', 'generate_feed')->count());
                $this->assertSame(0, \App\Models\OfficeCommand::where('action', 'like', 'publish%')->count());
            }
        }
    }

    public function test_duplicate_active_schedule_is_skipped(): void
    {
        OfficeContentSchedule::create(['brand_id' => $this->brand->id, 'name' => 'Tips Belajar', 'schedule_type' => 'one_time', 'platform' => 'instagram', 'content_type' => 'feed', 'topic' => 'Tips Belajar', 'scheduled_at' => '2026-10-10 09:00:00', 'is_active' => true, 'generation_mode' => 'manual', 'publishing_mode' => 'review']);
        $this->fakeAi($this->proposal());
        $this->requestIntent('Buat feed Tips Belajar tanggal 10 Oktober')->assertOk()->assertJsonPath('status', 'error');
        $this->assertSame(0, OfficeContentPlanItem::count());
    }
}
