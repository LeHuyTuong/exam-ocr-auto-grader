<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Yle\YleSubmission;

class YleSubmissionPolicy extends BasePolicy
{
    public function view(User $user, YleSubmission $submission): bool
    {
        return $this->ownsClass($user, $submission->class_id);
    }

    public function create(User $user): bool
    {
        return true; // gated by permission middleware
    }

    public function update(User $user, YleSubmission $submission): bool
    {
        return $this->ownsClass($user, $submission->class_id);
    }

    private function ownsClass(User $user, int $classId): bool
    {
        return $user->classes()->where('class_id', $classId)->exists();
    }
}
