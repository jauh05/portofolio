<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LivingOfficeAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_guest_cannot_access_office()
    {
        $response = $this->get('/office');
        $response->assertRedirect('/office/login');
    }

    public function test_correct_owner_can_login()
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('secret123'),
            'is_office_owner' => true,
        ]);

        $response = $this->post('/office/login', [
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/office');
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_password_rejected()
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('secret123'),
            'is_office_owner' => true,
        ]);

        $response = $this->post('/office/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_authenticated_owner_can_access_office()
    {
        $user = User::factory()->create(['is_office_owner' => true]);
        $response = $this->actingAs($user)->get('/office');
        $response->assertStatus(200);
    }

    public function test_owner_can_logout()
    {
        $user = User::factory()->create(['is_office_owner' => true]);
        $response = $this->actingAs($user)->post('/office/logout');
        $response->assertRedirect('/office/login');
        $this->assertGuest();
    }

    public function test_guest_cannot_get_office_api()
    {
        $response = $this->getJson('/office/api/agents');
        $response->assertStatus(401);
    }

    public function test_authenticated_owner_can_get_office_api()
    {
        $user = User::factory()->create(['is_office_owner' => true]);
        $response = $this->actingAs($user)->getJson('/office/api/agents');
        $response->assertStatus(200);
    }

    public function test_authenticated_non_owner_cannot_access_office_or_api()
    {
        $user = User::factory()->create(['is_office_owner' => false]);

        $this->actingAs($user)->get('/office')->assertForbidden();
        $this->actingAs($user)->getJson('/office/api/agents')->assertForbidden();
    }

    public function test_non_owner_credentials_are_rejected_by_office_login()
    {
        User::factory()->create([
            'email' => 'member@example.com',
            'password' => bcrypt('secret123'),
            'is_office_owner' => false,
        ]);

        $this->post('/office/login', ['email' => 'member@example.com', 'password' => 'secret123'])
            ->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_existing_account_can_be_promoted_without_resetting_its_password()
    {
        $user = User::factory()->create(['is_office_owner' => false]);
        $passwordHash = $user->password;

        $this->artisan('office:owner', ['--email' => $user->email, '--existing' => true])
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue($user->isOfficeOwner());
        $this->assertSame($passwordHash, $user->password);
    }

    public function test_bot_endpoint_does_not_require_login()
    {
        // Valid token
        config(['services.office_bridge.token' => 'valid-token']);
        
        $response = $this->postJson('/api/office/events', [
            'event' => 'system.heartbeat',
            'parent_system' => 'jauki-content-bot'
        ], [
            'Authorization' => 'Bearer valid-token'
        ]);
        
        $response->assertStatus(200);

        // Invalid token
        $response = $this->postJson('/api/office/events', [
            'event' => 'system.heartbeat',
        ], [
            'Authorization' => 'Bearer wrong-token'
        ]);
        
        $response->assertStatus(401);
    }
}
