<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    public function createConversation(User $user, Application $application): bool
    {
        return $application->student_id === $user->id
            || $application->internshipOffer()->where('company_id', $user->id)->exists();
    }
}
