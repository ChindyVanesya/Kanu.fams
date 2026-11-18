<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceReportRequest;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\TrainingSession;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class AttendanceReportController extends Controller
{
    public function __invoke(AttendanceReportRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $period = $validated['type'] === 'weekly'
            ? CarbonImmutable::createFromFormat('!Y-m-d', $validated['period'])
            : CarbonImmutable::createFromFormat('!Y-m', $validated['period']);

        $periodStart = $validated['type'] === 'weekly'
            ? $period->startOfWeek(Carbon::MONDAY)
            : $period->startOfMonth();
        $periodEnd = $validated['type'] === 'weekly'
            ? $periodStart->endOfWeek(Carbon::SUNDAY)
            : $periodStart->endOfMonth();

        $sessionIds = TrainingSession::query()
            ->whereDate('training_date', '>=', $periodStart->toDateString())
            ->whereDate('training_date', '<=', $periodEnd->toDateString())
            ->pluck('id');
        $sessionCount = $sessionIds->count();

        $attendanceCounts = Attendance::query()
            ->selectRaw('member_id, COUNT(*) as attendance_count')
            ->whereIn('training_session_id', $sessionIds)
            ->groupBy('member_id')
            ->pluck('attendance_count', 'member_id');

        $search = mb_strtolower(trim($validated['search'] ?? ''));

        $members = Member::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%']);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'status'])
            ->map(function (Member $member) use ($attendanceCounts, $sessionCount): array {
                $presentCount = (int) ($attendanceCounts[$member->id] ?? 0);

                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'status' => $member->status,
                    'attendance_count' => $presentCount,
                    'session_count' => $sessionCount,
                    'attendance_percentage' => $sessionCount > 0
                        ? round(($presentCount / $sessionCount) * 100, 1)
                        : null,
                ];
            });

        return response()->json([
            'type' => $validated['type'],
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'session_count' => $sessionCount,
            'members' => $members,
        ]);
    }
}
