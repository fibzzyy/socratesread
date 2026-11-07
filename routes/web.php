<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\OtpVerificationController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Student\ChallengeController;
use App\Http\Controllers\Student\ClassroomEnrollmentController;
use App\Http\Controllers\Student\DashboardController;
use App\Http\Controllers\Student\ProgressController;
use App\Http\Controllers\Student\QuizController;
use App\Http\Controllers\Student\ReadingController;
use App\Http\Controllers\Teacher\DocumentController;
use App\Http\Controllers\Teacher\ClassroomController;
use App\Http\Controllers\Teacher\ExportController;
use App\Http\Controllers\Teacher\StudentDetailController;
use App\Http\Controllers\Teacher\DashboardController as TeacherDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:6,1');

    Route::get('/verify-otp', [OtpVerificationController::class, 'create'])->name('verification.otp');
    Route::post('/verify-otp', [OtpVerificationController::class, 'store'])->middleware('throttle:6,1')->name('verification.otp.submit');
    Route::post('/verify-otp/resend', [OtpVerificationController::class, 'resend'])->middleware('throttle:6,1')->name('verification.otp.resend');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::get('/avatar/{user}', function (\App\Models\User $user) {
    if (! $user->avatar_path || ! \Illuminate\Support\Facades\Storage::disk('public')->exists($user->avatar_path)) {
        abort(404);
    }

    return response()->file(
        \Illuminate\Support\Facades\Storage::disk('public')->path($user->avatar_path),
        ['Cache-Control' => 'public, max-age=86400']
    );
})->name('avatar');

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
    Route::get('/settings/profile', [ProfileController::class, 'edit'])->name('settings.profile');
    Route::put('/settings/profile', [ProfileController::class, 'update'])->name('settings.profile.update');
});

Route::middleware(['auth', 'role:student'])->group(function () {
    Route::get('/classrooms', [ClassroomEnrollmentController::class, 'index'])->name('classrooms.index');
    Route::post('/classrooms/join', [ClassroomEnrollmentController::class, 'store'])->middleware('throttle:6,1')->name('classrooms.join');
    Route::get('/reading/{document?}', [ReadingController::class, 'show'])->whereNumber('document')->name('reading');
    Route::post('/reading/progress', [ReadingController::class, 'updateProgress'])->name('reading.progress');
    Route::post('/reading/coach', [ReadingController::class, 'coach'])->middleware('throttle:6,1')->name('reading.coach');
    Route::get('/challenge', [ChallengeController::class, 'show'])->name('challenge');
    Route::post('/challenge/answer', [ChallengeController::class, 'saveAnswer'])->name('challenge.answer');
    Route::post('/challenge/complete', [ChallengeController::class, 'complete'])->name('challenge.complete');
    Route::get('/quiz/timed/{document?}', [QuizController::class, 'timed'])->whereNumber('document')->name('quiz.timed');
    Route::get('/quiz/practice/{document?}', [QuizController::class, 'practice'])->whereNumber('document')->name('quiz.practice');
    Route::post('/quiz/submit', [QuizController::class, 'submit'])->name('quiz.submit');
    Route::get('/progress', ProgressController::class)->name('progress');
});

Route::middleware(['auth', 'role:teacher'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('/', TeacherDashboardController::class)->name('dashboard');
    Route::get('/export-progress', [ExportController::class, 'exportCsv'])->name('export.progress');
    Route::get('/students/{student}', [StudentDetailController::class, 'show'])->whereNumber('student')->name('students.show');
    Route::get('/classrooms', [ClassroomController::class, 'index'])->name('classrooms');
    Route::post('/classrooms', [ClassroomController::class, 'store'])->middleware('throttle:5,1')->name('classrooms.store');
    Route::post('/classrooms/{classroom}/toggle-status', [ClassroomController::class, 'toggleStatus'])->name('classrooms.toggle-status');
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents');
    Route::post('/documents', [DocumentController::class, 'store'])->middleware('throttle:5,1')->name('documents.store');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->whereNumber('document')->name('documents.destroy');
});
