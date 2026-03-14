<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Timetable;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimetableController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Timetable::class);

        $filters = $request->validate([
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'day_of_week' => ['nullable', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Timetable::query()
            ->with(['class.school', 'subject', 'teacher'])
            ->orderBy('day_of_week')
            ->orderBy('time_start');

        $this->applyVisibilityScope($query, $request->user());

        foreach (['class_id', 'subject_id', 'teacher_id', 'day_of_week'] as $key) {
            if (isset($filters[$key])) {
                $query->where($key, $filters[$key]);
            }
        }

        return response()->json($query->paginate($filters['per_page'] ?? 20));
    }

    public function show(Request $request, Timetable $timetable): JsonResponse
    {
        $this->authorize('view', $timetable);

        $query = Timetable::query()->whereKey($timetable->id);
        $this->applyVisibilityScope($query, $request->user());

        if (! $query->exists()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'data' => $timetable->load(['class.school', 'subject', 'teacher']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Timetable::class);

        $user = $request->user();
        $payload = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'day_of_week' => ['required', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end' => ['required', 'date_format:H:i', 'after:time_start'],
        ]);

        if ($user->role === 'teacher') {
            $payload['teacher_id'] = $user->id;
        }

        if (! isset($payload['teacher_id'])) {
            throw ValidationException::withMessages([
                'teacher_id' => ['teacher_id is required.'],
            ]);
        }

        $this->ensurePayloadIsAllowed($user, $payload);
        $this->ensureNoTimeConflict(
            classId: (int) $payload['class_id'],
            teacherId: (int) $payload['teacher_id'],
            dayOfWeek: (string) $payload['day_of_week'],
            timeStart: (string) $payload['time_start'],
            timeEnd: (string) $payload['time_end'],
        );

        $timetable = Timetable::query()->create([
            'class_id' => (int) $payload['class_id'],
            'subject_id' => (int) $payload['subject_id'],
            'teacher_id' => (int) $payload['teacher_id'],
            'day_of_week' => $payload['day_of_week'],
            'time_start' => $payload['time_start'].':00',
            'time_end' => $payload['time_end'].':00',
        ]);

        return response()->json([
            'message' => 'Timetable created successfully.',
            'data' => $timetable->load(['class.school', 'subject', 'teacher']),
        ], 201);
    }

    public function update(Request $request, Timetable $timetable): JsonResponse
    {
        $this->authorize('update', $timetable);

        $user = $request->user();
        $payload = $request->validate([
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'day_of_week' => ['nullable', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'time_start' => ['nullable', 'date_format:H:i'],
            'time_end' => ['nullable', 'date_format:H:i'],
        ]);

        $target = [
            'class_id' => (int) ($payload['class_id'] ?? $timetable->class_id),
            'subject_id' => (int) ($payload['subject_id'] ?? $timetable->subject_id),
            'teacher_id' => (int) ($payload['teacher_id'] ?? $timetable->teacher_id),
            'day_of_week' => (string) ($payload['day_of_week'] ?? $timetable->day_of_week),
            'time_start' => (string) ($payload['time_start'] ?? substr((string) $timetable->time_start, 0, 5)),
            'time_end' => (string) ($payload['time_end'] ?? substr((string) $timetable->time_end, 0, 5)),
        ];

        if ($user->role === 'teacher') {
            $target['teacher_id'] = $user->id;
        }

        if ($target['time_end'] <= $target['time_start']) {
            throw ValidationException::withMessages([
                'time_end' => ['time_end must be after time_start.'],
            ]);
        }

        $this->ensurePayloadIsAllowed($user, $target, $timetable);
        $this->ensureNoTimeConflict(
            classId: (int) $target['class_id'],
            teacherId: (int) $target['teacher_id'],
            dayOfWeek: (string) $target['day_of_week'],
            timeStart: (string) $target['time_start'],
            timeEnd: (string) $target['time_end'],
            ignoreTimetableId: $timetable->id
        );

        $timetable->fill([
            'class_id' => $target['class_id'],
            'subject_id' => $target['subject_id'],
            'teacher_id' => $target['teacher_id'],
            'day_of_week' => $target['day_of_week'],
            'time_start' => $target['time_start'].':00',
            'time_end' => $target['time_end'].':00',
        ])->save();

        return response()->json([
            'message' => 'Timetable updated successfully.',
            'data' => $timetable->fresh()->load(['class.school', 'subject', 'teacher']),
        ]);
    }

    public function destroy(Request $request, Timetable $timetable): JsonResponse
    {
        $this->authorize('delete', $timetable);

        $query = Timetable::query()->whereKey($timetable->id);
        $this->applyWritableScope($query, $request->user());

        if (! $query->exists()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $timetable->delete();

        return response()->json([
            'message' => 'Timetable deleted successfully.',
        ]);
    }

    private function applyVisibilityScope(Builder $query, User $user): void
    {
        if ($user->role === 'super-admin') {
            return;
        }

        if ($user->role === 'admin' && $user->school_id) {
            $query->whereHas('class', fn (Builder $classQuery) => $classQuery->where('school_id', $user->school_id));

            return;
        }

        if ($user->role === 'teacher') {
            $classIds = $user->teachingClasses()->pluck('classes.id')->all();
            $query->where(function (Builder $scope) use ($user, $classIds): void {
                $scope->where('teacher_id', $user->id)
                    ->orWhereIn('class_id', $classIds === [] ? [-1] : $classIds);
            });

            return;
        }

        if ($user->role === 'student') {
            $classId = $user->studentProfile?->class_id;
            $query->whereIn('class_id', $classId ? [$classId] : [-1]);

            return;
        }

        if ($user->role === 'parent') {
            $classIds = $user->children()->pluck('students.class_id')->filter()->unique()->values()->all();
            $query->whereIn('class_id', $classIds === [] ? [-1] : $classIds);

            return;
        }

        $query->whereRaw('1 = 0');
    }

    private function applyWritableScope(Builder $query, User $user): void
    {
        if ($user->role === 'super-admin') {
            return;
        }

        if ($user->role === 'admin' && $user->school_id) {
            $query->whereHas('class', fn (Builder $classQuery) => $classQuery->where('school_id', $user->school_id));

            return;
        }

        if ($user->role === 'teacher') {
            $query->where('teacher_id', $user->id);

            return;
        }

        $query->whereRaw('1 = 0');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function ensurePayloadIsAllowed(User $user, array $payload, ?Timetable $existing = null): void
    {
        $class = SchoolClass::query()->find((int) $payload['class_id']);
        $subject = Subject::query()->find((int) $payload['subject_id']);
        $teacher = User::query()->find((int) $payload['teacher_id']);

        if (! $class || ! $subject || ! $teacher) {
            throw ValidationException::withMessages([
                'class_id' => ['Invalid class, subject, or teacher reference.'],
            ]);
        }

        if ($teacher->role !== 'teacher') {
            throw ValidationException::withMessages([
                'teacher_id' => ['teacher_id must reference a teacher user.'],
            ]);
        }

        $schoolIds = [(int) $class->school_id, (int) $subject->school_id, (int) ($teacher->school_id ?? 0)];
        if (count(array_unique($schoolIds)) !== 1) {
            throw ValidationException::withMessages([
                'school_id' => ['Class, subject, and teacher must belong to the same school.'],
            ]);
        }

        if ($user->role === 'admin') {
            if (! $user->school_id || (int) $user->school_id !== (int) $class->school_id) {
                throw ValidationException::withMessages([
                    'school_id' => ['You can only manage timetable in your school.'],
                ]);
            }
        }

        if ($user->role === 'teacher') {
            if ((int) $payload['teacher_id'] !== (int) $user->id) {
                throw ValidationException::withMessages([
                    'teacher_id' => ['Teacher can only assign timetable to themselves.'],
                ]);
            }

            if (! $user->school_id || (int) $user->school_id !== (int) $class->school_id) {
                throw ValidationException::withMessages([
                    'school_id' => ['You can only manage timetable in your school.'],
                ]);
            }

            $assigned = DB::table('teacher_class')
                ->where('teacher_id', $user->id)
                ->where('class_id', $class->id)
                ->where('subject_id', $subject->id)
                ->exists();

            if (! $assigned) {
                throw ValidationException::withMessages([
                    'class_id' => ['You are not assigned to this class/subject combination.'],
                ]);
            }

            if ($existing && (int) $existing->teacher_id !== (int) $user->id) {
                throw ValidationException::withMessages([
                    'timetable_id' => ['You cannot modify another teacher timetable row.'],
                ]);
            }
        }
    }

    private function ensureNoTimeConflict(
        int $classId,
        int $teacherId,
        string $dayOfWeek,
        string $timeStart,
        string $timeEnd,
        ?int $ignoreTimetableId = null
    ): void {
        $timeStartWithSeconds = $timeStart.':00';
        $timeEndWithSeconds = $timeEnd.':00';

        $classConflict = Timetable::query()
            ->where('class_id', $classId)
            ->where('day_of_week', $dayOfWeek)
            ->where('time_start', '<', $timeEndWithSeconds)
            ->where('time_end', '>', $timeStartWithSeconds);

        $teacherConflict = Timetable::query()
            ->where('teacher_id', $teacherId)
            ->where('day_of_week', $dayOfWeek)
            ->where('time_start', '<', $timeEndWithSeconds)
            ->where('time_end', '>', $timeStartWithSeconds);

        if ($ignoreTimetableId) {
            $classConflict->whereKeyNot($ignoreTimetableId);
            $teacherConflict->whereKeyNot($ignoreTimetableId);
        }

        if ($classConflict->exists()) {
            throw ValidationException::withMessages([
                'class_id' => ['Class timetable conflict detected for the selected day/time range.'],
            ]);
        }

        if ($teacherConflict->exists()) {
            throw ValidationException::withMessages([
                'teacher_id' => ['Teacher timetable conflict detected for the selected day/time range.'],
            ]);
        }
    }
}
