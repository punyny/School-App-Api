<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;
use App\Policies\Concerns\ChecksSchoolMembership;

class StudentPolicy
{
    use ChecksSchoolMembership;

    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['super-admin', 'admin', 'teacher'], true);
    }

    public function view(User $user, Student $student): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        if ($user->role === 'admin') {
            return $user->school_id
                && (int) ($student->user?->school_id ?? 0) === (int) $user->school_id;
        }

        if ($user->role === 'teacher') {
            return $this->managesClass($user, (int) $student->class_id);
        }

        if ($this->isStudentOwner($user, (int) $student->id)) {
            return true;
        }

        if ($this->isParentOfStudent($user, (int) $student->id)) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['super-admin', 'admin'], true);
    }

    public function update(User $user, Student $student): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        return $user->role === 'admin'
            && $user->school_id
            && (int) ($student->user?->school_id ?? 0) === (int) $user->school_id;
    }

    public function delete(User $user, Student $student): bool
    {
        return $this->update($user, $student);
    }
}
