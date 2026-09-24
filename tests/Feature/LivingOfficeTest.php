<?php
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('living office route is available', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/office')
        ->assertOk()
        ->assertSee('living-office-root');
});
