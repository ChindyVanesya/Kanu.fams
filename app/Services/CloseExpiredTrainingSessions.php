<?php

namespace App\Services;

use App\Models\TrainingSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CloseExpiredTrainingSessions
{
    public function close(): int
    {
        $today = now('Asia/Jayapura')->toDateString();

        return DB::transaction(function () use ($today): int {
            $sessions = TrainingSession::query()
                ->where('status', 'open')
                ->whereDate('training_date', '<', $today)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($sessions as $session) {
                $session->update([
                    'status' => 'closed',
                    'closed_at' => Carbon::parse($session->training_date->toDateString(), 'Asia/Jayapura')->endOfDay(),
                ]);
            }

            return $sessions->count();
        });
    }
}
