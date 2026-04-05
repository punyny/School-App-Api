<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AttendanceStoreRequest;
use App\Http\Requests\Api\AttendanceUpdateRequest;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Timetable;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'status' => ['nullable', 'in:P,A,L'],
            'period_type' => ['nullable', 'in:month,semester,year,range'],
            'month' => ['nullable', 'date_format:Y-m'],
            'year' => ['nullable', 'integer', 'digits:4', 'min:2000', 'max:2100'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'in:id,date,time_start,time_end,status,created_at,subject_id'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ]);

        $query = Attendance::query()
            ->with(['student.user', 'class', 'subject'])
            ->orderBy($filters['sort_by'] ?? 'date', $filters['sort_dir'] ?? 'desc')
            ->orderBy('time_start', 'desc');

        $this->applyVisibilityScope($query, $request->user());
        $this->applyAttendanceFilters($query, $filters);

        return response()->json($query->paginate($filters['per_page'] ?? 20));
    }

    public function show(Request $request, Attendance $attendance): JsonResponse
    {
        $this->authorize('view', $attendance);

        return response()->json([
            'data' => $attendance->load(['student.user', 'class', 'subject']),
        ]);
    }

    public function monthlyReport(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Attendance::class);

        $filters = $request->validate([
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'period_type' => ['nullable', 'in:month,semester,year,range'],
            'month' => ['nullable', 'date_format:Y-m'],
            'year' => ['nullable', 'integer', 'digits:4', 'min:2000', 'max:2100'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $dateRange = $this->resolveDateRange([
            'period_type' => $filters['period_type'] ?? null,
            'month' => $filters['month'] ?? null,
            'year' => $filters['year'] ?? null,
            'semester' => $filters['semester'] ?? null,
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
        ]);
        $reportMonth = (string) ($filters['month'] ?? '');

        $query = Attendance::query()
            ->with(['student.user', 'class', 'subject'])
            ->orderBy('date')
            ->orderBy('time_start');

        $this->applyVisibilityScope($query, $request->user());
        $this->applyAttendanceFilters($query, [
            'class_id' => $filters['class_id'] ?? null,
            'student_id' => $filters['student_id'] ?? null,
            'subject_id' => $filters['subject_id'] ?? null,
            'date_from' => $dateRange['date_from'],
            'date_to' => $dateRange['date_to'],
        ]);

        $rows = $query->get();
        $absenceEntries = $rows
            ->filter(fn (Attendance $attendance): bool => in_array((string) $attendance->status, ['A', 'L'], true))
            ->values();

        $absenceRows = $absenceEntries
            ->map(function (Attendance $attendance): array {
                return [
                    'attendance_id' => (int) $attendance->id,
                    'student_id' => (int) $attendance->student_id,
                    'student_name' => (string) ($attendance->student?->user?->name ?? 'Student'),
                    'class_id' => (int) $attendance->class_id,
                    'class_name' => (string) ($attendance->class?->name ?? 'Class'),
                    'subject_id' => $attendance->subject_id ? (int) $attendance->subject_id : null,
                    'subject_name' => (string) ($attendance->subject?->name ?? 'General Attendance'),
                    'date' => (string) $attendance->date,
                    'status' => (string) $attendance->status,
                    'remarks' => (string) ($attendance->remarks ?? ''),
                    'time_start' => (string) $attendance->time_start,
                    'time_end' => (string) ($attendance->time_end ?? ''),
                    'time_slot' => trim(((string) $attendance->time_start).' - '.((string) ($attendance->time_end ?? '-'))),
                ];
            })
            ->values();

        $subjectRows = $rows
            ->filter(fn (Attendance $attendance): bool => in_array((string) $attendance->status, ['A', 'L'], true))
            ->groupBy(function (Attendance $attendance): string {
                return implode('|', [
                    (int) $attendance->student_id,
                    (int) $attendance->class_id,
                    (int) ($attendance->subject_id ?? 0),
                    (string) $attendance->time_start,
                    (string) ($attendance->time_end ?? ''),
                ]);
            })
            ->map(function ($entries) {
                $sortedEntries = $entries->sortBy('date')->values();
                /** @var Attendance $first */
                $first = $sortedEntries->first();
                $presentCount = $sortedEntries->where('status', 'P')->count();
                $absentCount = $sortedEntries->where('status', 'A')->count();
                $leaveCount = $sortedEntries->where('status', 'L')->count();
                $affectedDates = $sortedEntries
                    ->filter(fn (Attendance $attendance): bool => in_array($attendance->status, ['A', 'L'], true))
                    ->map(fn (Attendance $attendance): array => [
                        'date' => (string) $attendance->date,
                        'status' => (string) $attendance->status,
                        'remarks' => (string) ($attendance->remarks ?? ''),
                    ])
                    ->values()
                    ->all();

                return [
                    'student_id' => (int) $first->student_id,
                    'student_name' => (string) ($first->student?->user?->name ?? 'Student'),
                    'class_id' => (int) $first->class_id,
                    'class_name' => (string) ($first->class?->name ?? 'Class'),
                    'subject_id' => $first->subject_id ? (int) $first->subject_id : null,
                    'subject_name' => (string) ($first->subject?->name ?? 'General Attendance'),
                    'time_start' => (string) $first->time_start,
                    'time_end' => (string) ($first->time_end ?? ''),
                    'time_slot' => trim(((string) $first->time_start).' - '.((string) ($first->time_end ?? '-'))),
                    'present_count' => $presentCount,
                    'absent_count' => $absentCount,
                    'leave_count' => $leaveCount,
                    'total_missed' => $absentCount + $leaveCount,
                    'reasons' => $sortedEntries
                        ->pluck('remarks')
                        ->filter(fn ($value): bool => trim((string) $value) !== '')
                        ->unique()
                        ->values()
                        ->all(),
                    'affected_dates' => $affectedDates,
                ];
            })
            ->sortByDesc(fn (array $row): int => ((int) $row['total_missed'] * 1000) + (int) $row['absent_count'])
            ->values();

        $studentTotals = $absenceEntries
            ->groupBy(fn (Attendance $attendance): int => (int) $attendance->student_id)
            ->map(function ($entries) {
                $sortedEntries = $entries->sortBy('date')->values();
                /** @var Attendance $first */
                $first = $sortedEntries->first();

                return [
                    'student_id' => (int) $first->student_id,
                    'student_name' => (string) ($first->student?->user?->name ?? 'Student'),
                    'class_id' => (int) $first->class_id,
                    'class_name' => (string) ($first->class?->name ?? 'Class'),
                    'absent_count' => $sortedEntries->where('status', 'A')->count(),
                    'leave_count' => $sortedEntries->where('status', 'L')->count(),
                    'total_missed' => $sortedEntries->count(),
                    'subject_names' => $sortedEntries
                        ->map(fn (Attendance $attendance): string => (string) ($attendance->subject?->name ?? 'General Attendance'))
                        ->unique()
                        ->values()
                        ->all(),
                ];
            })
            ->sortByDesc(fn (array $row): int => ((int) $row['total_missed'] * 1000) + (int) $row['absent_count'])
            ->values();

        $classRows = collect();
        if (in_array($request->user()->role, ['super-admin', 'admin'], true)) {
            $classRows = $rows
                ->groupBy(fn (Attendance $attendance): int => (int) $attendance->class_id)
                ->map(function ($entries) {
                    $sortedEntries = $entries->sortBy('date')->values();
                    /** @var Attendance $first */
                    $first = $sortedEntries->first();

                    return [
                        'class_id' => (int) $first->class_id,
                        'class_name' => (string) ($first->class?->name ?? 'Class'),
                        'students_count' => $sortedEntries->pluck('student_id')->unique()->count(),
                        'subjects_count' => $sortedEntries->pluck('subject_id')->filter()->unique()->count(),
                        'present_count' => $sortedEntries->where('status', 'P')->count(),
                        'absent_count' => $sortedEntries->where('status', 'A')->count(),
                        'leave_count' => $sortedEntries->where('status', 'L')->count(),
                        'total_records' => $sortedEntries->count(),
                    ];
                })
                ->sortByDesc(fn (array $row): int => ((int) $row['absent_count'] * 1000) + (int) $row['leave_count'])
                ->values();
        }

        return response()->json([
            'data' => [
                'month' => $reportMonth,
                'date_from' => $dateRange['date_from'],
                'date_to' => $dateRange['date_to'],
                'period_mode' => $dateRange['period_type'],
                'summary' => [
                    'total_records' => $rows->count(),
                    'students_count' => $rows->pluck('student_id')->unique()->count(),
                    'subjects_count' => $rows->pluck('subject_id')->filter()->unique()->count(),
                    'present_count' => $rows->where('status', 'P')->count(),
                    'absent_count' => $rows->where('status', 'A')->count(),
                    'leave_count' => $rows->where('status', 'L')->count(),
                    'total_missed_records' => $absenceEntries->count(),
                    'affected_students_count' => $absenceEntries->pluck('student_id')->unique()->count(),
                ],
                'absence_rows' => $absenceRows,
                'subject_rows' => $subjectRows,
                'student_totals' => $studentTotals,
                'class_rows' => $classRows,
            ],
        ]);
    }

    public function trackingContext(Request $request): JsonResponse
    {
        $this->authorize('create', Attendance::class);

        $payload = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'date' => ['required', 'date'],
        ]);

        $user = $request->user();
        $classId = (int) $payload['class_id'];
        $date = Carbon::parse((string) $payload['date'])->toDateString();
        $dayOfWeek = strtolower(Carbon::parse($date)->format('l'));

        if (! $this->canManageClass($user, $classId)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        /** @var SchoolClass|null $class */
        $class = SchoolClass::query()
            ->with([
                'students.user',
                'timetables' => fn ($query) => $query
                    ->where('day_of_week', $dayOfWeek)
                    ->with(['subject', 'teacher'])
                    ->orderBy('time_start'),
            ])
            ->find($classId);

        if (! $class) {
            return response()->json(['message' => 'Class not found.'], 404);
        }

        $studyDays = $this->resolveClassStudyDays($class);
        $isStudyDay = in_array($dayOfWeek, $studyDays, true);

        $sessionRows = collect($class->timetables ?? [])
            ->filter(fn (Timetable $row): bool => $this->canManageSubject($user, $classId, (int) $row->subject_id))
            ->values();

        $studentIds = collect($class->students ?? [])->pluck('id')->map(fn ($id): int => (int) $id)->filter()->unique()->values()->all();
        $eligibleStudents = [];
        $blockedStudents = [];

        foreach ($class->students as $student) {
            $studentId = (int) $student->id;
            if ($studentId <= 0) {
                continue;
            }

            $enrollmentDate = $this->resolveStudentEnrollmentDateForClass($studentId, $classId);
            $studentName = (string) ($student->user?->name ?? 'Student '.$studentId);
            $studentCode = (string) ($student->student_code ?? '');

            $entry = [
                'id' => $studentId,
                'name' => $studentName,
                'student_code' => $studentCode,
                'enrollment_date' => $enrollmentDate?->toDateString(),
            ];

            if (! $enrollmentDate || Carbon::parse($date)->lt($enrollmentDate)) {
                $blockedStudents[] = $entry;

                continue;
            }

            $eligibleStudents[] = $entry;
        }

        $sessions = $sessionRows->map(function (Timetable $row): array {
            return [
                'timetable_id' => (int) $row->id,
                'subject_id' => (int) $row->subject_id,
                'subject_name' => (string) ($row->subject?->name ?? 'Subject'),
                'teacher_id' => (int) ($row->teacher_id ?? 0),
                'teacher_name' => (string) ($row->teacher?->name ?? 'Teacher'),
                'time_start' => substr((string) $row->time_start, 0, 5),
                'time_end' => substr((string) $row->time_end, 0, 5),
            ];
        })->values()->all();

        return response()->json([
            'data' => [
                'class_id' => $classId,
                'class_name' => (string) ($class->name ?? 'Class'),
                'date' => $date,
                'day_of_week' => $dayOfWeek,
                'is_study_day' => $isStudyDay,
                'class_schedule' => [
                    'study_days' => $studyDays,
                    'study_time_start' => $class->study_time_start ? substr((string) $class->study_time_start, 0, 5) : null,
                    'study_time_end' => $class->study_time_end ? substr((string) $class->study_time_end, 0, 5) : null,
                ],
                'sessions' => $sessions,
                'eligible_students' => $eligibleStudents,
                'blocked_students' => $blockedStudents,
                'total_students' => count($studentIds),
                'eligible_students_count' => count($eligibleStudents),
                'blocked_students_count' => count($blockedStudents),
            ],
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
        $this->ensureSubjectBelongsToClass((int) $payload['class_id'], isset($payload['subject_id']) ? (int) $payload['subject_id'] : null);

        if (! $this->canManageSubject($user, (int) $payload['class_id'], isset($payload['subject_id']) ? (int) $payload['subject_id'] : null)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $payload = $this->normalizeAttendancePayload($payload);
        $this->ensureStudentEnrollmentDate((int) $payload['student_id'], (int) $payload['class_id'], (string) $payload['date']);
        $this->validateClassScheduleWindow(
            classId: (int) $payload['class_id'],
            date: (string) $payload['date'],
            timeStart: (string) $payload['time_start'],
            timeEnd: $payload['time_end'] ?? null,
        );
        $this->ensureSubjectSessionMatchesTimetable(
            classId: (int) $payload['class_id'],
            subjectId: isset($payload['subject_id']) ? (int) $payload['subject_id'] : null,
            date: (string) $payload['date'],
            timeStart: (string) $payload['time_start'],
            timeEnd: $payload['time_end'] ?? null,
        );
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

    public function storeDailySheet(Request $request): JsonResponse
    {
        $this->authorize('create', Attendance::class);

        $payload = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'date' => ['required', 'date'],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end' => ['nullable', 'date_format:H:i', 'after:time_start'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'records.*.status' => ['required', 'in:P,A,L'],
            'records.*.remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $classId = (int) $payload['class_id'];

        if (! $this->canManageClass($user, $classId)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $subjectId = isset($payload['subject_id']) ? (int) $payload['subject_id'] : null;
        $this->ensureSubjectBelongsToClass($classId, $subjectId);

        if (! $this->canManageSubject($user, $classId, $subjectId)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $basePayload = $this->normalizeAttendancePayload([
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'date' => $payload['date'],
            'time_start' => $payload['time_start'],
            'time_end' => $payload['time_end'] ?? null,
        ]);

        $this->validateClassScheduleWindow(
            classId: $classId,
            date: (string) $basePayload['date'],
            timeStart: (string) $basePayload['time_start'],
            timeEnd: $basePayload['time_end'] ?? null,
        );
        $this->ensureSubjectSessionMatchesTimetable(
            classId: $classId,
            subjectId: $subjectId,
            date: (string) $basePayload['date'],
            timeStart: (string) $basePayload['time_start'],
            timeEnd: $basePayload['time_end'] ?? null,
        );
        $this->validateTimeRange((string) $basePayload['time_start'], $basePayload['time_end'] ?? null);

        $created = 0;
        $updated = 0;
        $savedRecords = [];

        foreach ($payload['records'] as $record) {
            $studentId = (int) ($record['student_id'] ?? 0);
            $this->ensureStudentBelongsToClass($studentId, $classId);
            $this->ensureStudentEnrollmentDate($studentId, $classId, (string) $basePayload['date']);

            $attendance = Attendance::query()->updateOrCreate(
                [
                    'student_id' => $studentId,
                    'class_id' => $classId,
                    'date' => $basePayload['date'],
                    'time_start' => $basePayload['time_start'],
                    'subject_id' => $basePayload['subject_id'] ?? null,
                ],
                [
                    'time_end' => $basePayload['time_end'] ?? null,
                    'status' => (string) $record['status'],
                    'remarks' => isset($record['remarks']) && trim((string) $record['remarks']) !== ''
                        ? trim((string) $record['remarks'])
                        : null,
                ]
            );

            if ($attendance->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }

            $savedRecords[] = $attendance->fresh()->load(['student.user', 'class', 'subject']);
        }

        return response()->json([
            'message' => 'Daily attendance sheet saved successfully.',
            'data' => [
                'created' => $created,
                'updated' => $updated,
                'records' => $savedRecords,
            ],
        ], 201);
    }

    public function update(AttendanceUpdateRequest $request, Attendance $attendance): JsonResponse
    {
        $this->authorize('update', $attendance);

        $user = $request->user();
        $payload = $request->validated();

        $targetClassId = (int) ($payload['class_id'] ?? $attendance->class_id);
        $targetStudentId = (int) ($payload['student_id'] ?? $attendance->student_id);
        $targetSubjectId = array_key_exists('subject_id', $payload)
            ? (isset($payload['subject_id']) ? (int) $payload['subject_id'] : null)
            : ($attendance->subject_id ? (int) $attendance->subject_id : null);

        if (! $this->canManageClass($user, $targetClassId)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $this->ensureStudentBelongsToClass($targetStudentId, $targetClassId);
        $this->ensureSubjectBelongsToClass($targetClassId, $targetSubjectId);

        if (! $this->canManageSubject($user, $targetClassId, $targetSubjectId)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $payload = $this->normalizeAttendancePayload($payload);
        $merged = array_merge($attendance->only(['student_id', 'class_id', 'subject_id', 'date', 'time_start']), $payload);
        $this->ensureStudentEnrollmentDate((int) $merged['student_id'], (int) $merged['class_id'], (string) $merged['date']);
        $this->validateClassScheduleWindow(
            classId: (int) $merged['class_id'],
            date: (string) $merged['date'],
            timeStart: (string) $merged['time_start'],
            timeEnd: $merged['time_end'] ?? null,
        );
        $this->ensureSubjectSessionMatchesTimetable(
            classId: (int) $merged['class_id'],
            subjectId: isset($merged['subject_id']) ? (int) $merged['subject_id'] : null,
            date: (string) $merged['date'],
            timeStart: (string) $merged['time_start'],
            timeEnd: $merged['time_end'] ?? null,
        );
        $this->validateTimeRange((string) $merged['time_start'], $merged['time_end'] ?? null);

        if ($this->hasDuplicateRecord($merged, $attendance->id)) {
            throw ValidationException::withMessages([
                'time_start' => ['Attendance already exists for this student/class/date/time_start.'],
            ]);
        }

        $attendance->fill($payload)->save();

        return response()->json([
            'message' => 'Attendance updated.',
            'data' => $attendance->fresh()->load(['student.user', 'class', 'subject']),
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
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'status' => ['nullable', 'in:P,A,L'],
            'period_type' => ['nullable', 'in:month,semester,year,range'],
            'month' => ['nullable', 'date_format:Y-m'],
            'year' => ['nullable', 'integer', 'digits:4', 'min:2000', 'max:2100'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $query = Attendance::query()
            ->with(['student.user', 'class', 'subject'])
            ->orderByDesc('date')
            ->orderByDesc('time_start');

        $this->applyVisibilityScope($query, $request->user());
        $this->applyAttendanceFilters($query, $filters);

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
                'subject_id',
                'subject_name',
                'remarks',
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
                    $item->subject_id,
                    $item->subject?->name,
                    $item->remarks,
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
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'status' => ['nullable', 'in:P,A,L'],
            'period_type' => ['nullable', 'in:month,semester,year,range'],
            'month' => ['nullable', 'date_format:Y-m'],
            'year' => ['nullable', 'integer', 'digits:4', 'min:2000', 'max:2100'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $query = Attendance::query()
            ->with(['student.user', 'class', 'subject'])
            ->orderByDesc('date')
            ->orderByDesc('time_start');

        $this->applyVisibilityScope($query, $request->user());
        $this->applyAttendanceFilters($query, $filters);

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
                    'subject_id' => isset($row['subject_id']) && $row['subject_id'] !== '' ? (int) $row['subject_id'] : null,
                    'date' => (string) ($row['date'] ?? ''),
                    'time_start' => (string) ($row['time_start'] ?? ''),
                    'time_end' => isset($row['time_end']) && $row['time_end'] !== '' ? (string) $row['time_end'] : null,
                    'status' => (string) ($row['status'] ?? ''),
                    'remarks' => isset($row['remarks']) && $row['remarks'] !== '' ? (string) $row['remarks'] : null,
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

                $this->ensureSubjectBelongsToClass((int) $record['class_id'], isset($record['subject_id']) ? (int) $record['subject_id'] : null);

                if (! $this->canManageSubject($request->user(), (int) $record['class_id'], isset($record['subject_id']) ? (int) $record['subject_id'] : null)) {
                    throw ValidationException::withMessages([
                        'subject_id' => ['You cannot import attendance for this class subject.'],
                    ]);
                }

                $this->ensureStudentBelongsToClass((int) $record['student_id'], (int) $record['class_id']);
                $this->ensureStudentEnrollmentDate((int) $record['student_id'], (int) $record['class_id'], (string) $record['date']);
                $this->validateClassScheduleWindow(
                    classId: (int) $record['class_id'],
                    date: (string) $record['date'],
                    timeStart: (string) $record['time_start'],
                    timeEnd: $record['time_end'],
                );
                $this->ensureSubjectSessionMatchesTimetable(
                    classId: (int) $record['class_id'],
                    subjectId: isset($record['subject_id']) ? (int) $record['subject_id'] : null,
                    date: (string) $record['date'],
                    timeStart: (string) $record['time_start'],
                    timeEnd: $record['time_end'],
                );
                $this->validateTimeRange((string) $record['time_start'], $record['time_end']);

                $normalized = $this->normalizeAttendancePayload($record);
                $attendance = Attendance::query()->updateOrCreate(
                    [
                        'student_id' => $normalized['student_id'],
                        'class_id' => $normalized['class_id'],
                        'date' => $normalized['date'],
                        'time_start' => $normalized['time_start'],
                        'subject_id' => $normalized['subject_id'] ?? null,
                    ],
                    [
                        'time_end' => $normalized['time_end'] ?? null,
                        'status' => $normalized['status'],
                        'remarks' => $normalized['remarks'] ?? null,
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

    /**
     * @return array<int, string>
     */
    private function resolveClassStudyDays(SchoolClass $class): array
    {
        $allDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $studyDays = collect($class->study_days ?? [])
            ->map(fn ($day): string => strtolower(trim((string) $day)))
            ->filter(fn (string $day): bool => in_array($day, $allDays, true))
            ->unique()
            ->values()
            ->all();

        return $studyDays === [] ? $allDays : $studyDays;
    }

    private function validateClassScheduleWindow(
        int $classId,
        string $date,
        string $timeStart,
        ?string $timeEnd
    ): void {
        /** @var SchoolClass|null $class */
        $class = SchoolClass::query()->find($classId);
        if (! $class) {
            throw ValidationException::withMessages([
                'class_id' => ['Selected class is invalid.'],
            ]);
        }

        $dayOfWeek = strtolower(Carbon::parse($date)->format('l'));
        $studyDays = $this->resolveClassStudyDays($class);

        if (! in_array($dayOfWeek, $studyDays, true)) {
            throw ValidationException::withMessages([
                'date' => ['Selected date is outside class study days.'],
            ]);
        }

        if ($class->study_time_start && $class->study_time_end) {
            $startText = substr((string) $class->study_time_start, 0, 8);
            $endText = substr((string) $class->study_time_end, 0, 8);
            $targetEnd = $timeEnd ?: $timeStart;

            if (strtotime($timeStart) < strtotime($startText) || strtotime($targetEnd) > strtotime($endText)) {
                throw ValidationException::withMessages([
                    'time_start' => ['Attendance time is outside configured class study hours.'],
                ]);
            }
        }
    }

    private function ensureStudentEnrollmentDate(int $studentId, int $classId, string $date): void
    {
        $enrollmentDate = $this->resolveStudentEnrollmentDateForClass($studentId, $classId);
        if (! $enrollmentDate) {
            throw ValidationException::withMessages([
                'student_id' => ['Student enrollment date is missing for this class. Please set enrollment first.'],
            ]);
        }

        if (Carbon::parse($date)->lt($enrollmentDate)) {
            throw ValidationException::withMessages([
                'date' => ['Attendance date cannot be earlier than student enrollment date ('.$enrollmentDate->toDateString().').'],
            ]);
        }
    }

    private function resolveStudentEnrollmentDateForClass(int $studentId, int $classId): ?Carbon
    {
        $enrollment = Enrollment::query()
            ->where('student_id', $studentId)
            ->where('class_id', $classId)
            ->whereIn('status', ['Enrolled', 'Promoted', 'Completed'])
            ->orderBy('enrollment_date')
            ->first();

        if ($enrollment && $enrollment->enrollment_date) {
            return Carbon::parse((string) $enrollment->enrollment_date)->startOfDay();
        }

        $student = Student::query()->find($studentId);
        if ($student && $student->admission_date) {
            return Carbon::parse((string) $student->admission_date)->startOfDay();
        }

        $class = SchoolClass::query()
            ->with('school:id,config_details')
            ->find($classId);
        $defaultEnrollmentDate = trim((string) data_get($class, 'school.config_details.default_enrollment_date', ''));
        if ($defaultEnrollmentDate !== '') {
            try {
                return Carbon::parse($defaultEnrollmentDate)->startOfDay();
            } catch (\Throwable) {
                // Ignore invalid legacy config values and continue to null.
            }
        }

        return null;
    }

    private function ensureSubjectSessionMatchesTimetable(
        int $classId,
        ?int $subjectId,
        string $date,
        string $timeStart,
        ?string $timeEnd
    ): void {
        if (! $subjectId || $subjectId <= 0) {
            return;
        }

        $dayOfWeek = strtolower(Carbon::parse($date)->format('l'));
        $sessionEnd = $timeEnd ?: $timeStart;

        $rows = Timetable::query()
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('day_of_week', $dayOfWeek)
            ->orderBy('time_start')
            ->get(['time_start', 'time_end']);

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'subject_id' => ['No timetable session found for selected subject on this date.'],
            ]);
        }

        $matched = $rows->contains(function (Timetable $row) use ($timeStart, $sessionEnd): bool {
            $rowStart = (string) $row->time_start;
            $rowEnd = (string) $row->time_end;

            return strtotime($timeStart) >= strtotime($rowStart)
                && strtotime($sessionEnd) <= strtotime($rowEnd);
        });

        if (! $matched) {
            throw ValidationException::withMessages([
                'time_start' => ['Attendance time must match timetable period for the selected subject.'],
            ]);
        }
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
            $classIds = $user->teachingClasses()->pluck('classes.id')->all();
            $subjectIds = $user->teachingClasses()->pluck('teacher_class.subject_id')->filter()->all();

            $query->whereIn('class_id', $classIds === [] ? [-1] : $classIds)
                ->whereIn('subject_id', $subjectIds === [] ? [-1] : $subjectIds);
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

    private function canManageSubject(User $user, int $classId, ?int $subjectId): bool
    {
        if (! $this->canManageClass($user, $classId)) {
            return false;
        }

        if (! $subjectId) {
            return true;
        }

        if ($user->role === 'super-admin' || $user->role === 'admin') {
            return true;
        }

        if ($user->role !== 'teacher') {
            return false;
        }

        return $user->teachingClasses()
            ->where('classes.id', $classId)
            ->wherePivot('subject_id', $subjectId)
            ->exists();
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

    private function ensureSubjectBelongsToClass(int $classId, ?int $subjectId): void
    {
        if (! $subjectId) {
            return;
        }

        $class = SchoolClass::query()->find($classId);
        $subject = Subject::query()->find($subjectId);

        if (! $class || ! $subject) {
            throw ValidationException::withMessages([
                'subject_id' => ['Selected subject is invalid.'],
            ]);
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
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeAttendancePayload(array $payload): array
    {
        if (array_key_exists('subject_id', $payload) && (int) ($payload['subject_id'] ?? 0) <= 0) {
            $payload['subject_id'] = null;
        }

        if (array_key_exists('remarks', $payload)) {
            $remarks = trim((string) ($payload['remarks'] ?? ''));
            $payload['remarks'] = $remarks !== '' ? $remarks : null;
        }

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

        if (array_key_exists('subject_id', $payload)) {
            if ($payload['subject_id']) {
                $query->where('subject_id', $payload['subject_id']);
            } else {
                $query->whereNull('subject_id');
            }
        }

        if ($exceptId) {
            $query->whereKeyNot($exceptId);
        }

        return $query->exists();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyAttendanceFilters(Builder $query, array $filters): void
    {
        if (isset($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (isset($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (isset($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $dateRange = $this->resolveDateRange($filters);

        if ($dateRange['date_from']) {
            $query->whereDate('date', '>=', $dateRange['date_from']);
        }

        if ($dateRange['date_to']) {
            $query->whereDate('date', '<=', $dateRange['date_to']);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{date_from:?string,date_to:?string,period_type:string}
     */
    private function resolveDateRange(array $filters): array
    {
        $periodType = trim((string) ($filters['period_type'] ?? ''));
        $month = trim((string) ($filters['month'] ?? ''));

        if ($periodType === '' && $month !== '') {
            $periodType = 'month';
        }

        if ($periodType === '' && ! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            $periodType = 'range';
        }

        if ($periodType === '' && ! empty($filters['year']) && ! empty($filters['semester'])) {
            $periodType = 'semester';
        }

        if ($periodType === '' && ! empty($filters['year'])) {
            $periodType = 'year';
        }

        if ($periodType === 'semester') {
            $year = (int) ($filters['year'] ?? 0);
            $semester = (int) ($filters['semester'] ?? 0);
            if ($year > 0 && in_array($semester, [1, 2], true)) {
                $startMonth = $semester === 1 ? 1 : 7;
                $start = Carbon::create($year, $startMonth, 1)->startOfMonth();
                $end = $start->copy()->addMonths(5)->endOfMonth();

                return [
                    'date_from' => $start->toDateString(),
                    'date_to' => $end->toDateString(),
                    'period_type' => 'semester',
                ];
            }
        }

        if ($periodType === 'year') {
            $year = (int) ($filters['year'] ?? 0);
            if ($year > 0) {
                $start = Carbon::create($year, 1, 1)->startOfYear();

                return [
                    'date_from' => $start->toDateString(),
                    'date_to' => $start->copy()->endOfYear()->toDateString(),
                    'period_type' => 'year',
                ];
            }
        }

        if (($periodType === '' || $periodType === 'month') && $month !== '') {
            $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

            return [
                'date_from' => $monthDate->toDateString(),
                'date_to' => $monthDate->copy()->endOfMonth()->toDateString(),
                'period_type' => 'month',
            ];
        }

        return [
            'date_from' => isset($filters['date_from']) ? (string) $filters['date_from'] : null,
            'date_to' => isset($filters['date_to']) ? (string) $filters['date_to'] : null,
            'period_type' => 'range',
        ];
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
