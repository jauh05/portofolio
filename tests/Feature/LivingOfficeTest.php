<?php
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('local'));

test('living office route is available', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/office')
        ->assertOk()
        ->assertSee('living-office-root');
});
