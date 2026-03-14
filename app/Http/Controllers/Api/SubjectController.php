<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubjectController extends Controller
{
    private const KHMER_CORE_SUBJECTS = [
        'កម្មវិធីចំណេះដឹងទូទៅខ្មែរ',
        'ភាសាខ្មែរ',
        'គណិតវិទ្យា',
        'រូបវិទ្យា',
        'គីមីវិទ្យា',
        'ជីវវិទ្យា',
        'ផែនដីវិទ្យា',
        'ប្រវត្តិវិទ្យា',
        'ភូមិវិទ្យា',
        'ភាសាអង់គ្លេស',
    ];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Subject::class);

        $filters = $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'name' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Subject::query()
            ->with(['school'])
            ->orderBy('name');

        $this->applyVisibilityScope(
            $query,
            $request->user(),
            isset($filters['class_id']) ? (int) $filters['class_id'] : null
        );

        if (isset($filters['school_id']) && $request->user()->role === 'super-admin') {
            $query->where('school_id', (int) $filters['school_id']);
        }

        if (isset($filters['name']) && $filters['name'] !== '') {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }

        return response()->json($query->paginate($filters['per_page'] ?? 20));
    }

    public function show(Request $request, Subject $subject): JsonResponse
    {
        $this->authorize('view', $subject);

        $query = Subject::query()->whereKey($subject->id);
        $this->applyVisibilityScope($query, $request->user());

        if (! $query->exists()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'data' => $subject->load('school'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Subject::class);

        $authUser = $request->user();
        $payload = $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:100'],
            'full_score' => ['nullable', 'numeric', 'min:1', 'max:1000'],
        ]);

        $schoolId = $this->resolveSchoolIdForWrite($authUser, $payload['school_id'] ?? null);

        if ($this->subjectExists($schoolId, $payload['name'])) {
            throw ValidationException::withMessages([
                'name' => ['A subject with this name already exists in this school.'],
            ]);
        }

        $subject = Subject::query()->create([
            'school_id' => $schoolId,
            'name' => $payload['name'],
            'full_score' => isset($payload['full_score']) ? (float) $payload['full_score'] : 100,
        ]);

        return response()->json([
            'message' => 'Subject created successfully.',
            'data' => $subject->load('school'),
        ], 201);
    }

    public function update(Request $request, Subject $subject): JsonResponse
    {
        $this->authorize('update', $subject);

        $authUser = $request->user();
        $this->ensureWritableBy($authUser, $subject);

        $payload = $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'name' => ['nullable', 'string', 'max:100'],
            'full_score' => ['nullable', 'numeric', 'min:1', 'max:1000'],
        ]);

        $targetSchoolId = array_key_exists('school_id', $payload)
            ? $this->resolveSchoolIdForWrite($authUser, $payload['school_id'])
            : (int) $subject->school_id;
        $targetName = array_key_exists('name', $payload) ? (string) $payload['name'] : (string) $subject->name;

        if ($this->subjectExists($targetSchoolId, $targetName, $subject->id)) {
            throw ValidationException::withMessages([
                'name' => ['A subject with this name already exists in this school.'],
            ]);
        }

        $subject->fill([
            'school_id' => $targetSchoolId,
            'name' => $targetName,
            'full_score' => array_key_exists('full_score', $payload)
                ? (float) ($payload['full_score'] ?? 100)
                : $subject->full_score,
        ])->save();

        return response()->json([
            'message' => 'Subject updated successfully.',
            'data' => $subject->fresh()->load('school'),
        ]);
    }

    public function destroy(Request $request, Subject $subject): JsonResponse
    {
        $this->authorize('delete', $subject);

        $this->ensureWritableBy($request->user(), $subject);

        $subject->delete();

        return response()->json([
            'message' => 'Subject deleted successfully.',
        ]);
    }

    public function installKhmerCore(Request $request): JsonResponse
    {
        $this->authorize('create', Subject::class);

        $authUser = $request->user();
        $payload = $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'extra_subjects' => ['nullable', 'array'],
            'extra_subjects.*' => ['string', 'max:100'],
        ]);

        $schoolId = $this->resolveSchoolIdForWrite($authUser, $payload['school_id'] ?? null);
        $extraSubjects = collect($payload['extra_subjects'] ?? [])
            ->map(fn (mixed $name): string => trim((string) $name))
            ->filter()
            ->values()
            ->all();

        $subjectNames = collect(array_merge(self::KHMER_CORE_SUBJECTS, $extraSubjects))
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique()
            ->values();

        $created = 0;
        $existing = 0;

        foreach ($subjectNames as $name) {
            if ($this->subjectExists($schoolId, $name)) {
                $existing++;
                continue;
            }

            Subject::query()->create([
                'school_id' => $schoolId,
                'name' => $name,
                'full_score' => 100,
            ]);
            $created++;
        }

        return response()->json([
            'message' => 'Khmer core subjects installed.',
            'data' => [
                'school_id' => $schoolId,
                'created' => $created,
                'existing' => $existing,
                'total_requested' => $subjectNames->count(),
            ],
        ]);
    }

    private function applyVisibilityScope(Builder $query, User $user, ?int $classId = null): void
    {
        if ($user->role === 'super-admin') {
            return;
        }

        if (in_array($user->role, ['admin', 'teacher', 'student', 'parent'], true) && $user->school_id) {
            $query->where('school_id', (int) $user->school_id);

            if ($user->role === 'teacher') {
                $subjectIdsQuery = DB::table('teacher_class')
                    ->where('teacher_id', $user->id);

                if ($classId !== null) {
                    $subjectIdsQuery->where('class_id', $classId);
                }

                $subjectIds = $subjectIdsQuery
                    ->pluck('subject_id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->filter(fn (int $id): bool => $id > 0)
                    ->unique()
                    ->values()
                    ->all();

                $query->whereIn('id', $subjectIds === [] ? [-1] : $subjectIds);
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
                'role' => ['Only super-admin or admin can modify subjects.'],
            ]);
        }

        return (int) $authUser->school_id;
    }

    private function ensureWritableBy(User $authUser, Subject $subject): void
    {
        if ($authUser->role === 'super-admin') {
            return;
        }

        if ($authUser->role !== 'admin' || ! $authUser->school_id || (int) $subject->school_id !== (int) $authUser->school_id) {
            throw ValidationException::withMessages([
                'subject_id' => ['You cannot modify this subject.'],
            ]);
        }
    }

    private function subjectExists(int $schoolId, string $name, ?int $ignoreId = null): bool
    {
        $query = Subject::query()
            ->where('school_id', $schoolId)
            ->where('name', $name);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }
}
