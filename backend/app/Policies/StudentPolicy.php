<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy extends BasePolicy
{
    public function view(User $user, Student $student): bool
    {
        return $this->ownsClass($user, $student->class_id);
    }

    public function create(User $user): bool
    {
        return true; // gated by permission middleware
    }

    public function update(User $user, Student $student): bool
    {
        return $this->ownsClass($user, $student->class_id);
    }

    public function delete(User $user, Student $student): bool
    {
        return $this->ownsClass($user, $student->class_id);
    }

    private function ownsClass(User $user, int $classId): bool
    {
        return $user->classes()->where('class_id', $classId)->exists();
    }
}
