<?php

use App\Models\OfficeCommand;
use App\Models\OfficeContentBrand;
use App\Models\OfficeContentItem;
use App\Models\OfficeContentSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    config(['services.office_bridge.token' => 'bridge-test-token']);
});

function schedule(string $brandSlug, string $platform, string $type, string $name): OfficeContentSchedule
{
    $brand = OfficeContentBrand::where('slug', $brandSlug)->firstOrFail();
    $at = now('Asia/Jakarta')->addDay()->setTime(19, 0);
    return OfficeContentSchedule::create([
        'name' => $name, 'topic' => $name, 'schedule_type' => 'one_time', 'platform' => $platform, 'content_type' => $type,
        'brand_id' => $brand->id, 'timezone' => 'Asia/Jakarta', 'scheduled_at' => $at, 'next_run_at' => $at, 'generation_mode' => 'manual',
        'publishing_mode' => 'review', 'is_active' => true,
    ]);
}

test('each schedule card keeps its own content when two brands and platforms share the same time slot', function () {
    $owner = User::factory()->create(['is_office_owner' => true]);
    $kauizFeed = schedule('kauiz', 'instagram', 'feed', 'Kauiz feed');
    $jaukiThreads = schedule('jauki', 'threads', 'thread', 'Jauki threads');
    $kauizArticle = schedule('kauiz', 'article', 'article', 'Kauiz artikel');

    foreach ([$kauizFeed, $jaukiThreads, $kauizArticle] as $item) {
        $this->actingAs($owner)->postJson('/office/api/content-schedules/'.$item->id.'/generate')->assertOk()->assertJsonPath('status', 'generated');
    }

    $commands = OfficeCommand::orderBy('created_at')->get()->keyBy(fn ($command) => $command->action);
    expect($commands->keys()->sort()->values()->all())->toBe(['generate_article', 'generate_feed', 'generate_threads']);
    expect($commands['generate_threads']->agent_id)->toBe('jauki-threads');
    expect($commands['generate_article']->agent_id)->toBe('jauki-article');
    expect($commands['generate_article']->payload['brand'])->toBe('Kauiz');
    expect($commands['generate_threads']->payload['brand'])->toBe('Jauki');

    $threadsItem = OfficeContentItem::findOrFail($commands['generate_threads']->payload['content_id']);
    $feedItem = OfficeContentItem::findOrFail($commands['generate_feed']->payload['content_id']);
    $articleItem = OfficeContentItem::findOrFail($commands['generate_article']->payload['content_id']);
    expect([$threadsItem->schedule_id, $feedItem->schedule_id, $articleItem->schedule_id])->toBe([$jaukiThreads->id, $kauizFeed->id, $kauizArticle->id]);

    $post = fn (array $body) => $this->postJson('/api/office/events', $body, ['Authorization' => 'Bearer bridge-test-token'])->assertOk();
    $post(['event' => 'content.preview_ready', 'event_id' => 'e-feed', 'agent_id' => 'jauki-social', 'content' => [
        'platform' => 'instagram', 'content_type' => 'feed', 'content_id' => $feedItem->id, 'title' => 'Feed Kauiz', 'text' => 'Caption feed', 'image_url' => 'https://img/feed.jpg']]);
    $post(['event' => 'content.preview_ready', 'event_id' => 'e-threads', 'agent_id' => 'jauki-threads', 'content' => [
        'platform' => 'threads', 'content_type' => 'thread', 'content_id' => $threadsItem->id, 'title' => 'Threads post', 'text' => 'Teks threads Jauki']]);
    $post(['event' => 'content.preview_ready', 'event_id' => 'e-article', 'agent_id' => 'jauki-article', 'content' => [
        'platform' => 'article', 'content_type' => 'article', 'content_id' => $articleItem->id, 'title' => 'Artikel Kauiz', 'text' => 'Ringkasan artikel',
        'image_url' => 'https://kauiz.web.id/v1/article-images/a.jpg', 'external_id' => '8']]);

    $query = http_build_query(['schedule_ids' => [$kauizFeed->id, $jaukiThreads->id, $kauizArticle->id]]);
    $data = collect($this->actingAs($owner)->getJson('/office/api/content?'.$query)->assertOk()->json('data'))->keyBy('schedule_id');

    expect($data[$jaukiThreads->id]['platform'])->toBe('threads');
    expect($data[$jaukiThreads->id]['text'])->toBe('Teks threads Jauki');
    expect($data[$jaukiThreads->id]['brand']['slug'])->toBe('jauki');
    expect($data[$kauizFeed->id]['platform'])->toBe('instagram');
    expect($data[$kauizFeed->id]['brand']['slug'])->toBe('kauiz');
    expect($data[$kauizArticle->id]['platform'])->toBe('article');
    expect($data[$kauizArticle->id]['imageUrl'])->toBe('https://kauiz.web.id/v1/article-images/a.jpg');
    expect(OfficeContentItem::count())->toBe(3);
});

test('a threads draft without content_id never lands on another card', function () {
    $owner = User::factory()->create(['is_office_owner' => true]);
    $kauizFeed = schedule('kauiz', 'instagram', 'feed', 'Kauiz feed');
    $jaukiThreads = schedule('jauki', 'threads', 'thread', 'Jauki threads');
    $this->actingAs($owner)->postJson('/office/api/content-schedules/'.$kauizFeed->id.'/generate')->assertOk();
    $this->actingAs($owner)->postJson('/office/api/content-schedules/'.$jaukiThreads->id.'/generate')->assertOk();

    $this->postJson('/api/office/events', ['event' => 'content.preview_ready', 'event_id' => 'old-threads', 'agent_id' => 'jauki-threads', 'content' => [
        'platform' => 'threads', 'content_type' => 'thread', 'title' => 'Threads post', 'text' => 'Draf lama tanpa content_id']], ['Authorization' => 'Bearer bridge-test-token'])->assertOk();

    $feed = OfficeContentItem::where('schedule_id', $kauizFeed->id)->firstOrFail();
    expect($feed->platform)->toBe('instagram');
    expect($feed->text)->toBeNull();
});
