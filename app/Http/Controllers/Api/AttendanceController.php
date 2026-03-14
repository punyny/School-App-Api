<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AttendanceStoreRequest;
use App\Http\Requests\Api\AttendanceUpdateRequest;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Attendance::class);

        $filters = $request->validate([
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'status' => ['nullable', 'in:P,A,L'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'in:id,date,time_start,time_end,status,created_at'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ]);

        $query = Attendance::query()
            ->with(['student.user', 'class'])
            ->orderBy($filters['sort_by'] ?? 'date', $filters['sort_dir'] ?? 'desc')
            ->orderBy('time_start', 'desc');

        $this->applyVisibilityScope($query, $request->user());

        if (isset($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (isset($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        return response()->json($query->paginate($filters['per_page'] ?? 20));
    }

    public function show(Request $request, Attendance $attendance): JsonResponse
    {
        $this->authorize('view', $attendance);

        return response()->json([
            'data' => $attendance->load(['student.user', 'class']),
        ]);
    }

    public function store(AttendanceStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Attendance::class);

        $user = $request->user();
        $payload = $request->validated();

        if (! $this->canManageClass($user, (int) $payload['class_id'])) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $this->ensureStudentBelongsToClass((int) $payload['student_id'], (int) $payload['class_id']);
        $payload = $this->normalizeAttendancePayload($payload);
        $this->validateTimeRange((string) $payload['time_start'], $payload['time_end'] ?? null);

        if ($this->hasDuplicateRecord($payload)) {
            throw ValidationException::withMessages([
                'time_start' => ['Attendance already exists for this student/class/date/time_start.'],
            ]);
        }

        $attendance = Attendance::query()->create($payload);

        return response()->json([
            'message' => 'Attendance created.',
            'data' => $attendance->load(['student.user', 'class']),
        ], 201);
    }

    public function update(AttendanceUpdateRequest $request, Attendance $attendance): JsonResponse
    {
        $this->authorize('update', $attendance);

        $user = $request->user();
        $payload = $request->validated();

        $targetClassId = (int) ($payload['class_id'] ?? $attendance->class_id);
        $targetStudentId = (int) ($payload['student_id'] ?? $attendance->student_id);

        if (! $this->canManageClass($user, $targetClassId)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $this->ensureStudentBelongsToClass($targetStudentId, $targetClassId);

        $payload = $this->normalizeAttendancePayload($payload);
        $merged = array_merge($attendance->only(['student_id', 'class_id', 'date', 'time_start']), $payload);
        $this->validateTimeRange((string) $merged['time_start'], $merged['time_end'] ?? null);

        if ($this->hasDuplicateRecord($merged, $attendance->id)) {
            throw ValidationException::withMessages([
                'time_start' => ['Attendance already exists for this student/class/date/time_start.'],
            ]);
        }

        $attendance->fill($payload)->save();

        return response()->json([
            'message' => 'Attendance updated.',
            'data' => $attendance->fresh()->load(['student.user', 'class']),
        ]);
    }

    public function destroy(Request $request, Attendance $attendance): JsonResponse
    {
        $this->authorize('delete', $attendance);

        if (! $this->canManageClass($request->user(), (int) $attendance->class_id)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $attendance->delete();

        return response()->json([
            'message' => 'Attendance deleted.',
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $this->authorize('export', Attendance::class);

        $filters = $request->validate([
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'status' => ['nullable', 'in:P,A,L'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $query = Attendance::query()
            ->with(['student.user', 'class'])
            ->orderByDesc('date')
            ->orderByDesc('time_start');

        $this->applyVisibilityScope($query, $request->user());

        if (isset($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (isset($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        $rows = $query->get();
        $fileName = 'attendance_export_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            if (! $handle) {
                return;
            }

            fputcsv($handle, [
                'attendance_id',
                'date',
                'time_start',
                'time_end',
                'status',
                'student_id',
                'student_name',
                'class_id',
                'class_name',
            ]);

            foreach ($rows as $item) {
                fputcsv($handle, [
                    $item->id,
                    $item->date,
                    $item->time_start,
                    $item->time_end,
                    $item->status,
                    $item->student_id,
                    $item->student?->user?->name,
                    $item->class_id,
                    $item->class?->name,
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf(Request $request)
    {
        $this->authorize('export', Attendance::class);

        $filters = $request->validate([
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'status' => ['nullable', 'in:P,A,L'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $query = Attendance::query()
            ->with(['student.user', 'class'])
            ->orderByDesc('date')
            ->orderByDesc('time_start');

        $this->applyVisibilityScope($query, $request->user());

        if (isset($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (isset($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        $rows = $query->get();
        $fileName = 'attendance_export_'.now()->format('Ymd_His').'.pdf';

        return Pdf::loadView('pdf.attendance', [
            'rows' => $rows,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape')
            ->download($fileName);
    }

    public function importCsv(Request $request): JsonResponse
    {
        $this->authorize('create', Attendance::class);

        $payload = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $lines = file($payload['file']->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (! is_array($lines) || count($lines) < 2) {
            throw ValidationException::withMessages([
                'file' => ['CSV must include a header row and at least one data row.'],
            ]);
        }

        $header = array_map(fn (string $item): string => trim($item), str_getcsv((string) array_shift($lines)));
        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($lines as $lineNumber => $line) {
            $cells = str_getcsv((string) $line);
            $row = [];
            foreach ($header as $index => $column) {
                $row[$column] = isset($cells[$index]) ? trim((string) $cells[$index]) : null;
            }

            try {
                $record = [
                    'student_id' => (int) ($row['student_id'] ?? 0),
                    'class_id' => (int) ($row['class_id'] ?? 0),
                    'date' => (string) ($row['date'] ?? ''),
                    'time_start' => (string) ($row['time_start'] ?? ''),
                    'time_end' => isset($row['time_end']) && $row['time_end'] !== '' ? (string) $row['time_end'] : null,
                    'status' => (string) ($row['status'] ?? ''),
                ];

                if (
                    $record['student_id'] <= 0
                    || $record['class_id'] <= 0
                    || $record['date'] === ''
                    || $record['time_start'] === ''
                    || ! in_array($record['status'], ['P', 'A', 'L'], true)
                ) {
                    throw ValidationException::withMessages([
                        'row' => ['Missing required attendance columns.'],
                    ]);
                }

                if (! $this->canManageClass($request->user(), (int) $record['class_id'])) {
                    throw ValidationException::withMessages([
                        'class_id' => ['You cannot import attendance for this class.'],
                    ]);
                }

                $this->ensureStudentBelongsToClass((int) $record['student_id'], (int) $record['class_id']);
                $this->validateTimeRange((string) $record['time_start'], $record['time_end']);

                $normalized = $this->normalizeAttendancePayload($record);
                $attendance = Attendance::query()->updateOrCreate(
                    [
                        'student_id' => $normalized['student_id'],
                        'class_id' => $normalized['class_id'],
                        'date' => $normalized['date'],
                        'time_start' => $normalized['time_start'],
                    ],
                    [
                        'time_end' => $normalized['time_end'] ?? null,
                        'status' => $normalized['status'],
                    ]
                );

                if ($attendance->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            } catch (\Throwable $exception) {
                $errors[] = [
                    'line' => $lineNumber + 2,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => 'Attendance CSV import completed.',
            'data' => [
                'created' => $created,
                'updated' => $updated,
                'errors' => $errors,
            ],
        ], 201);
    }

    private function applyVisibilityScope(Builder $query, User $user): void
    {
        if ($user->role === 'super-admin') {
            return;
        }

        if (in_array($user->role, ['admin', 'teacher'], true) && ! $user->school_id) {
            $query->whereRaw('1 = 0');

            return;
        }

        if ($user->role === 'student') {
            $query->whereHas('student', fn (Builder $studentQuery) => $studentQuery->where('user_id', $user->id));

            return;
        }

        if ($user->role === 'parent') {
            $studentIds = $user->children()->pluck('students.id')->all();
            $query->whereIn('student_id', $studentIds === [] ? [-1] : $studentIds);

            return;
        }

        if ($user->school_id) {
            $query->whereHas('class', fn (Builder $classQuery) => $classQuery->where('school_id', $user->school_id));
        }

        if ($user->role === 'teacher') {
            $allowedClassIds = $user->teachingClasses()->pluck('classes.id')->all();
            $query->whereIn('class_id', $allowedClassIds === [] ? [-1] : $allowedClassIds);
        }
    }

    private function canViewAttendance(User $user, Attendance $attendance): bool
    {
        $query = Attendance::query()->whereKey($attendance->id);
        $this->applyVisibilityScope($query, $user);

        return $query->exists();
    }

    private function canManageClass(User $user, int $classId): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        if (! in_array($user->role, ['admin', 'teacher'], true)) {
            return false;
        }

        if (! $user->school_id) {
            return false;
        }

        $class = SchoolClass::query()->find($classId);

        if (! $class) {
            return false;
        }

        if ($user->school_id && $class->school_id !== $user->school_id) {
            return false;
        }

        if ($user->role === 'teacher') {
            return $user->teachingClasses()->where('classes.id', $classId)->exists();
        }

        return true;
    }

    private function ensureStudentBelongsToClass(int $studentId, int $classId): void
    {
        $matches = Student::query()
            ->whereKey($studentId)
            ->where('class_id', $classId)
            ->exists();

        if (! $matches) {
            throw ValidationException::withMessages([
                'student_id' => ['Selected student is not assigned to the selected class.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeAttendancePayload(array $payload): array
    {
        if (array_key_exists('time_start', $payload) && $payload['time_start']) {
            $payload['time_start'] = $payload['time_start'].':00';
        }

        if (array_key_exists('time_end', $payload) && $payload['time_end']) {
            $payload['time_end'] = $payload['time_end'].':00';
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hasDuplicateRecord(array $payload, ?int $exceptId = null): bool
    {
        $query = Attendance::query()
            ->where('student_id', $payload['student_id'])
            ->where('class_id', $payload['class_id'])
            ->whereDate('date', $payload['date'])
            ->where('time_start', $payload['time_start']);

        if ($exceptId) {
            $query->whereKeyNot($exceptId);
        }

        return $query->exists();
    }

    private function validateTimeRange(string $timeStart, ?string $timeEnd): void
    {
        if (! $timeEnd) {
            return;
        }

        if (strtotime($timeEnd) <= strtotime($timeStart)) {
            throw ValidationException::withMessages([
                'time_end' => ['time_end must be after time_start.'],
            ]);
        }
    }
}
