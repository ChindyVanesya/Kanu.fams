<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\PrivateTrainingParticipant;
use App\Models\PrivateTrainingSession;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrivateTrainingSessionController extends Controller
{
    public function index(): JsonResponse
    {
        $this->closeExpiredSessions();

        return response()->json([
            'sessions' => PrivateTrainingSession::query()
                ->withCount('participants')
                ->withCount(['participants as attendances_count' => fn ($query) => $query->whereNotNull('attended_at')])
                ->latest('training_date')
                ->latest('id')
                ->paginate(15),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'training_date' => [
                'required',
                'date_format:Y-m-d',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value < now('Asia/Jayapura')->toDateString()) {
                        $fail('Tanggal latihan private tidak boleh sebelum hari ini.');
                    }
                },
            ],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'price_per_member' => ['required', 'integer', 'min:1', 'max:100000000'],
        ]);

        $session = DB::transaction(fn (): PrivateTrainingSession => PrivateTrainingSession::query()->create([
            ...$validated,
            'opened_by' => $request->user()->id,
            'status' => 'open',
            'opened_at' => now('Asia/Jayapura'),
        ]));

        return response()->json([
            'message' => 'Sesi latihan private berhasil dijadwalkan.',
            'session' => $session->loadCount('participants'),
        ], 201);
    }

    public function show(PrivateTrainingSession $privateTrainingSession): JsonResponse
    {
        $this->closeExpiredSessions();
        $privateTrainingSession->refresh();
        $privateTrainingSession->loadCount('participants');
        $privateTrainingSession->loadCount(['participants as attendances_count' => fn ($query) => $query->whereNotNull('attended_at')]);

        $participants = $privateTrainingSession->participants()
            ->with(['member:id,name,member_code,status', 'payments' => fn ($query) => $query->with('recordedBy:id,name')->latest('paid_at')])
            ->withSum('payments', 'amount')
            ->orderBy('id')
            ->get()
            ->map(function (PrivateTrainingParticipant $participant) use ($privateTrainingSession): array {
                $paid = (int) ($participant->payments_sum_amount ?? 0);

                return [
                    'id' => $participant->id,
                    'member_id' => $participant->member_id,
                    'name' => $participant->member->name,
                    'member_code' => $participant->member->member_code,
                    'member_status' => $participant->member->status,
                    'attended_at' => $participant->attended_at,
                    'amount_paid' => $paid,
                    'balance' => max(0, $privateTrainingSession->price_per_member - $paid),
                    'payment_status' => $paid >= $privateTrainingSession->price_per_member ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
                    'payments' => $participant->payments,
                ];
            });

        $participantMemberIds = $privateTrainingSession->participants()->pluck('member_id');
        $availableMembers = Member::query()
            ->where('status', 'active')
            ->whereNotIn('id', $participantMemberIds)
            ->orderBy('name')
            ->get(['id', 'name', 'member_code']);

        return response()->json([
            'session' => $privateTrainingSession,
            'participants' => $participants,
            'available_members' => $availableMembers,
        ]);
    }

    public function addParticipant(Request $request, PrivateTrainingSession $privateTrainingSession): JsonResponse
    {
        $this->closeExpiredSessions();

        $validated = $request->validate([
            'member_id' => ['required', 'integer', 'exists:members,id'],
        ]);

        try {
            $participant = DB::transaction(function () use ($privateTrainingSession, $validated): PrivateTrainingParticipant {
                $session = PrivateTrainingSession::query()->lockForUpdate()->findOrFail($privateTrainingSession->id);
                $member = Member::query()->lockForUpdate()->findOrFail($validated['member_id']);

                if ($session->status !== 'open') {
                    throw ValidationException::withMessages(['session' => 'Peserta hanya dapat ditambahkan pada sesi yang masih terbuka.']);
                }

                if ($member->status !== 'active') {
                    throw ValidationException::withMessages(['member_id' => 'Hanya anggota aktif yang dapat dipilih untuk latihan private.']);
                }

                if ($session->participants()->where('member_id', $member->id)->exists()) {
                    throw ValidationException::withMessages(['member_id' => 'Anggota ini sudah terdaftar pada sesi private.']);
                }

                return $session->participants()->create(['member_id' => $member->id]);
            });
        } catch (UniqueConstraintViolationException) {
            return response()->json(['message' => 'Anggota ini sudah terdaftar pada sesi private.'], 409);
        }

        return response()->json([
            'message' => 'Anggota berhasil ditambahkan ke sesi private.',
            'participant' => $participant,
        ], 201);
    }

    public function markAttendance(PrivateTrainingSession $privateTrainingSession, PrivateTrainingParticipant $participant): JsonResponse
    {
        $attendedAt = DB::transaction(function () use ($privateTrainingSession, $participant): Carbon {
            $session = PrivateTrainingSession::query()->lockForUpdate()->findOrFail($privateTrainingSession->id);
            $privateParticipant = PrivateTrainingParticipant::query()
                ->lockForUpdate()
                ->where('private_training_session_id', $session->id)
                ->findOrFail($participant->id);
            $member = Member::query()->lockForUpdate()->findOrFail($privateParticipant->member_id);

            if ($session->status !== 'open' || $session->training_date->toDateString() !== now('Asia/Jayapura')->toDateString()) {
                throw ValidationException::withMessages(['session' => 'Absensi private hanya dapat dicatat pada tanggal sesi yang masih terbuka.']);
            }

            if ($privateParticipant->attended_at !== null) {
                throw ValidationException::withMessages(['participant' => 'Kehadiran anggota ini sudah dikonfirmasi.']);
            }

            if ($member->status !== 'active') {
                throw ValidationException::withMessages(['participant' => 'Anggota nonaktif tidak dapat dicatat hadir.']);
            }

            $privateParticipant->update(['attended_at' => now('Asia/Jayapura')]);

            return $privateParticipant->attended_at;
        });

        return response()->json([
            'message' => 'Kehadiran latihan private berhasil dikonfirmasi.',
            'attended_at' => $attendedAt,
        ]);
    }

    public function close(PrivateTrainingSession $privateTrainingSession): JsonResponse
    {
        $session = DB::transaction(function () use ($privateTrainingSession): PrivateTrainingSession {
            $lockedSession = PrivateTrainingSession::query()->lockForUpdate()->findOrFail($privateTrainingSession->id);

            if ($lockedSession->status === 'closed') {
                throw ValidationException::withMessages(['session' => 'Sesi latihan private ini sudah ditutup.']);
            }

            $lockedSession->update(['status' => 'closed', 'closed_at' => now('Asia/Jayapura')]);

            return $lockedSession;
        });

        return response()->json([
            'message' => 'Sesi latihan private berhasil ditutup.',
            'session' => $session,
        ]);
    }

    private function closeExpiredSessions(): void
    {
        $today = now('Asia/Jayapura')->toDateString();
        DB::transaction(function () use ($today): void {
            $expiredSessions = PrivateTrainingSession::query()
                ->where('status', 'open')
                ->whereDate('training_date', '<', $today)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($expiredSessions as $session) {
                $session->update([
                    'status' => 'closed',
                    'closed_at' => Carbon::parse($session->training_date->toDateString(), 'Asia/Jayapura')->endOfDay(),
                ]);
            }
        });
    }
}
