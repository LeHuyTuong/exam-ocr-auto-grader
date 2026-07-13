<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\GradeController;
use App\Http\Controllers\Api\OcrController;
use App\Http\Controllers\Api\SchoolClassController;
use App\Http\Controllers\Api\Yle\YleExamController;
use App\Http\Controllers\Api\Yle\YleSubmissionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

Route::post('/auth/register', [AuthController::class, 'register']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/user', fn (Request $r) => $r->user());

    Route::get('/classes/mine', [SchoolClassController::class, 'mine']);

    Route::get('/exams/today', [ExamController::class, 'today']);
    Route::post('/exams/today', [ExamController::class, 'storeToday']);

    Route::post('/ocr/extract', [OcrController::class, 'extract']);

    Route::post('/grades', [GradeController::class, 'store']);
    Route::put('/grades/{id}', [GradeController::class, 'update']);
    Route::get('/grades', [GradeController::class, 'index']);

    Route::get('/dashboard/class/{schoolClass}', [DashboardController::class, 'classStats']);
    Route::get('/dashboard/class/{schoolClass}/students', [DashboardController::class, 'studentStats']);

    // YLE exam management
    Route::get('/yle/exams', [YleExamController::class, 'index']);
    Route::post('/yle/exams', [YleExamController::class, 'store']);
    Route::get('/yle/exams/{id}', [YleExamController::class, 'show']);
    Route::put('/yle/exams/{id}', [YleExamController::class, 'update']);
    Route::delete('/yle/exams/{id}', [YleExamController::class, 'destroy']);
    Route::put('/yle/questions/{id}', [YleExamController::class, 'updateQuestion']);

    // YLE submissions
    Route::post('/yle/submissions', [YleSubmissionController::class, 'store']);
    Route::post('/yle/submissions/{id}/pages', [YleSubmissionController::class, 'uploadPage']);
    Route::put('/yle/submissions/{id}/student', [YleSubmissionController::class, 'updateStudent']);
    Route::post('/yle/submissions/{id}/manual', [YleSubmissionController::class, 'addManualMarks']);
    Route::put('/yle/answers/{id}', [YleSubmissionController::class, 'updateAnswer']);
    Route::get('/yle/submissions/{id}', [YleSubmissionController::class, 'show']);
    Route::get('/yle/submissions', [YleSubmissionController::class, 'index']);
});
