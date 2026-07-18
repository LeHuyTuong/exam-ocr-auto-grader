<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Yle\YleExam;

class YleExamPolicy extends BasePolicy
{
    public function view(User $user, YleExam $yleExam): bool
    {
        return true; // any authenticated user can view templates
    }

    public function create(User $user): bool
    {
        return true; // gated by permission middleware
    }

    public function update(User $user, YleExam $yleExam): bool
    {
        return $yleExam->created_by === $user->id;
    }

    public function delete(User $user, YleExam $yleExam): bool
    {
        return false; // admin-only via BasePolicy::before
    }
}
