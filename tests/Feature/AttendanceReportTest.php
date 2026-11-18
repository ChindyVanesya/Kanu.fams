<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekly_report_uses_monday_through_sunday(): void
    {
        $admin = User::factory()->create();
        $member = Member::factory()->create(['name' => 'Echa']);
        $mondaySession = $this->createSession($admin, '2026-11-16');
        $this->createSession($admin, '2026-11-22');
        $this->createSession($admin, '2026-11-23');
        $mondaySession->attendances()->create([
            'member_id' => $member->id,
            'attended_at' => '2026-11-16 09:00:00',
        ]);

        $this->actingAs($admin)
            ->getJson('/admin/absensi/api/rekap?type=weekly&period=2026-11-18')
            ->assertOk()
            ->assertJsonPath('period_start', '2026-11-16')
            ->assertJsonPath('period_end', '2026-11-22')
            ->assertJsonPath('session_count', 2)
            ->assertJsonPath('members.0.attendance_count', 1)
            ->assertJsonPath('members.0.attendance_percentage', 50);
    }

    public function test_monthly_report_only_counts_sessions_in_selected_month(): void
    {
        $admin = User::factory()->create();
        $member = Member::factory()->create(['name' => 'Echa']);
        $novemberSession = $this->createSession($admin, '2026-11-01');
        $this->createSession($admin, '2026-11-30');
        $this->createSession($admin, '2026-12-01');
        $novemberSession->attendances()->create([
            'member_id' => $member->id,
            'attended_at' => '2026-11-01 09:00:00',
        ]);

        $this->actingAs($admin)
            ->getJson('/admin/absensi/api/rekap?type=monthly&period=2026-11')
            ->assertOk()
            ->assertJsonPath('period_start', '2026-11-01')
            ->assertJsonPath('period_end', '2026-11-30')
            ->assertJsonPath('session_count', 2)
            ->assertJsonPath('members.0.attendance_count', 1)
            ->assertJsonPath('members.0.attendance_percentage', 50);
    }

    public function test_report_has_no_percentage_when_there_were_no_sessions(): void
    {
        $admin = User::factory()->create();
        Member::factory()->create(['name' => 'Echa']);

        $this->actingAs($admin)
            ->getJson('/admin/absensi/api/rekap?type=monthly&period=2026-11')
            ->assertOk()
            ->assertJsonPath('session_count', 0)
            ->assertJsonPath('members.0.attendance_percentage', null);
    }

    private function createSession(User $admin, string $date): TrainingSession
    {
        return TrainingSession::factory()->create([
            'opened_by' => $admin->id,
            'training_date' => $date,
            'status' => 'closed',
            'opened_at' => $date.' 08:00:00',
            'closed_at' => $date.' 10:00:00',
        ]);
    }
}
