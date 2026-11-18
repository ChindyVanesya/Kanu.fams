<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrainingSessionRequest;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\TrainingSession;
use App\Services\CloseExpiredTrainingSessions;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrainingSessionController extends Controller
{
    public function index(Request $request, CloseExpiredTrainingSessions $expiredSessions): JsonResponse
    {
        $expiredSessions->close();

        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);
        $query = TrainingSession::query()->withCount('attendances');
        if (isset($validated['month'])) {
            [$year, $month] = explode('-', $validated['month']);
            $query->whereYear('training_date', $year)->whereMonth('training_date', $month);
        }

        return response()->json([
            'months' => TrainingSession::query()
                ->selectRaw("to_char(training_date, 'YYYY-MM') as value")
                ->distinct()
                ->orderByDesc('value')
                ->pluck('value'),
            'sessions' => $query
                ->latest('training_date')
                ->latest('id')
                ->paginate(15),
        ]);
    }

    public function programIndex(CloseExpiredTrainingSessions $expiredSessions): JsonResponse
    {
        $expiredSessions->close();

        return response()->json([
            'sessions' => TrainingSession::query()
                ->withCount('attendances')
                ->orderBy('training_date')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function store(StoreTrainingSessionRequest $request): JsonResponse
    {
        $session = DB::transaction(function () use ($request): TrainingSession {
            return TrainingSession::query()->create([
                ...$request->validated(),
                'opened_by' => $request->user()->id,
                'status' => 'open',
                'opened_at' => now('Asia/Jayapura'),
            ]);
        });

        return response()->json([
            'message' => 'Sesi latihan berhasil dijadwalkan.',
            'session' => $session->loadCount('attendances'),
        ], 201);
    }

    public function show(TrainingSession $trainingSession, CloseExpiredTrainingSessions $expiredSessions): JsonResponse
    {
        $expiredSessions->close();
        $trainingSession->refresh();

        return response()->json([
            'session' => $trainingSession->loadCount('attendances'),
            'members' => Member::query()
                ->where(function ($query) use ($trainingSession): void {
                    $query->where('status', 'active')
                        ->orWhereHas('attendances', fn ($attendances) => $attendances->where('training_session_id', $trainingSession->id));
                })
                ->with(['attendances' => function ($query) use ($trainingSession): void {
                    $query->where('training_session_id', $trainingSession->id)
                        ->with('feePayments')
                        ->withSum('feePayments', 'amount');
                }])
                ->orderBy('name')
                ->get(['id', 'name', 'member_code'])
                ->map(function (Member $member): array {
                    $attendance = $member->attendances->first();

                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'member_code' => $member->member_code,
                        'status' => $member->status,
                        'attendance' => $attendance ? [
                            'id' => $attendance->id,
                            'attended_at' => $attendance->attended_at,
                            'fee_paid_amount' => (int) ($attendance->fee_payments_sum_amount ?? 0),
                            'has_paid_fee' => (int) ($attendance->fee_payments_sum_amount ?? 0) > 0,
                            'fee_payments' => $attendance->feePayments,
                        ] : null,
                    ];
                }),
        ]);
    }

    public function markAttendance(TrainingSession $trainingSession, Member $member): JsonResponse
    {
        try {
            $attendance = DB::transaction(function () use ($trainingSession, $member): Attendance {
                $session = TrainingSession::query()->lockForUpdate()->findOrFail($trainingSession->id);
                $activeMember = Member::query()->lockForUpdate()->findOrFail($member->id);

                if ($session->status !== 'open'
                    || $session->training_date->toDateString() !== now('Asia/Jayapura')->toDateString()) {
                    throw ValidationException::withMessages([
                        'session' => 'Kehadiran hanya bisa dikonfirmasi pada tanggal sesi latihan.',
                    ]);
                }

                if ($activeMember->status !== 'active') {
                    throw ValidationException::withMessages([
                        'member' => 'Anggota nonaktif tidak dapat dicatat hadir.',
                    ]);
                }

                if ($session->attendances()->where('member_id', $activeMember->id)->exists()) {
                    throw ValidationException::withMessages([
                        'member' => 'Kehadiran anggota ini sudah dikonfirmasi untuk sesi tersebut.',
                    ]);
                }

                return $session->attendances()->create([
                    'member_id' => $activeMember->id,
                    'attended_at' => now('Asia/Jayapura'),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            return response()->json(['message' => 'Kehadiran anggota ini sudah dikonfirmasi untuk sesi tersebut.'], 409);
        }

        return response()->json([
            'message' => 'Kehadiran berhasil dikonfirmasi.',
            'attendance' => $attendance,
        ], 201);
    }

    public function updateEvaluation(Request $request, TrainingSession $trainingSession): JsonResponse
    {
        $validated = $request->validate([
            'evaluation' => ['nullable', 'string', 'max:10000'],
        ]);

        $trainingSession->update($validated);

        return response()->json([
            'message' => 'Evaluasi latihan berhasil disimpan.',
            'evaluation' => $trainingSession->evaluation,
        ]);
    }

    public function updateProgram(Request $request, TrainingSession $trainingSession): JsonResponse
    {
        $validated = $request->validate([
            'training_focus' => ['required', 'string', 'max:255'],
            'training_program' => ['nullable', 'string', 'max:10000'],
        ]);

        $trainingSession->update($validated);

        return response()->json([
            'message' => 'Program latihan berhasil disimpan.',
            'session' => $trainingSession->only(['id', 'training_focus', 'training_program']),
        ]);
    }

    public function destroy(TrainingSession $trainingSession): JsonResponse
    {
        DB::transaction(function () use ($trainingSession): void {
            $session = TrainingSession::query()
                ->lockForUpdate()
                ->findOrFail($trainingSession->id);

            if ($session->attendances()->exists()) {
                throw ValidationException::withMessages([
                    'session' => 'Sesi tidak dapat dihapus karena sudah memiliki catatan kehadiran atau iuran.',
                ]);
            }

            $session->delete();
        });

        return response()->json(['message' => 'Sesi latihan beserta catatan kehadirannya berhasil dihapus.']);
    }

    public function close(TrainingSession $trainingSession): JsonResponse
    {
        $closedSession = Cache::lock('kanu-fams:open-training-session', 10)->get(function () use ($trainingSession): TrainingSession {
            return DB::transaction(function () use ($trainingSession): TrainingSession {
                $session = TrainingSession::query()
                    ->lockForUpdate()
                    ->findOrFail($trainingSession->id);

                if ($session->status === 'closed') {
                    throw ValidationException::withMessages([
                        'session' => 'Sesi absensi ini sudah ditutup.',
                    ]);
                }

                $session->update([
                    'status' => 'closed',
                    'closed_at' => now(),
                ]);

                return $session->loadCount('attendances');
            });
        });

        if ($closedSession === false) {
            throw ValidationException::withMessages([
                'session' => 'Sesi absensi sedang diproses. Silakan coba lagi.',
            ]);
        }

        return response()->json([
            'message' => 'Sesi absensi berhasil ditutup.',
            'session' => $closedSession,
        ]);
    }
}
