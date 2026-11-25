<?php

use App\Http\Controllers\ReviewController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseMaterialController;
use App\Http\Controllers\TimetableController;
use App\Http\Controllers\TimetableEntryController;
use App\Http\Controllers\UserTimetableEntryController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FavoriteCourseController;
use App\Http\Controllers\AdminEntryController;
use App\Http\Controllers\CourseAnnouncementController;
use App\Http\Controllers\CourseGradeController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/timetable', [TimetableController::class, 'index']);
Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'registerForm']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::get('/courses/{course}', [CourseController::class, 'show']);
    Route::post('/courses/{course}/material', [CourseMaterialController::class, 'store'])->name('courses.material.store');
    Route::get('/courses/{course}/material/download', [CourseMaterialController::class, 'download'])->name('courses.material.download');
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/entries', [UserTimetableEntryController::class, 'index']);
    Route::get('/entries/create', [TimetableEntryController::class, 'create']);
    Route::post('/entries', [UserTimetableEntryController::class, 'store']);
    Route::delete('/entries/{id}', [TimetableEntryController::class, 'destroy']);
    Route::get('/courses', [CourseController::class, 'index']);
    Route::post('/courses/{course}/favorite', [FavoriteCourseController::class, 'toggle'])->name('courses.favorite');
    Route::post('/courses/{course}/reviews', [ReviewController::class, 'store']);
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);
    Route::post('/courses/{course}/announcements', [CourseAnnouncementController::class, 'store'])
        ->middleware('can:post-announcements');

    Route::get('/courses/{course}/roster/download',[CourseGradeController::class, 'download'])->name('courses.roster.download');
    Route::post('/courses/{course}/grades/import', [CourseGradeController::class, 'import'])->name('courses.grades.import');

    Route::middleware('can:decide-enrollments')->group(function () {
        Route::get('/admin/entries', [AdminEntryController::class, 'index']);
        Route::post('/admin/entries/{id}/approve', [AdminEntryController::class, 'approve'])->name('admin.entries.approve');
        Route::post('/admin/entries/{id}/reject', [AdminEntryController::class, 'reject'])->name('admin.entries.reject');
    });
});
