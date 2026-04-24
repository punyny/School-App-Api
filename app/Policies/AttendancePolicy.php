<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\SubstituteTeacherAssignment;
use App\Models\Timetable;
use App\Models\User;
use App\Policies\Concerns\ChecksSchoolMembership;
use Illuminate\Support\Carbon;

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
            $subjectId = (int) ($attendance->subject_id ?? 0);
            if ($subjectId <= 0) {
                return false;
            }

            $isAssignedTeacher = $user->teachingClasses()
                ->where('classes.id', (int) $attendance->class_id)
                ->wherePivot('subject_id', (int) $attendance->subject_id)
                ->exists();

            if ($isAssignedTeacher) {
                return true;
            }

            $dayOfWeek = strtolower(Carbon::parse((string) $attendance->date)->format('l'));
            $timeStart = substr((string) $attendance->time_start, 0, 8);
            $timeEndRaw = $attendance->time_end ? (string) $attendance->time_end : (string) $attendance->time_start;
            $timeEnd = substr($timeEndRaw, 0, 8);

            $hasSubstituteAccess = SubstituteTeacherAssignment::query()
                ->where('class_id', (int) $attendance->class_id)
                ->where('subject_id', $subjectId)
                ->whereDate('date', Carbon::parse((string) $attendance->date)->toDateString())
                ->where('time_start', '<=', $timeStart)
                ->where('time_end', '>=', $timeEnd)
                ->where(function ($scope) use ($user): void {
                    $scope
                        ->where('substitute_teacher_id', (int) $user->id)
                        ->orWhere('original_teacher_id', (int) $user->id);
                })
                ->exists();

            if ($hasSubstituteAccess) {
                return true;
            }

            return Timetable::query()
                ->where('class_id', (int) $attendance->class_id)
                ->where('subject_id', $subjectId)
                ->where('teacher_id', $user->id)
                ->where('day_of_week', $dayOfWeek)
                ->where('time_start', '<=', $timeStart)
                ->where('time_end', '>=', $timeEnd)
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
