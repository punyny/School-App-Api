<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\SubstituteTeacherAssignment;
use App\Models\Subject;
use App\Models\Timetable;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SubstituteTeacherAssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'substitute_teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'original_teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'date' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = SubstituteTeacherAssignment::query()
            ->with([
                'class:id,name,grade_level,room',
                'subject:id,name',
                'originalTeacher:id,name,email',
                'substituteTeacher:id,name,email',
                'assignedBy:id,name,email',
            ])
            ->orderByDesc('date')
            ->orderBy('time_start');

        $this->applyVisibilityScope($query, $request->user());

        foreach (['class_id', 'subject_id', 'substitute_teacher_id', 'original_teacher_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, (int) $filters[$field]);
            }
        }

        if (! empty($filters['date'])) {
            $query->whereDate('date', (string) $filters['date']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('date', '>=', (string) $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('date', '<=', (string) $filters['date_to']);
        }

        return response()->json($query->paginate($filters['per_page'] ?? 20));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->canManageAssignments($user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $payload = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'substitute_teacher_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date'],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end' => ['required', 'date_format:H:i', 'after:time_start'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $classId = (int) $payload['class_id'];
        $subjectId = (int) $payload['subject_id'];
        $substituteTeacherId = (int) $payload['substitute_teacher_id'];

        /** @var SchoolClass|null $class */
        $class = SchoolClass::query()->find($classId);
        /** @var Subject|null $subject */
        $subject = Subject::query()->find($subjectId);
        /** @var User|null $substituteTeacher */
        $substituteTeacher = User::query()->find($substituteTeacherId);

        if (! $class || ! $subject || ! $substituteTeacher) {
            throw ValidationException::withMessages([
                'class_id' => ['Invalid class, subject, or substitute teacher.'],
            ]);
        }

        if (! $user->school_id && $user->role === 'admin') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($user->role === 'admin' && (int) $class->school_id !== (int) $user->school_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ((int) $class->school_id !== (int) $subject->school_id) {
            throw ValidationException::withMessages([
                'subject_id' => ['Selected subject does not belong to the selected class school.'],
            ]);
        }

        if (! $class->subjects()->whereKey($subjectId)->exists()) {
            throw ValidationException::withMessages([
                'subject_id' => ['Selected subject is not assigned to the selected class.'],
            ]);
        }

        if ($substituteTeacher->role !== 'teacher' || (int) ($substituteTeacher->school_id ?? 0) !== (int) $class->school_id) {
            throw ValidationException::withMessages([
                'substitute_teacher_id' => ['Substitute teacher must be a teacher in the same school.'],
            ]);
        }

        $date = Carbon::parse((string) $payload['date'])->toDateString();
        $dayOfWeek = strtolower(Carbon::parse($date)->format('l'));
        $timeStart = $payload['time_start'].':00';
        $timeEnd = $payload['time_end'].':00';

        /** @var Timetable|null $session */
        $session = Timetable::query()
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('day_of_week', $dayOfWeek)
            ->where('time_start', '<=', $timeStart)
            ->where('time_end', '>=', $timeEnd)
            ->orderBy('time_start')
            ->first();

        if (! $session) {
            throw ValidationException::withMessages([
                'time_start' => ['No timetable session found that matches class, subject, date, and selected time range.'],
            ]);
        }

        $originalTeacherId = (int) $session->teacher_id;
        if ($originalTeacherId <= 0) {
            throw ValidationException::withMessages([
                'time_start' => ['Timetable session has no original teacher assigned.'],
            ]);
        }

        if ($originalTeacherId === $substituteTeacherId) {
            throw ValidationException::withMessages([
                'substitute_teacher_id' => ['Substitute teacher cannot be the same as original teacher.'],
            ]);
        }

        $alreadyAssigned = SubstituteTeacherAssignment::query()
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->whereDate('date', $date)
            ->where('time_start', $timeStart)
            ->where('time_end', $timeEnd)
            ->exists();

        if ($alreadyAssigned) {
            throw ValidationException::withMessages([
                'time_start' => ['A substitute assignment already exists for this exact session.'],
            ]);
        }

        $overlappingAssignment = SubstituteTeacherAssignment::query()
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->whereDate('date', $date)
            ->where('time_start', '<', $timeEnd)
            ->where('time_end', '>', $timeStart)
            ->exists();

        if ($overlappingAssignment) {
            throw ValidationException::withMessages([
                'time_start' => ['Selected time overlaps an existing substitute assignment for this class and subject.'],
            ]);
        }

        $this->ensureSubstituteTeacherHasNoTimeConflict(
            substituteTeacherId: $substituteTeacherId,
            dayOfWeek: $dayOfWeek,
            date: $date,
            timeStart: $timeStart,
            timeEnd: $timeEnd,
        );

        $assignment = SubstituteTeacherAssignment::query()->create([
            'school_id' => (int) $class->school_id,
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'original_teacher_id' => $originalTeacherId,
            'substitute_teacher_id' => $substituteTeacherId,
            'assigned_by_user_id' => (int) $user->id,
            'date' => $date,
            'time_start' => $timeStart,
            'time_end' => $timeEnd,
            'notes' => isset($payload['notes']) && trim((string) $payload['notes']) !== ''
                ? trim((string) $payload['notes'])
                : null,
        ]);

        return response()->json([
            'message' => 'Substitute teacher assigned successfully.',
            'data' => $assignment->load([
                'class:id,name,grade_level,room',
                'subject:id,name',
                'originalTeacher:id,name,email',
                'substituteTeacher:id,name,email',
                'assignedBy:id,name,email',
            ]),
        ], 201);
    }

    public function destroy(Request $request, SubstituteTeacherAssignment $substituteAssignment): JsonResponse
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            if (! $user->school_id || (int) $user->school_id !== (int) $substituteAssignment->school_id) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
        } elseif ($user->role !== 'super-admin') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $substituteAssignment->delete();

        return response()->json([
            'message' => 'Substitute assignment removed successfully.',
        ]);
    }

    private function canManageAssignments(User $user): bool
    {
        return in_array($user->role, ['super-admin', 'admin'], true);
    }

    private function applyVisibilityScope(Builder $query, User $user): void
    {
        if ($user->role === 'super-admin') {
            return;
        }

        if ($user->role === 'admin') {
            if (! $user->school_id) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where('school_id', (int) $user->school_id);

            return;
        }

        if ($user->role === 'teacher') {
            $query->where(function (Builder $scope) use ($user): void {
                $scope
                    ->where('substitute_teacher_id', (int) $user->id)
                    ->orWhere('original_teacher_id', (int) $user->id);
            });

            return;
        }

        $query->whereRaw('1 = 0');
    }

    private function ensureSubstituteTeacherHasNoTimeConflict(
        int $substituteTeacherId,
        string $dayOfWeek,
        string $date,
        string $timeStart,
        string $timeEnd,
    ): void {
        $timetableConflict = Timetable::query()
            ->where('teacher_id', $substituteTeacherId)
            ->where('day_of_week', $dayOfWeek)
            ->where('time_start', '<', $timeEnd)
            ->where('time_end', '>', $timeStart)
            ->exists();

        if ($timetableConflict) {
            throw ValidationException::withMessages([
                'substitute_teacher_id' => ['Substitute teacher has timetable conflict at this time.'],
            ]);
        }

        $assignmentConflict = SubstituteTeacherAssignment::query()
            ->where('substitute_teacher_id', $substituteTeacherId)
            ->whereDate('date', $date)
            ->where('time_start', '<', $timeEnd)
            ->where('time_end', '>', $timeStart)
            ->exists();

        if ($assignmentConflict) {
            throw ValidationException::withMessages([
                'substitute_teacher_id' => ['Substitute teacher already has another substitute session at this time.'],
            ]);
        }
    }
}
