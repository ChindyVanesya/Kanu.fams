<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_and_close_a_training_session(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->postJson('/admin/absensi/api/sesi', [
                'training_date' => '2026-11-16',
                'title' => 'Latihan rutin',
                'notes' => 'Latihan sore',
            ])
            ->assertCreated()
            ->assertJsonPath('session.status', 'open')
            ->assertJsonPath('session.title', 'Latihan rutin');

        $session = TrainingSession::query()->firstOrFail();

        $this->patchJson("/admin/absensi/api/sesi/{$session->id}/tutup")
            ->assertOk()
            ->assertJsonPath('session.status', 'closed');

        $this->assertDatabaseHas('training_sessions', [
            'id' => $session->id,
            'status' => 'closed',
        ]);
    }

    public function test_admin_cannot_open_a_second_session_while_another_is_open(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin)
            ->postJson('/admin/absensi/api/sesi', ['training_date' => '2026-11-16'])
            ->assertCreated();

        $this->postJson('/admin/absensi/api/sesi', ['training_date' => '2026-11-18'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('session');

        $this->assertDatabaseCount('training_sessions', 1);
    }

    public function test_admin_can_add_a_member_and_deactivate_them(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)
            ->postJson('/admin/absensi/api/anggota', [
                'name' => 'Anggota Resmi',
                'member_code' => 'KN-001',
                'phone' => '081234567890',
            ])
            ->assertCreated()
            ->assertJsonPath('member.status', 'active');

        $memberId = $response->json('member.id');

        $this->patchJson("/admin/absensi/api/anggota/{$memberId}/status", ['status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('member.status', 'inactive');

        $this->assertDatabaseHas('members', ['id' => $memberId, 'status' => 'inactive']);
    }

    public function test_active_member_can_attend_without_logging_in(): void
    {
        $member = Member::factory()->create(['name' => 'Echa']);
        $this->createOpenSession();

        $this->postJson('/absensi/konfirmasi', ['member_id' => $member->id])
            ->assertCreated()
            ->assertJsonPath('message', 'Kehadiran berhasil dicatat. Terima kasih!');

        $this->assertDatabaseHas('attendances', ['member_id' => $member->id]);
    }

    public function test_inactive_member_cannot_attend(): void
    {
        $member = Member::factory()->create(['status' => 'inactive']);
        $this->createOpenSession();

        $this->postJson('/absensi/konfirmasi', ['member_id' => $member->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('member_id');

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_member_cannot_attend_when_no_session_is_open(): void
    {
        $member = Member::factory()->create();

        $this->postJson('/absensi/konfirmasi', ['member_id' => $member->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('session');
    }

    public function test_member_cannot_attend_a_closed_session(): void
    {
        $member = Member::factory()->create();
        $this->createOpenSession(['status' => 'closed', 'closed_at' => now()]);

        $this->postJson('/absensi/konfirmasi', ['member_id' => $member->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('session');
    }

    public function test_member_cannot_attend_twice_in_one_session(): void
    {
        $member = Member::factory()->create();
        $session = $this->createOpenSession();

        $this->postJson('/absensi/konfirmasi', ['member_id' => $member->id])->assertCreated();
        $this->postJson('/absensi/konfirmasi', ['member_id' => $member->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('member_id');

        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseHas('attendances', [
            'member_id' => $member->id,
            'training_session_id' => $session->id,
        ]);
    }

    public function test_public_attendance_page_only_returns_active_member_names(): void
    {
        $activeMember = Member::factory()->create(['name' => 'Anggota Aktif', 'phone' => '081234567890']);
        Member::factory()->create(['name' => 'Anggota Nonaktif', 'status' => 'inactive']);

        $this->getJson('/absensi/data')
            ->assertOk()
            ->assertJsonPath('members.0.id', $activeMember->id)
            ->assertJsonPath('members.0.name', 'Anggota Aktif')
            ->assertJsonMissingPath('members.0.phone');
    }

    public function test_invalid_member_id_is_rejected(): void
    {
        $this->createOpenSession();

        $this->postJson('/absensi/konfirmasi', ['member_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('member_id');
    }

    /** @param array<string, mixed> $overrides */
    private function createOpenSession(array $overrides = []): TrainingSession
    {
        $admin = User::factory()->create();

        return TrainingSession::factory()->create([
            'opened_by' => $admin->id,
            'status' => 'open',
            'closed_at' => null,
            ...$overrides,
        ]);
    }
}
