<?php

namespace Tests\Feature;

use App\Models\OfficeContentBrand;
use App\Models\User;
use App\Services\OfficeAiGatewayException;
use App\Services\OfficeTrentOrchestratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeContentPlannerChatTest extends TestCase
{
    use RefreshDatabase;

    private function brand(): OfficeContentBrand
    {
        return OfficeContentBrand::where('slug', 'jauki')->firstOrFail();
    }

    public function test_guest_and_non_owner_cannot_use_the_chat(): void
    {
        $this->postJson('/office/api/content-planner/chat', ['message' => 'Buat konten besok'])->assertStatus(401);

        $member = User::factory()->create(['is_office_owner' => false]);
        $this->actingAs($member)->postJson('/office/api/content-planner/chat', ['message' => 'Buat konten besok'])->assertForbidden();
    }

    public function test_history_is_joined_into_the_message_for_clarification_turns(): void
    {
        $owner = User::factory()->create(['is_office_owner' => true]);
        $brand = $this->brand();

        $this->mock(OfficeTrentOrchestratorService::class, function ($mock) use ($brand) {
            $mock->shouldReceive('processIntent')
                ->once()
                ->withArgs(fn (string $message, string $workspaceId) => $message === "Buat konten Threads\nBesok jam 10" && $workspaceId === $brand->id)
                ->andReturn(['status' => 'ambiguous', 'summary' => 'Topiknya apa?', 'items' => []]);
        });

        $this->actingAs($owner)->postJson('/office/api/content-planner/chat', [
            'message' => 'Besok jam 10',
            'history' => ['Buat konten Threads'],
        ])->assertOk()
            ->assertJsonPath('status', 'ambiguous')
            ->assertJsonPath('summary', 'Topiknya apa?');
    }

    public function test_ai_provider_failure_becomes_a_friendly_error_status(): void
    {
        $owner = User::factory()->create(['is_office_owner' => true]);
        $this->brand();

        $this->mock(OfficeTrentOrchestratorService::class, function ($mock) {
            $mock->shouldReceive('processIntent')->andThrow(new OfficeAiGatewayException('Office AI is not configured yet.', 422));
        });

        $this->actingAs($owner)->postJson('/office/api/content-planner/chat', ['message' => 'Buat konten besok'])
            ->assertOk()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Office AI is not configured yet.');
    }

    public function test_missing_workspace_returns_error_without_calling_ai(): void
    {
        $owner = User::factory()->create(['is_office_owner' => true]);
        OfficeContentBrand::query()->delete();

        $this->actingAs($owner)->postJson('/office/api/content-planner/chat', ['message' => 'Buat konten besok'])
            ->assertOk()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Workspace tidak ditemukan.');
    }
}
