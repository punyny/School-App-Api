<?php

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\Student;
use App\Models\User;
use App\Policies\Concerns\ChecksSchoolMembership;

class LeaveRequestPolicy
{
    use ChecksSchoolMembership;

    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['super-admin', 'admin', 'teacher', 'student', 'parent'], true);
    }

    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        if ($this->isStudentOwner($user, (int) $leaveRequest->student_id)) {
            return true;
        }

        if ($this->isParentOfStudent($user, (int) $leaveRequest->student_id)) {
            return true;
        }

        if ($user->role === 'teacher') {
            return $leaveRequest->recipients()->where('users.id', $user->id)->exists()
                || $this->teacherCanReview($user, $leaveRequest);
        }

        if ($user->role === 'admin') {
            return $this->managesStudent($user, (int) $leaveRequest->student_id);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['student', 'parent'], true);
    }

    public function update(User $user, LeaveRequest $leaveRequest): bool
    {
        if ((int) $leaveRequest->submitted_by === (int) $user->id && $leaveRequest->status === 'pending') {
            return true;
        }

        return $user->role === 'super-admin';
    }

    public function delete(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($this->update($user, $leaveRequest)) {
            return true;
        }

        if ($user->role === 'admin') {
            return $this->managesStudent($user, (int) $leaveRequest->student_id);
        }

        return false;
    }

    public function updateStatus(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        if ($user->role === 'admin') {
            return $this->managesStudent($user, (int) $leaveRequest->student_id);
        }

        if ($user->role === 'teacher') {
            return $leaveRequest->recipients()->where('users.id', $user->id)->exists()
                || $this->teacherCanReview($user, $leaveRequest);
        }

        return false;
    }

    private function teacherCanReview(User $teacher, LeaveRequest $leaveRequest): bool
    {
        if ($teacher->role !== 'teacher' || ! $teacher->school_id) {
            return false;
        }

        $student = Student::query()->find($leaveRequest->student_id);
        if (! $student || ! $student->class_id) {
            return false;
        }

        $subjectIds = collect($leaveRequest->subject_ids ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($subjectIds === [] && $leaveRequest->subject_id) {
            $subjectIds = [(int) $leaveRequest->subject_id];
        }

        $query = $teacher->teachingClasses()->where('classes.id', (int) $student->class_id);
        if ($subjectIds !== []) {
            $query->whereIn('teacher_class.subject_id', $subjectIds);
        }

        return $query->exists();
    }
}
