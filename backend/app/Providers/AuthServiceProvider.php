<?php

namespace App\Providers;

use App\Models\Exam;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Yle\YleExam;
use App\Models\Yle\YleQuestion;
use App\Models\Yle\YleSubmission;
use App\Policies\ExamPolicy;
use App\Policies\GradePolicy;
use App\Policies\SchoolClassPolicy;
use App\Policies\StudentPolicy;
use App\Policies\YleExamPolicy;
use App\Policies\YleQuestionPolicy;
use App\Policies\YleSubmissionPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        SchoolClass::class => SchoolClassPolicy::class,
        Student::class => StudentPolicy::class,
        Exam::class => ExamPolicy::class,
        Grade::class => GradePolicy::class,
        YleExam::class => YleExamPolicy::class,
        YleQuestion::class => YleQuestionPolicy::class,
        YleSubmission::class => YleSubmissionPolicy::class,
    ];

    public function boot(): void
    {
        //
    }
}
