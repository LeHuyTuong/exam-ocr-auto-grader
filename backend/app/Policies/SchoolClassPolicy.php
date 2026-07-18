<?php

namespace App\Policies;

use App\Models\SchoolClass;
use App\Models\User;

class SchoolClassPolicy extends BasePolicy
{
    public function view(User $user, SchoolClass $schoolClass): bool
    {
        return $user->classes()->where('class_id', $schoolClass->id)->exists();
    }

    public function update(User $user, SchoolClass $schoolClass): bool
    {
        return $user->classes()->where('class_id', $schoolClass->id)->exists();
    }
}
