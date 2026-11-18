<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_redirect_guests_to_the_login_page(): void
    {
        $this->get('/admin/absensi')->assertRedirect('/admin/login');
        $this->getJson('/admin/absensi/api/dashboard')->assertUnauthorized();
    }

    public function test_admin_can_log_in_and_open_the_dashboard(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@kanufams.test',
            'password' => 'password-rahasia-123',
        ]);

        $this->postJson('/admin/login', [
            'email' => 'admin@kanufams.test',
            'password' => 'password-rahasia-123',
        ])
            ->assertOk()
            ->assertJsonPath('redirect', route('admin.attendance.dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->get('/admin/absensi')->assertOk();
    }

    public function test_admin_can_log_out(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->postJson('/admin/logout')
            ->assertOk()
            ->assertJsonPath('redirect', route('admin.login'));

        $this->assertGuest();
    }
}
