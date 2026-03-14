<?php

namespace App\Policies;

use App\Models\Subject;
use App\Models\User;

class SubjectPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['super-admin', 'admin', 'teacher', 'student', 'parent'], true);
    }

    public function view(User $user, Subject $subject): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        return in_array($user->role, ['admin', 'teacher', 'student', 'parent'], true)
            && $user->school_id
            && (int) $user->school_id === (int) $subject->school_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['super-admin', 'admin'], true);
    }

    public function update(User $user, Subject $subject): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        return $user->role === 'admin'
            && $user->school_id
            && (int) $user->school_id === (int) $subject->school_id;
    }

    public function delete(User $user, Subject $subject): bool
    {
        return $this->update($user, $subject);
    }
}
