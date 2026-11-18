<?php

use App\Http\Controllers\Admin\AttendanceDashboardController;
use App\Http\Controllers\Admin\AttendanceFeeController;
use App\Http\Controllers\Admin\AttendanceReportController;
use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\MemberPaymentController;
use App\Http\Controllers\Admin\TrainingSessionController;
use App\Http\Controllers\AdminAuthController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/login');

Route::redirect('/absensi', '/admin/login')->name('attendance.public');

Route::middleware('guest')->group(function (): void {
    Route::get('/admin/login', [AdminAuthController::class, 'create'])->name('admin.login');
    Route::post('/admin/login', [AdminAuthController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('admin.login.store');
});

Route::post('/admin/logout', [AdminAuthController::class, 'destroy'])
    ->middleware('auth')
    ->name('admin.logout');

Route::prefix('admin/absensi')
    ->name('admin.attendance.')
    ->middleware('auth')
    ->group(function (): void {
        Route::view('/', 'welcome', ['page' => 'admin'])->name('dashboard');
        Route::view('/sesi', 'welcome', ['page' => 'admin'])->name('sessions.page');
        Route::view('/sesi/{trainingSession}', 'welcome', ['page' => 'admin'])->name('sessions.detail-page');
        Route::view('/program-latihan', 'welcome', ['page' => 'admin'])->name('programs.page');
        Route::view('/iuran', 'welcome', ['page' => 'admin'])->name('fees.page');
        Route::view('/anggota', 'welcome', ['page' => 'admin'])->name('members.page');
        Route::view('/rekap', 'welcome', ['page' => 'admin'])->name('reports.page');

        Route::get('/api/dashboard', AttendanceDashboardController::class)->name('data');
        Route::get('/api/sesi', [TrainingSessionController::class, 'index'])->name('sessions.index');
        Route::get('/api/program-sesi', [TrainingSessionController::class, 'programIndex'])->name('programs.sessions');
        Route::get('/api/iuran', AttendanceFeeController::class)->name('fees.index');
        Route::post('/api/sesi', [TrainingSessionController::class, 'store'])->name('sessions.store');
        Route::get('/api/sesi/{trainingSession}', [TrainingSessionController::class, 'show'])->name('sessions.show');
        Route::delete('/api/sesi/{trainingSession}', [TrainingSessionController::class, 'destroy'])->name('sessions.destroy');
        Route::patch('/api/sesi/{trainingSession}/tutup', [TrainingSessionController::class, 'close'])
            ->name('sessions.close');
        Route::patch('/api/sesi/{trainingSession}/evaluasi', [TrainingSessionController::class, 'updateEvaluation'])
            ->name('sessions.evaluation.update');
        Route::patch('/api/sesi/{trainingSession}/program', [TrainingSessionController::class, 'updateProgram'])
            ->name('sessions.program.update');
        Route::post('/api/sesi/{trainingSession}/anggota/{member}/hadir', [TrainingSessionController::class, 'markAttendance'])
            ->name('sessions.attendance.store');
        Route::post('/api/kehadiran/{attendance}/iuran', [MemberPaymentController::class, 'storeAttendance'])
            ->name('attendance-fees.store');

        Route::get('/api/anggota/template', [MemberController::class, 'template'])->name('members.template');
        Route::post('/api/anggota/import', [MemberController::class, 'import'])->name('members.import');
        Route::get('/api/anggota', [MemberController::class, 'index'])->name('members.index');
        Route::post('/api/anggota', [MemberController::class, 'store'])->name('members.store');
        Route::patch('/api/anggota/{member}/status', [MemberController::class, 'updateStatus'])
            ->name('members.status');
        Route::patch('/api/anggota/{member}/jersey', [MemberController::class, 'updateJersey'])
            ->name('members.jersey');
        Route::post('/api/anggota/{member}/pembayaran-pendaftaran', [MemberPaymentController::class, 'storeRegistration'])
            ->name('members.registration-payments.store');

        Route::get('/api/rekap', AttendanceReportController::class)->name('reports.index');
    });
