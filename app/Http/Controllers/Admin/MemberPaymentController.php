<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceFeePayment;
use App\Models\Member;
use App\Models\RegistrationFeePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MemberPaymentController extends Controller
{
    public function storeRegistration(Request $request, Member $member): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);
        $year = now('Asia/Jayapura')->year;

        DB::transaction(function () use ($member, $validated, $year, $request): void {
            $lockedMember = Member::query()->lockForUpdate()->findOrFail($member->id);
            $paid = (int) $lockedMember->registrationFeePayments()->where('year', $year)->sum('amount');

            if ($paid + $validated['amount'] > 100000) {
                throw ValidationException::withMessages([
                    'amount' => 'Pembayaran melebihi sisa biaya pendaftaran tahun ini sebesar Rp'.number_format(100000 - $paid, 0, ',', '.').'.',
                ]);
            }

            RegistrationFeePayment::query()->create([
                'member_id' => $lockedMember->id,
                'year' => $year,
                'amount' => $validated['amount'],
                'paid_at' => now('Asia/Jayapura'),
                'recorded_by' => $request->user()->id,
            ]);
        });

        $payments = $member->registrationFeePayments()->where('year', $year)->latest('paid_at')->get();

        return response()->json([
            'message' => 'Cicilan biaya pendaftaran berhasil dicatat.',
            'registration_paid' => (int) $payments->sum('amount'),
            'registration_balance' => max(0, 100000 - (int) $payments->sum('amount')),
            'registration_payments' => $payments,
        ], 201);
    }

    public function storeAttendance(Request $request, Attendance $attendance): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        $payment = DB::transaction(function () use ($attendance, $validated, $request): AttendanceFeePayment {
            $lockedAttendance = Attendance::query()->lockForUpdate()->findOrFail($attendance->id);

            return AttendanceFeePayment::query()->create([
                'attendance_id' => $lockedAttendance->id,
                'amount' => $validated['amount'],
                'paid_at' => now('Asia/Jayapura'),
                'recorded_by' => $request->user()->id,
            ]);
        });

        return response()->json([
            'message' => 'Pembayaran iuran sesi berhasil dicatat.',
            'payment' => $payment,
        ], 201);
    }
}
