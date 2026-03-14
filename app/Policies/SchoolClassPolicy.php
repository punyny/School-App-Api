<?php

namespace App\Policies;

use App\Models\SchoolClass;
use App\Models\User;

class SchoolClassPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['super-admin', 'admin', 'teacher', 'student', 'parent'], true);
    }

    public function view(User $user, SchoolClass $schoolClass): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        if ($user->role === 'admin') {
            return (int) $user->school_id === (int) $schoolClass->school_id;
        }

        if ($user->role === 'teacher') {
            if ((int) $user->school_id !== (int) $schoolClass->school_id) {
                return false;
            }

            return $user->teachingClasses()->where('classes.id', $schoolClass->id)->exists();
        }

        if ($user->role === 'student') {
            return (int) ($user->studentProfile?->class_id) === (int) $schoolClass->id;
        }

        if ($user->role === 'parent') {
            return $user->children()->where('students.class_id', $schoolClass->id)->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['super-admin', 'admin'], true);
    }

    public function update(User $user, SchoolClass $schoolClass): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        return $user->role === 'admin'
            && $user->school_id
            && (int) $user->school_id === (int) $schoolClass->school_id;
    }

    public function delete(User $user, SchoolClass $schoolClass): bool
    {
        return $this->update($user, $schoolClass);
    }
}
