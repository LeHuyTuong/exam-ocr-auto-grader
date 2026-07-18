<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Yle\YleQuestion;

class YleQuestionPolicy extends BasePolicy
{
    public function update(User $user, YleQuestion $question): bool
    {
        return $question->part?->exam?->created_by === $user->id;
    }
}
