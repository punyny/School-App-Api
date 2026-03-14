<?php

namespace App\Policies;

use App\Models\Homework;
use App\Models\User;
use App\Policies\Concerns\ChecksSchoolMembership;

class HomeworkPolicy
{
    use ChecksSchoolMembership;

    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['super-admin', 'admin', 'teacher', 'student', 'parent'], true);
    }

    public function view(User $user, Homework $homework): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        if ($user->role === 'student') {
            return (int) ($user->studentProfile?->class_id) === (int) $homework->class_id;
        }

        if ($user->role === 'parent') {
            return $user->children()->where('students.class_id', $homework->class_id)->exists();
        }

        return $this->managesClass($user, (int) $homework->class_id);
    }

    public function create(User $user): bool
    {
        return $user->role === 'teacher';
    }

    public function update(User $user, Homework $homework): bool
    {
        return $this->create($user) && $this->managesClassSubject(
            $user,
            (int) $homework->class_id,
            (int) $homework->subject_id
        );
    }

    public function delete(User $user, Homework $homework): bool
    {
        return $this->update($user, $homework);
    }

    public function updateStatus(User $user, Homework $homework): bool
    {
        if ($user->role === 'student') {
            return (int) ($user->studentProfile?->class_id) === (int) $homework->class_id;
        }

        if ($user->role === 'parent') {
            return $user->children()->where('students.class_id', $homework->class_id)->exists();
        }

        return false;
    }

    public function export(User $user): bool
    {
        return $this->viewAny($user);
    }
}
