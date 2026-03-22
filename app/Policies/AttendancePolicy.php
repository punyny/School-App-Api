<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;
use App\Policies\Concerns\ChecksSchoolMembership;

class AttendancePolicy
{
    use ChecksSchoolMembership;

    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['super-admin', 'admin', 'teacher', 'student', 'parent'], true);
    }

    public function view(User $user, Attendance $attendance): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        if ($user->role === 'teacher') {
            return (int) ($attendance->subject_id ?? 0) > 0
                && $user->teachingClasses()
                    ->where('classes.id', (int) $attendance->class_id)
                    ->wherePivot('subject_id', (int) $attendance->subject_id)
                    ->exists();
        }

        if ($this->isStudentOwner($user, (int) $attendance->student_id)) {
            return true;
        }

        if ($this->isParentOfStudent($user, (int) $attendance->student_id)) {
            return true;
        }

        return $this->managesClass($user, (int) $attendance->class_id);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['super-admin', 'admin', 'teacher'], true);
    }

    public function update(User $user, Attendance $attendance): bool
    {
        return $this->create($user) && $this->view($user, $attendance);
    }

    public function delete(User $user, Attendance $attendance): bool
    {
        return $this->update($user, $attendance);
    }

    public function export(User $user): bool
    {
        return $this->viewAny($user);
    }
}
