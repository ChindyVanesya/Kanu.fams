<?php

use App\Services\CloseExpiredTrainingSessions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('training-sessions:close-expired', function (CloseExpiredTrainingSessions $expiredSessions): void {
    $count = $expiredSessions->close();
    $this->info("Menutup {$count} sesi latihan yang sudah lewat tanggalnya.");
})->purpose('Close training attendance sessions after their training date');

Schedule::command('training-sessions:close-expired')
    ->dailyAt('00:01')
    ->timezone('Asia/Jayapura')
    ->withoutOverlapping();
