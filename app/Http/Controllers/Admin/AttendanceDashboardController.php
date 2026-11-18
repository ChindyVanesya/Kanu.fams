<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\TrainingSession;
use App\Services\CloseExpiredTrainingSessions;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class AttendanceDashboardController extends Controller
{
    public function __invoke(CloseExpiredTrainingSessions $expiredSessions): JsonResponse
    {
        $expiredSessions->close();

        $weekStart = now('Asia/Jayapura')->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        $weeklySessions = TrainingSession::query()
            ->whereDate('training_date', '>=', $weekStart->toDateString())
            ->whereDate('training_date', '<=', $weekEnd->toDateString());

        return response()->json([
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'weekly_session_count' => (clone $weeklySessions)->count(),
            'weekly_attendance_count' => Attendance::query()
                ->whereHas('trainingSession', function ($query) use ($weekStart, $weekEnd): void {
                    $query->whereDate('training_date', '>=', $weekStart->toDateString())
                        ->whereDate('training_date', '<=', $weekEnd->toDateString());
                })
                ->count(),
            'active_member_count' => Member::query()->where('status', 'active')->count(),
            'open_session' => TrainingSession::query()
                ->where('status', 'open')
                ->whereDate('training_date', now('Asia/Jayapura')->toDateString())
                ->withCount('attendances')
                ->latest('opened_at')
                ->first(),
            'recent_sessions' => TrainingSession::query()
                ->withCount('attendances')
                ->latest('training_date')
                ->latest('id')
                ->limit(8)
                ->get(),
            'unpaid_contributions' => Attendance::query()
                ->with(['member:id,name,member_code', 'trainingSession:id,training_date,title'])
                ->whereDoesntHave('feePayments')
                ->get()
                ->groupBy('member_id')
                ->map(function ($attendances): array {
                    $first = $attendances->first();

                    return [
                        'member_id' => $first->member_id,
                        'member_name' => $first->member->name,
                        'unpaid_session_count' => $attendances->count(),
                        'sessions' => $attendances->map(fn (Attendance $attendance): array => [
                            'id' => $attendance->trainingSession->id,
                            'title' => $attendance->trainingSession->title,
                            'training_date' => $attendance->trainingSession->training_date->toDateString(),
                        ])->values(),
                    ];
                })
                ->values(),
        ]);
    }
}
