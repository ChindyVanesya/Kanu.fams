<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendanceRequest;
use App\Models\Member;
use App\Models\TrainingSession;
use App\Services\CloseExpiredTrainingSessions;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublicAttendanceController extends Controller
{
    public function show(CloseExpiredTrainingSessions $expiredSessions): JsonResponse
    {
        $expiredSessions->close();

        $session = TrainingSession::query()
            ->where('status', 'open')
            ->whereDate('training_date', now('Asia/Jayapura')->toDateString())
            ->withCount('attendances')
            ->latest('opened_at')
            ->first();

        return response()->json([
            'session' => $session,
            'members' => Member::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(StoreAttendanceRequest $request, CloseExpiredTrainingSessions $expiredSessions): JsonResponse
    {
        $expiredSessions->close();

        try {
            DB::transaction(function () use ($request): void {
                $session = TrainingSession::query()
                    ->where('status', 'open')
                    ->whereDate('training_date', now('Asia/Jayapura')->toDateString())
                    ->latest('opened_at')
                    ->lockForUpdate()
                    ->first();

                if ($session === null) {
                    throw ValidationException::withMessages([
                        'session' => 'Absensi belum dibuka oleh admin.',
                    ]);
                }

                $member = Member::query()
                    ->whereKey($request->validated('member_id'))
                    ->where('status', 'active')
                    ->first();

                if ($member === null) {
                    throw ValidationException::withMessages([
                        'member_id' => 'Anggota tidak aktif atau tidak ditemukan.',
                    ]);
                }

                if ($session->attendances()->where('member_id', $member->id)->exists()) {
                    throw ValidationException::withMessages([
                        'member_id' => 'Anda sudah melakukan absensi pada sesi latihan ini.',
                    ]);
                }

                $session->attendances()->create([
                    'member_id' => $member->id,
                    'attended_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'Anda sudah melakukan absensi pada sesi latihan ini.',
            ], 409);
        }

        return response()->json([
            'message' => 'Kehadiran berhasil dicatat. Terima kasih!',
        ], 201);
    }
}
