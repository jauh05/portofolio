<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OfficeReelsSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private string $settingsPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['is_office_owner' => true]);
        $this->settingsPath = sys_get_temp_dir().'/reels-settings-'.uniqid().'.json';
        config(['reels.settings_path' => $this->settingsPath]);
    }

    protected function tearDown(): void
    {
        File::delete([$this->settingsPath, $this->settingsPath.'.tmp']);
        parent::tearDown();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'videos_per_week' => 3,
            'durations' => [20, 30],
            'speed_choices' => [1.0, 1.2],
            'skip_head_s' => 5,
            'skip_tail_s' => 5,
            'music_moods' => ['jj', 'chill'],
            'music_volume' => 0.3,
            'brand_hashtags' => ['#jaukiacademy'],
            'services' => ['joki tugas', 'bimbingan belajar'],
        ], $overrides);
    }

    public function test_returns_defaults_when_no_settings_file_exists(): void
    {
        $response = $this->actingAs($this->owner)->getJson('/office/api/reels-settings');

        $response->assertOk()
            ->assertJsonPath('settings.videos_per_week', 4)
            ->assertJsonPath('settings.durations', [20, 25, 30, 35, 40]);
    }

    public function test_saves_valid_settings_and_returns_them_on_next_read(): void
    {
        $this->actingAs($this->owner)->putJson('/office/api/reels-settings', $this->validPayload())
            ->assertOk()
            ->assertJsonPath('settings.videos_per_week', 3)
            ->assertJsonPath('settings.durations', [20, 30]);

        $this->assertFileExists($this->settingsPath);
        $stored = json_decode(File::get($this->settingsPath), true);
        $this->assertEquals([1.0, 1.2], $stored['speed_choices']);
        $this->assertSame(0.3, $stored['music_volume']);

        $this->actingAs($this->owner)->getJson('/office/api/reels-settings')
            ->assertJsonPath('settings.music_moods', ['jj', 'chill']);
    }

    public function test_rejects_duration_outside_allowed_values(): void
    {
        $this->actingAs($this->owner)->putJson('/office/api/reels-settings', $this->validPayload(['durations' => [45]]))
            ->assertStatus(422)->assertJsonValidationErrors(['durations.0']);
    }

    public function test_rejects_speed_outside_allowed_values(): void
    {
        $this->actingAs($this->owner)->putJson('/office/api/reels-settings', $this->validPayload(['speed_choices' => [1.3]]))
            ->assertStatus(422)->assertJsonValidationErrors(['speed_choices.0']);
    }

    public function test_rejects_videos_per_week_outside_range(): void
    {
        $this->actingAs($this->owner)->putJson('/office/api/reels-settings', $this->validPayload(['videos_per_week' => 8]))
            ->assertStatus(422)->assertJsonValidationErrors(['videos_per_week']);
    }

    public function test_rejects_mood_with_uppercase_or_spaces(): void
    {
        $this->actingAs($this->owner)->putJson('/office/api/reels-settings', $this->validPayload(['music_moods' => ['JJ']]))
            ->assertStatus(422)->assertJsonValidationErrors(['music_moods.0']);
    }

    public function test_rejects_brand_hashtag_without_hash_sign(): void
    {
        $this->actingAs($this->owner)->putJson('/office/api/reels-settings', $this->validPayload(['brand_hashtags' => ['jaukiacademy']]))
            ->assertStatus(422)->assertJsonValidationErrors(['brand_hashtags.0']);
    }

    public function test_failed_validation_does_not_touch_existing_file(): void
    {
        $this->actingAs($this->owner)->putJson('/office/api/reels-settings', $this->validPayload())->assertOk();
        $before = File::get($this->settingsPath);

        $this->actingAs($this->owner)->putJson('/office/api/reels-settings', $this->validPayload(['videos_per_week' => 0]))
            ->assertStatus(422);

        $this->assertSame($before, File::get($this->settingsPath));
    }
}
