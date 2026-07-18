<?php

namespace App\Policies;

use App\Models\Exam;
use App\Models\User;

class ExamPolicy extends BasePolicy
{
    public function view(User $user, Exam $exam): bool
    {
        return $this->ownsClass($user, $exam->class_id);
    }

    public function create(User $user): bool
    {
        return true; // gated by permission middleware
    }

    public function update(User $user, Exam $exam): bool
    {
        return $this->ownsClass($user, $exam->class_id);
    }

    private function ownsClass(User $user, int $classId): bool
    {
        return $user->classes()->where('class_id', $classId)->exists();
    }
}
