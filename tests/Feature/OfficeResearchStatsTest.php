<?php

namespace Tests\Feature;

use App\Models\OfficeResearchRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeResearchStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_gets_research_chart_totals(): void
    {
        $owner = User::factory()->create(['is_office_owner' => true]);
        OfficeResearchRun::create(['query' => 'AI untuk UMKM', 'status' => 'completed', 'sources' => ['a', 'b', 'c'], 'findings' => ['x'], 'trends' => ['t1'], 'content_ideas' => ['i1', 'i2'], 'created_at' => now()]);
        OfficeResearchRun::create(['query' => 'AI untuk UMKM', 'status' => 'running', 'sources' => ['a'], 'created_at' => now()]);
        OfficeResearchRun::create(['query' => null, 'status' => 'failed', 'sources' => [], 'created_at' => now()->subDay()]);

        $this->actingAs($owner)->getJson('/office/api/research-stats')
            ->assertOk()
            ->assertJsonPath('data.totals.runs', 3)
            ->assertJsonPath('data.totals.completed', 1)
            ->assertJsonPath('data.totals.running', 1)
            ->assertJsonPath('data.totals.failed', 1)
            ->assertJsonPath('data.totals.sources', 4)
            ->assertJsonPath('data.totals.contentIdeas', 2)
            ->assertJsonCount(14, 'data.series')
            ->assertJsonPath('data.series.13.date', now('Asia/Jakarta')->toDateString())
            ->assertJsonPath('data.series.0.date', now('Asia/Jakarta')->subDays(13)->toDateString())
            ->assertJsonPath('data.topics.0.topic', 'AI untuk UMKM');
    }

    public function test_guest_cannot_read_research_stats(): void
    {
        $this->getJson('/office/api/research-stats')->assertStatus(401);
    }
}
