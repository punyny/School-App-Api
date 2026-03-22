<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClassController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SchoolClass::class);

        $filters = $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'name' => ['nullable', 'string', 'max:50'],
            'grade_level' => ['nullable', 'string', 'max:50'],
            'room' => ['nullable', 'string', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'in:id,name,grade_level,room,created_at'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ]);

        $query = SchoolClass::query()
            ->select('classes.*')
            ->selectSub(
                DB::table('teacher_class')
                    ->selectRaw('COUNT(DISTINCT subject_id)')
                    ->whereColumn('teacher_class.class_id', 'classes.id'),
                'subjects_count'
            )
            ->selectSub(
                DB::table('teacher_class')
                    ->selectRaw('COUNT(DISTINCT teacher_id)')
                    ->whereColumn('teacher_class.class_id', 'classes.id'),
                'teachers_count'
            )
            ->with(['school'])
            ->withCount(['students', 'timetables'])
            ->orderBy($filters['sort_by'] ?? 'name', $filters['sort_dir'] ?? 'asc');

        $this->applyVisibilityScope($query, $request->user());

        if (isset($filters['school_id']) && $request->user()->role === 'super-admin') {
            $query->where('school_id', (int) $filters['school_id']);
        }

        if (isset($filters['name']) && $filters['name'] !== '') {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }

        if (isset($filters['grade_level']) && $filters['grade_level'] !== '') {
            $query->where('grade_level', 'like', '%'.$filters['grade_level'].'%');
        }

        if (isset($filters['room']) && $filters['room'] !== '') {
            $query->where('room', 'like', '%'.$filters['room'].'%');
        }

        return response()->json($query->paginate($filters['per_page'] ?? 20));
    }

    public function show(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        $this->authorize('view', $schoolClass);

        $query = SchoolClass::query()->whereKey($schoolClass->id);
        $this->applyVisibilityScope($query, $request->user());

        if (! $query->exists()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'data' => $schoolClass->load([
                'school',
                'students.user',
                'students.parents',
                'subjects',
                'teachers',
                'timetables.subject',
                'timetables.teacher',
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', SchoolClass::class);

        $authUser = $request->user();
        $payload = $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:50'],
            'grade_level' => ['nullable', 'string', 'max:50'],
            'room' => ['nullable', 'string', 'max:50'],
        ]);

        $schoolId = $this->resolveSchoolIdForWrite($authUser, $payload['school_id'] ?? null);
        $gradeLevel = $payload['grade_level'] ?? null;

        if ($this->classExists($schoolId, $payload['name'], $gradeLevel)) {
            throw ValidationException::withMessages([
                'name' => ['A class with this name and grade level already exists in this school.'],
            ]);
        }

        $schoolClass = SchoolClass::query()->create([
            'school_id' => $schoolId,
            'name' => $payload['name'],
            'grade_level' => $gradeLevel,
            'room' => $payload['room'] ?? null,
        ]);

        return response()->json([
            'message' => 'Class created successfully.',
            'data' => $schoolClass->load('school'),
        ], 201);
    }

    public function update(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        $this->authorize('update', $schoolClass);

        $authUser = $request->user();
        $this->ensureWritableBy($authUser, $schoolClass);

        $payload = $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'name' => ['nullable', 'string', 'max:50'],
            'grade_level' => ['nullable', 'string', 'max:50'],
            'room' => ['nullable', 'string', 'max:50'],
        ]);

        $targetSchoolId = array_key_exists('school_id', $payload)
            ? $this->resolveSchoolIdForWrite($authUser, $payload['school_id'])
            : (int) $schoolClass->school_id;
        $targetName = array_key_exists('name', $payload) ? (string) $payload['name'] : (string) $schoolClass->name;
        $targetGrade = array_key_exists('grade_level', $payload) ? $payload['grade_level'] : $schoolClass->grade_level;

        if ($this->classExists($targetSchoolId, $targetName, $targetGrade, $schoolClass->id)) {
            throw ValidationException::withMessages([
                'name' => ['A class with this name and grade level already exists in this school.'],
            ]);
        }

        $schoolClass->fill([
            'school_id' => $targetSchoolId,
            'name' => $targetName,
            'grade_level' => $targetGrade,
            'room' => array_key_exists('room', $payload) ? $payload['room'] : $schoolClass->room,
        ])->save();

        return response()->json([
            'message' => 'Class updated successfully.',
            'data' => $schoolClass->fresh()->load('school'),
        ]);
    }

    public function syncTeacherAssignments(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        $this->authorize('update', $schoolClass);

        $authUser = $request->user();
        $this->ensureWritableBy($authUser, $schoolClass);

        $payload = $request->validate([
            'assignments' => ['nullable', 'array'],
            'assignments.*.teacher_id' => ['required', 'integer', 'exists:users,id'],
            'assignments.*.subject_id' => ['required', 'integer', 'exists:subjects,id'],
        ]);

        $rows = collect($payload['assignments'] ?? [])
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(fn (array $row): array => [
                'teacher_id' => (int) ($row['teacher_id'] ?? 0),
                'subject_id' => (int) ($row['subject_id'] ?? 0),
            ])
            ->filter(fn (array $row): bool => $row['teacher_id'] > 0 && $row['subject_id'] > 0)
            ->unique(fn (array $row): string => $row['teacher_id'].'-'.$row['subject_id'])
            ->values();

        $teacherIds = $rows->pluck('teacher_id')->unique()->values()->all();
        $subjectIds = $rows->pluck('subject_id')->unique()->values()->all();
        $schoolId = (int) $schoolClass->school_id;

        if ($teacherIds !== []) {
            $teachers = User::query()
                ->whereIn('id', $teacherIds)
                ->get(['id', 'role', 'school_id'])
                ->keyBy('id');

            if ($teachers->count() !== count($teacherIds)) {
                throw ValidationException::withMessages([
                    'assignments' => ['Some teachers could not be found.'],
                ]);
            }

            $invalidTeacher = $teachers->first(fn (User $teacher): bool => $teacher->role !== 'teacher' || (int) $teacher->school_id !== $schoolId);
            if ($invalidTeacher) {
                throw ValidationException::withMessages([
                    'assignments' => ['Each teacher must have role=teacher and belong to the same school as this class.'],
                ]);
            }
        }

        if ($subjectIds !== []) {
            $subjects = Subject::query()
                ->whereIn('id', $subjectIds)
                ->get(['id', 'school_id'])
                ->keyBy('id');

            if ($subjects->count() !== count($subjectIds)) {
                throw ValidationException::withMessages([
                    'assignments' => ['Some subjects could not be found.'],
                ]);
            }

            $invalidSubject = $subjects->first(fn (Subject $subject): bool => (int) $subject->school_id !== $schoolId);
            if ($invalidSubject) {
                throw ValidationException::withMessages([
                    'assignments' => ['Each subject must belong to the same school as this class.'],
                ]);
            }
        }

        DB::transaction(function () use ($schoolClass, $rows): void {
            DB::table('teacher_class')
                ->where('class_id', $schoolClass->id)
                ->delete();

            if ($rows->isEmpty()) {
                return;
            }

            $timestamp = now();
            $inserts = $rows
                ->map(fn (array $row): array => [
                    'teacher_id' => $row['teacher_id'],
                    'class_id' => (int) $schoolClass->id,
                    'subject_id' => $row['subject_id'],
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ])
                ->all();

            DB::table('teacher_class')->insert($inserts);
        });

        return response()->json([
            'message' => 'Class teacher assignments synchronized successfully.',
            'data' => [
                'class_id' => (int) $schoolClass->id,
                'assignment_count' => $rows->count(),
                'teacher_count' => count($teacherIds),
                'subject_count' => count($subjectIds),
            ],
        ]);
    }

    public function destroy(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        $this->authorize('delete', $schoolClass);

        $this->ensureWritableBy($request->user(), $schoolClass);

        $schoolClass->delete();

        return response()->json([
            'message' => 'Class deleted successfully.',
        ]);
    }

    public function restore(Request $request, int $classId): JsonResponse
    {
        $authUser = $request->user();
        $schoolClass = SchoolClass::query()->withTrashed()->findOrFail($classId);

        $this->authorize('update', $schoolClass);
        $this->ensureWritableBy($authUser, $schoolClass);

        if (method_exists($schoolClass, 'trashed') && $schoolClass->trashed()) {
            $schoolClass->restore();
        }

        return response()->json([
            'message' => 'Class restored successfully.',
            'data' => $schoolClass->fresh()->load('school'),
        ]);
    }

    private function applyVisibilityScope(Builder $query, User $user): void
    {
        if ($user->role === 'super-admin') {
            return;
        }

        if (in_array($user->role, ['admin', 'teacher', 'student', 'parent'], true) && $user->school_id) {
            $query->where('school_id', (int) $user->school_id);

            if ($user->role === 'teacher') {
                $classIds = $user->teachingClasses()->pluck('classes.id')->all();
                $query->whereIn('id', $classIds === [] ? [-1] : $classIds);
            }

            if ($user->role === 'student') {
                $classId = $user->studentProfile?->class_id;
                $query->whereIn('id', $classId ? [$classId] : [-1]);
            }

            if ($user->role === 'parent') {
                $classIds = $user->children()->pluck('students.class_id')->filter()->unique()->values()->all();
                $query->whereIn('id', $classIds === [] ? [-1] : $classIds);
            }

            return;
        }

        $query->whereRaw('1 = 0');
    }

    private function resolveSchoolIdForWrite(User $authUser, mixed $requestedSchoolId): int
    {
        if ($authUser->role === 'super-admin') {
            if (! $requestedSchoolId) {
                throw ValidationException::withMessages([
                    'school_id' => ['school_id is required for super-admin writes.'],
                ]);
            }

            return (int) $requestedSchoolId;
        }

        if ($authUser->role !== 'admin' || ! $authUser->school_id) {
            throw ValidationException::withMessages([
                'role' => ['Only super-admin or admin can modify classes.'],
            ]);
        }

        return (int) $authUser->school_id;
    }

    private function ensureWritableBy(User $authUser, SchoolClass $schoolClass): void
    {
        if ($authUser->role === 'super-admin') {
            return;
        }

        if ($authUser->role !== 'admin' || ! $authUser->school_id || (int) $schoolClass->school_id !== (int) $authUser->school_id) {
            throw ValidationException::withMessages([
                'class_id' => ['You cannot modify this class.'],
            ]);
        }
    }

    private function classExists(int $schoolId, string $name, ?string $gradeLevel, ?int $ignoreId = null): bool
    {
        $query = SchoolClass::query()->withTrashed()
            ->where('school_id', $schoolId)
            ->where('name', $name);

        if ($gradeLevel === null) {
            $query->whereNull('grade_level');
        } else {
            $query->where('grade_level', $gradeLevel);
        }

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }
}
