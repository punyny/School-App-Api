<?php

namespace App\Policies;

use App\Models\Score;
use App\Models\User;
use App\Policies\Concerns\ChecksSchoolMembership;

class ScorePolicy
{
    use ChecksSchoolMembership;

    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['super-admin', 'admin', 'teacher', 'student', 'parent'], true);
    }

    public function view(User $user, Score $score): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        if ($this->isStudentOwner($user, (int) $score->student_id)) {
            return true;
        }

        if ($this->isParentOfStudent($user, (int) $score->student_id)) {
            return true;
        }

        return $this->managesClassSubject($user, (int) $score->class_id, (int) $score->subject_id);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['super-admin', 'admin', 'teacher'], true);
    }

    public function update(User $user, Score $score): bool
    {
        return $this->create($user)
            && $this->managesClassSubject($user, (int) $score->class_id, (int) $score->subject_id);
    }

    public function delete(User $user, Score $score): bool
    {
        return $this->update($user, $score);
    }

    public function export(User $user): bool
    {
        return $this->viewAny($user);
    }
}
