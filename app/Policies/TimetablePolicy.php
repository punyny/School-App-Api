<?php

namespace App\Policies;

use App\Models\Timetable;
use App\Models\User;

class TimetablePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['super-admin', 'admin', 'teacher', 'student', 'parent'], true);
    }

    public function view(User $user, Timetable $timetable): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        $timetableSchoolId = (int) ($timetable->class?->school_id ?? 0);

        if ($user->role === 'admin') {
            return $user->school_id && (int) $user->school_id === $timetableSchoolId;
        }

        if ($user->role === 'teacher') {
            if (! $user->school_id || (int) $user->school_id !== $timetableSchoolId) {
                return false;
            }

            return (int) $timetable->teacher_id === (int) $user->id
                || $user->teachingClasses()->where('classes.id', $timetable->class_id)->exists();
        }

        if ($user->role === 'student') {
            return (int) ($user->studentProfile?->class_id) === (int) $timetable->class_id;
        }

        if ($user->role === 'parent') {
            return $user->children()->where('students.class_id', $timetable->class_id)->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['super-admin', 'admin', 'teacher'], true);
    }

    public function update(User $user, Timetable $timetable): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        $timetableSchoolId = (int) ($timetable->class?->school_id ?? 0);

        if ($user->role === 'admin') {
            return $user->school_id && (int) $user->school_id === $timetableSchoolId;
        }

        if ($user->role === 'teacher') {
            return (int) $timetable->teacher_id === (int) $user->id
                && $user->school_id
                && (int) $user->school_id === $timetableSchoolId;
        }

        return false;
    }

    public function delete(User $user, Timetable $timetable): bool
    {
        return $this->update($user, $timetable);
    }
}
