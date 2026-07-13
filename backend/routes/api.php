<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\GradeController;
use App\Http\Controllers\Api\OcrController;
use App\Http\Controllers\Api\SchoolClassController;
use App\Http\Controllers\Api\Yle\YleExamController;
use App\Http\Controllers\Api\Yle\YleSubmissionController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

Route::post('/auth/register', [AuthController::class, 'register']);

Route::middleware('auth:api')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::get('/classes/mine', [SchoolClassController::class, 'mine'])
        ->middleware('permission:class.view');

    Route::get('/exams/today', [ExamController::class, 'today'])
        ->middleware('permission:exam.view');
    Route::post('/exams/today', [ExamController::class, 'storeToday'])
        ->middleware('permission:exam.create');

    Route::post('/ocr/extract', [OcrController::class, 'extract'])
        ->middleware('permission:ocr.use');

    Route::get('/grades', [GradeController::class, 'index'])
        ->middleware('permission:grade.view');
    Route::post('/grades', [GradeController::class, 'store'])
        ->middleware('permission:grade.create');
    Route::put('/grades/{id}', [GradeController::class, 'update'])
        ->middleware('permission:grade.create');

    Route::get('/dashboard/class/{schoolClass}', [DashboardController::class, 'classStats'])
        ->middleware('permission:dashboard.view');
    Route::get('/dashboard/class/{schoolClass}/students', [DashboardController::class, 'studentStats'])
        ->middleware('permission:dashboard.view');

    Route::middleware('permission:exam.view')->group(function () {
        Route::get('/yle/exams', [YleExamController::class, 'index']);
        Route::get('/yle/exams/{id}', [YleExamController::class, 'show']);
    });
    Route::middleware('permission:exam.create')->group(function () {
        Route::post('/yle/exams', [YleExamController::class, 'store']);
    });
    Route::middleware('permission:exam.edit')->group(function () {
        Route::put('/yle/exams/{id}', [YleExamController::class, 'update']);
    });
    Route::middleware('permission:exam.delete')->group(function () {
        Route::delete('/yle/exams/{id}', [YleExamController::class, 'destroy']);
    });

    Route::put('/yle/questions/{id}', [YleExamController::class, 'updateQuestion'])
        ->middleware('permission:question.manage');

    Route::middleware('permission:submission.create')->group(function () {
        Route::post('/yle/submissions', [YleSubmissionController::class, 'store']);
        Route::post('/yle/submissions/{id}/pages', [YleSubmissionController::class, 'uploadPage']);
    });
    Route::middleware('permission:submission.manage')->group(function () {
        Route::put('/yle/submissions/{id}/student', [YleSubmissionController::class, 'updateStudent']);
        Route::post('/yle/submissions/{id}/manual', [YleSubmissionController::class, 'addManualMarks']);
    });

    Route::put('/yle/answers/{id}', [YleSubmissionController::class, 'updateAnswer'])
        ->middleware('permission:answer.manage');

    Route::get('/yle/submissions/{id}', [YleSubmissionController::class, 'show'])
        ->middleware('permission:submission.manage');
    Route::get('/yle/submissions', [YleSubmissionController::class, 'index'])
        ->middleware('permission:submission.manage');
});
