<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrivateTrainingParticipant;
use App\Models\PrivateTrainingPayment;
use App\Models\PrivateTrainingSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrivateTrainingPaymentController extends Controller
{
    public function store(Request $request, PrivateTrainingParticipant $participant): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
        ]);

        $result = DB::transaction(function () use ($request, $participant, $validated): array {
            $session = PrivateTrainingSession::query()
                ->lockForUpdate()
                ->findOrFail($participant->private_training_session_id);
            $lockedParticipant = PrivateTrainingParticipant::query()
                ->lockForUpdate()
                ->where('private_training_session_id', $session->id)
                ->findOrFail($participant->id);
            $paid = (int) $lockedParticipant->payments()->sum('amount');
            $remaining = max(0, $session->price_per_member - $paid);

            if ($remaining === 0) {
                throw ValidationException::withMessages(['amount' => 'Biaya latihan private anggota ini sudah lunas.']);
            }

            if ($validated['amount'] > $remaining) {
                throw ValidationException::withMessages([
                    'amount' => 'Pembayaran melebihi sisa biaya sebesar Rp'.number_format($remaining, 0, ',', '.').'.',
                ]);
            }

            $payment = PrivateTrainingPayment::query()->create([
                'private_training_participant_id' => $lockedParticipant->id,
                'amount' => $validated['amount'],
                'paid_at' => now('Asia/Jayapura'),
                'recorded_by' => $request->user()->id,
            ]);

            $newPaid = $paid + $payment->amount;

            return [
                'payment' => $payment->load('recordedBy:id,name'),
                'amount_paid' => $newPaid,
                'balance' => max(0, $session->price_per_member - $newPaid),
            ];
        });

        return response()->json([
            'message' => 'Cicilan latihan private berhasil dicatat.',
            ...$result,
        ], 201);
    }
}
