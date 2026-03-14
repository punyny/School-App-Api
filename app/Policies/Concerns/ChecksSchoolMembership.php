<?php

namespace App\Policies\Concerns;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;

trait ChecksSchoolMembership
{
    private function managesClass(User $user, ?int $classId): bool
    {
        if (! $classId) {
            return false;
        }

        if ($user->role === 'super-admin') {
            return true;
        }

        if (! in_array($user->role, ['admin', 'teacher'], true) || ! $user->school_id) {
            return false;
        }

        $class = SchoolClass::query()->find($classId);
        if (! $class || (int) $class->school_id !== (int) $user->school_id) {
            return false;
        }

        if ($user->role === 'teacher') {
            return $user->teachingClasses()->where('classes.id', $classId)->exists();
        }

        return true;
    }

    private function managesClassSubject(User $user, ?int $classId, ?int $subjectId): bool
    {
        if (! $classId || ! $subjectId) {
            return false;
        }

        if (! $this->managesClass($user, $classId)) {
            return false;
        }

        if ($user->role === 'super-admin') {
            return true;
        }

        $subject = Subject::query()->find($subjectId);
        if (! $subject || (int) $subject->school_id !== (int) $user->school_id) {
            return false;
        }

        if ($user->role === 'teacher') {
            return $user->teachingClasses()
                ->where('classes.id', $classId)
                ->wherePivot('subject_id', $subjectId)
                ->exists();
        }

        return $user->role === 'admin';
    }

    private function managesStudent(User $user, ?int $studentId): bool
    {
        if (! $studentId) {
            return false;
        }

        if ($user->role === 'super-admin') {
            return true;
        }

        if (! in_array($user->role, ['admin', 'teacher'], true) || ! $user->school_id) {
            return false;
        }

        $student = Student::query()->find($studentId);
        if (! $student) {
            return false;
        }

        return $this->managesClass($user, (int) $student->class_id);
    }

    private function isStudentOwner(User $user, ?int $studentId): bool
    {
        return $user->role === 'student' && (int) ($user->studentProfile?->id) === (int) $studentId;
    }

    private function isParentOfStudent(User $user, ?int $studentId): bool
    {
        return $user->role === 'parent'
            && $studentId
            && $user->children()->where('students.id', $studentId)->exists();
    }
}
