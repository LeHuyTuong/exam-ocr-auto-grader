<?php

namespace App\Policies;

use App\Models\Grade;
use App\Models\User;

class GradePolicy extends BasePolicy
{
    public function view(User $user, Grade $grade): bool
    {
        return $this->ownsClass($user, $grade->class_id);
    }

    public function create(User $user): bool
    {
        return true; // gated by permission middleware
    }

    public function update(User $user, Grade $grade): bool
    {
        return $this->ownsClass($user, $grade->class_id);
    }

    private function ownsClass(User $user, int $classId): bool
    {
        return $user->classes()->where('class_id', $classId)->exists();
    }
}
