<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\JsonResponse;

class AttendanceFeeController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $unpaidContributions = Attendance::query()
            ->with(['member:id,name,member_code', 'trainingSession:id,training_date,title'])
            ->whereDoesntHave('feePayments')
            ->get()
            ->groupBy('member_id')
            ->map(function ($attendances): array {
                $first = $attendances->first();

                return [
                    'member_id' => $first->member_id,
                    'member_name' => $first->member->name,
                    'member_code' => $first->member->member_code,
                    'unpaid_session_count' => $attendances->count(),
                    'sessions' => $attendances->map(fn (Attendance $attendance): array => [
                        'id' => $attendance->trainingSession->id,
                        'title' => $attendance->trainingSession->title,
                        'training_date' => $attendance->trainingSession->training_date->toDateString(),
                    ])->values(),
                ];
            })
            ->sortBy('member_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return response()->json(['unpaid_contributions' => $unpaidContributions]);
    }
}
