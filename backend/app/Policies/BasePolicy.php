<?php

namespace App\Policies;

use App\Models\User;

class BasePolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }
}
