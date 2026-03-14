<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ScoreStoreRequest;
use App\Http\Requests\Api\ScoreUpdateRequest;
use App\Models\SchoolClass;
use App\Models\Score;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScoreController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Score::class);

        $filters = $request->validate([
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'assessment_type' => ['nullable', 'in:monthly,semester,yearly'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'semester' => ['nullable', 'integer', 'between:1,2'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'period' => ['nullable', 'string', 'max:50'],
            'rank_in_class' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'in:id,total_score,exam_score,month,semester,quarter,academic_year,rank_in_class,created_at'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ]);

        $query = Score::query()
            ->with(['student.user', 'class', 'subject'])
            ->orderBy(
                $filters['sort_by'] ?? 'created_at',
                $filters['sort_dir'] ?? 'desc'
            );

        $this->applyVisibilityScope($query, $request->user());

        foreach (['class_id', 'student_id', 'subject_id', 'assessment_type', 'month', 'semester', 'academic_year', 'quarter', 'period', 'rank_in_class'] as $key) {
            if (isset($filters[$key])) {
                $query->where($key, $filters[$key]);
            }
        }

        return response()->json($query->paginate($filters['per_page'] ?? 20));
    }

    public function show(Request $request, Score $score): JsonResponse
    {
        $this->authorize('view', $score);

        return response()->json([
            'data' => $score->load(['student.user', 'class', 'subject']),
        ]);
    }

    public function store(ScoreStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Score::class);

        $payload = $this->prepareScorePayloadForWrite($request->validated());

        if (! $this->canManageScoreTarget(
            $request->user(),
            (int) $payload['class_id'],
            (int) $payload['subject_id']
        )) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $this->ensureStudentBelongsToClass((int) $payload['student_id'], (int) $payload['class_id']);

        $score = Score::query()->create($payload);
        $this->recomputeRankForBucket($score);
        $score = $score->fresh();

        return response()->json([
            'message' => 'Score created.',
            'data' => $score->load(['student.user', 'class', 'subject']),
        ], 201);
    }

    public function update(ScoreUpdateRequest $request, Score $score): JsonResponse
    {
        $this->authorize('update', $score);

        $payload = $request->validated();
        $targetClassId = (int) ($payload['class_id'] ?? $score->class_id);
        $targetStudentId = (int) ($payload['student_id'] ?? $score->student_id);
        $targetSubjectId = (int) ($payload['subject_id'] ?? $score->subject_id);

        if (! $this->canManageScoreTarget($request->user(), $targetClassId, $targetSubjectId)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $this->ensureStudentBelongsToClass($targetStudentId, $targetClassId);

        $payload = $this->prepareScorePayloadForWrite($payload, $score);

        $oldBucket = $this->scoreBucket([
            'class_id' => $score->class_id,
            'subject_id' => $score->subject_id,
            'assessment_type' => $score->assessment_type,
            'academic_year' => $score->academic_year,
            'month' => $score->month,
            'semester' => $score->semester,
            'quarter' => $score->quarter,
            'period' => $score->period,
        ]);

        $score->fill($payload)->save();
        $this->recomputeRankForBucketValues($oldBucket);
        $this->recomputeRankForBucket($score);

        return response()->json([
            'message' => 'Score updated.',
            'data' => $score->fresh()->load(['student.user', 'class', 'subject']),
        ]);
    }

    public function destroy(Request $request, Score $score): JsonResponse
    {
        $this->authorize('delete', $score);

        if (! $this->canManageScoreTarget($request->user(), (int) $score->class_id, (int) $score->subject_id)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $oldBucket = $this->scoreBucket([
            'class_id' => $score->class_id,
            'subject_id' => $score->subject_id,
            'assessment_type' => $score->assessment_type,
            'academic_year' => $score->academic_year,
            'month' => $score->month,
            'semester' => $score->semester,
            'quarter' => $score->quarter,
            'period' => $score->period,
        ]);

        $score->delete();
        $this->recomputeRankForBucketValues($oldBucket);

        return response()->json([
            'message' => 'Score deleted.',
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $this->authorize('export', Score::class);

        $filters = $request->validate([
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'assessment_type' => ['nullable', 'in:monthly,semester,yearly'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'semester' => ['nullable', 'integer', 'between:1,2'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'period' => ['nullable', 'string', 'max:50'],
        ]);

        $query = Score::query()
            ->with(['student.user', 'class', 'subject'])
            ->orderByDesc('created_at');

        $this->applyVisibilityScope($query, $request->user());

        foreach (['class_id', 'student_id', 'subject_id', 'assessment_type', 'month', 'semester', 'academic_year', 'quarter', 'period'] as $key) {
            if (isset($filters[$key])) {
                $query->where($key, $filters[$key]);
            }
        }

        $rows = $query->get();
        $fileName = 'scores_export_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            if (! $handle) {
                return;
            }

            fputcsv($handle, [
                'score_id',
                'student_id',
                'student_name',
                'class_id',
                'class_name',
                'subject_id',
                'subject_name',
                'exam_score',
                'total_score',
                'assessment_type',
                'month',
                'semester',
                'academic_year',
                'quarter',
                'period',
                'grade',
                'rank_in_class',
            ]);

            foreach ($rows as $item) {
                fputcsv($handle, [
                    $item->id,
                    $item->student_id,
                    $item->student?->user?->name,
                    $item->class_id,
                    $item->class?->name,
                    $item->subject_id,
                    $item->subject?->name,
                    $item->exam_score,
                    $item->total_score,
                    $item->assessment_type,
                    $item->month,
                    $item->semester,
                    $item->academic_year,
                    $item->quarter,
                    $item->period,
                    $item->grade,
                    $item->rank_in_class,
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf(Request $request)
    {
        $this->authorize('export', Score::class);

        $filters = $request->validate([
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'assessment_type' => ['nullable', 'in:monthly,semester,yearly'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'semester' => ['nullable', 'integer', 'between:1,2'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'period' => ['nullable', 'string', 'max:50'],
        ]);

        $query = Score::query()
            ->with(['student.user', 'class', 'subject'])
            ->orderByDesc('created_at');

        $this->applyVisibilityScope($query, $request->user());

        foreach (['class_id', 'student_id', 'subject_id', 'assessment_type', 'month', 'semester', 'academic_year', 'quarter', 'period'] as $key) {
            if (isset($filters[$key])) {
                $query->where($key, $filters[$key]);
            }
        }

        $rows = $query->get();
        $fileName = 'scores_export_'.now()->format('Ymd_His').'.pdf';

        return Pdf::loadView('pdf.scores', [
            'rows' => $rows,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape')
            ->download($fileName);
    }

    public function importCsv(Request $request): JsonResponse
    {
        $this->authorize('create', Score::class);

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
                $studentId = (int) ($row['student_id'] ?? 0);
                $classId = (int) ($row['class_id'] ?? 0);
                $subjectId = (int) ($row['subject_id'] ?? 0);
                $examScore = isset($row['exam_score']) ? (float) $row['exam_score'] : null;
                $totalScore = isset($row['total_score']) ? (float) $row['total_score'] : null;
                $assessmentType = (string) ($row['assessment_type'] ?? $row['type'] ?? '');
                $month = isset($row['month']) && $row['month'] !== '' ? (int) $row['month'] : null;
                $semester = isset($row['semester']) && $row['semester'] !== '' ? (int) $row['semester'] : null;
                $academicYear = isset($row['academic_year']) ? trim((string) $row['academic_year']) : null;
                $quarter = isset($row['quarter']) && $row['quarter'] !== '' ? (int) $row['quarter'] : null;
                $period = isset($row['period']) ? trim((string) $row['period']) : null;
                $rankInClass = isset($row['rank_in_class']) && $row['rank_in_class'] !== '' ? (int) $row['rank_in_class'] : null;

                if (
                    $studentId <= 0 || $classId <= 0 || $subjectId <= 0
                    || $examScore === null || $totalScore === null
                ) {
                    throw ValidationException::withMessages([
                        'row' => ['Invalid or missing required score columns.'],
                    ]);
                }

                if (! $this->canManageScoreTarget($request->user(), $classId, $subjectId)) {
                    throw ValidationException::withMessages([
                        'subject_id' => ['You cannot import scores for this class/subject assignment.'],
                    ]);
                }

                $this->ensureStudentBelongsToClass($studentId, $classId);
                $payloadRow = $this->prepareScorePayloadForWrite([
                    'student_id' => $studentId,
                    'class_id' => $classId,
                    'subject_id' => $subjectId,
                    'exam_score' => $examScore,
                    'total_score' => $totalScore,
                    'assessment_type' => $assessmentType !== '' ? $assessmentType : null,
                    'month' => $month,
                    'semester' => $semester,
                    'academic_year' => $academicYear !== '' ? $academicYear : null,
                    'quarter' => $quarter,
                    'period' => $period !== '' ? $period : null,
                    'rank_in_class' => $rankInClass,
                    'grade' => $row['grade'] ?? null,
                ]);

                $score = Score::query()->updateOrCreate(
                    [
                        'student_id' => $studentId,
                        'class_id' => $classId,
                        'subject_id' => $subjectId,
                        'assessment_type' => $payloadRow['assessment_type'],
                        'month' => $payloadRow['month'],
                        'semester' => $payloadRow['semester'],
                        'academic_year' => $payloadRow['academic_year'],
                        'quarter' => $payloadRow['quarter'],
                        'period' => $payloadRow['period'],
                    ],
                    [
                        'exam_score' => $payloadRow['exam_score'],
                        'total_score' => $payloadRow['total_score'],
                        'grade' => $payloadRow['grade'],
                        'rank_in_class' => $payloadRow['rank_in_class'] ?? null,
                    ]
                );

                $this->recomputeRankForBucket($score);

                if ($score->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            } catch (\Throwable $exception) {
                $message = $exception instanceof ValidationException
                    ? (collect($exception->errors())->flatten()->first() ?? $exception->getMessage())
                    : $exception->getMessage();

                $errors[] = [
                    'line' => $lineNumber + 2,
                    'message' => $message,
                ];
            }
        }

        return response()->json([
            'message' => 'Score CSV import completed.',
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

        if ($user->role === 'student') {
            $studentId = $user->studentProfile?->id;
            $query->whereIn('student_id', $studentId ? [$studentId] : [-1]);

            return;
        }

        if ($user->role === 'parent') {
            $studentIds = $user->children()->pluck('students.id')->all();
            $query->whereIn('student_id', $studentIds === [] ? [-1] : $studentIds);

            return;
        }

        if (! $user->school_id) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('class', fn (Builder $classQuery) => $classQuery->where('school_id', $user->school_id));

        if ($user->role === 'teacher') {
            $query->whereExists(function ($subQuery) use ($user): void {
                $subQuery->selectRaw('1')
                    ->from('teacher_class')
                    ->whereColumn('teacher_class.class_id', 'scores.class_id')
                    ->whereColumn('teacher_class.subject_id', 'scores.subject_id')
                    ->where('teacher_class.teacher_id', $user->id);
            });
        }
    }

    private function canViewScore(User $user, Score $score): bool
    {
        $query = Score::query()->whereKey($score->id);
        $this->applyVisibilityScope($query, $user);

        return $query->exists();
    }

    private function canManageScoreTarget(User $user, int $classId, int $subjectId): bool
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
        if (! $class || $class->school_id !== $user->school_id) {
            return false;
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

        return true;
    }

    private function ensureStudentBelongsToClass(int $studentId, int $classId): void
    {
        $matches = Student::query()->whereKey($studentId)->where('class_id', $classId)->exists();
        if (! $matches) {
            throw ValidationException::withMessages([
                'student_id' => ['Selected student is not assigned to the selected class.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function prepareScorePayloadForWrite(array $payload, ?Score $existingScore = null): array
    {
        $payload = $this->normalizeAssessmentPayload($payload, $existingScore);
        $payload = $this->deriveComputedTotals($payload, $existingScore);

        $subjectId = (int) ($payload['subject_id'] ?? $existingScore?->subject_id ?? 0);
        if ($subjectId > 0) {
            $this->assertWithinSubjectFullScore(
                $subjectId,
                (float) ($payload['exam_score'] ?? $existingScore?->exam_score ?? 0),
                (float) ($payload['total_score'] ?? $existingScore?->total_score ?? 0),
            );
        }

        if (! array_key_exists('grade', $payload) || $payload['grade'] === null || $payload['grade'] === '') {
            $effectiveTotal = (float) ($payload['total_score'] ?? $existingScore?->total_score ?? 0);
            $payload['grade'] = $this->resolveGrade($effectiveTotal);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function deriveComputedTotals(array $payload, ?Score $existingScore = null): array
    {
        $assessmentType = (string) ($payload['assessment_type'] ?? $existingScore?->assessment_type ?? 'monthly');
        $studentId = (int) ($payload['student_id'] ?? $existingScore?->student_id ?? 0);
        $classId = (int) ($payload['class_id'] ?? $existingScore?->class_id ?? 0);
        $subjectId = (int) ($payload['subject_id'] ?? $existingScore?->subject_id ?? 0);
        $semester = (int) ($payload['semester'] ?? $existingScore?->semester ?? 0);
        $academicYear = array_key_exists('academic_year', $payload)
            ? $payload['academic_year']
            : $existingScore?->academic_year;
        $excludeScoreId = $existingScore?->id;

        $effectiveExam = (float) ($payload['exam_score'] ?? $existingScore?->exam_score ?? 0);
        $fallbackTotal = (float) ($payload['total_score'] ?? $existingScore?->total_score ?? $effectiveExam);

        if ($studentId <= 0 || $classId <= 0 || $subjectId <= 0) {
            $payload['total_score'] = $fallbackTotal;

            return $payload;
        }

        if ($assessmentType === 'semester') {
            $monthlyTotals = $this->monthlyTotalsForSemester(
                $studentId,
                $classId,
                $subjectId,
                $academicYear,
                $semester,
                $excludeScoreId
            );

            if ($monthlyTotals !== []) {
                $components = array_merge($monthlyTotals, [$effectiveExam]);
                $payload['total_score'] = round(array_sum($components) / count($components), 2);

                return $payload;
            }

            $payload['total_score'] = $fallbackTotal;

            return $payload;
        }

        if ($assessmentType === 'yearly') {
            $yearlyComponents = $this->yearlyComponents(
                $studentId,
                $classId,
                $subjectId,
                $academicYear,
                $excludeScoreId
            );

            if ($yearlyComponents !== []) {
                $payload['total_score'] = round(array_sum($yearlyComponents) / count($yearlyComponents), 2);

                return $payload;
            }

            $payload['total_score'] = $fallbackTotal;

            return $payload;
        }

        if (! array_key_exists('total_score', $payload) || $payload['total_score'] === null) {
            $payload['total_score'] = $effectiveExam;
        }

        return $payload;
    }

    /**
     * @return array<int, float>
     */
    private function monthlyTotalsForSemester(
        int $studentId,
        int $classId,
        int $subjectId,
        mixed $academicYear,
        int $semester,
        ?int $excludeScoreId = null
    ): array {
        if (! in_array($semester, [1, 2], true)) {
            return [];
        }

        $months = $semester === 1 ? range(1, 6) : range(7, 12);

        $query = Score::query()
            ->where('student_id', $studentId)
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('assessment_type', 'monthly')
            ->whereIn('month', $months);

        if ($excludeScoreId !== null) {
            $query->whereKeyNot($excludeScoreId);
        }

        $this->applyAcademicYearFilter($query, $academicYear);

        return $query->pluck('total_score')
            ->map(fn ($value): float => (float) $value)
            ->values()
            ->all();
    }

    /**
     * @return array<int, float>
     */
    private function yearlyComponents(
        int $studentId,
        int $classId,
        int $subjectId,
        mixed $academicYear,
        ?int $excludeScoreId = null
    ): array {
        $monthlyQuery = Score::query()
            ->where('student_id', $studentId)
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('assessment_type', 'monthly');
        $semesterExamQuery = Score::query()
            ->where('student_id', $studentId)
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('assessment_type', 'semester');

        if ($excludeScoreId !== null) {
            $monthlyQuery->whereKeyNot($excludeScoreId);
            $semesterExamQuery->whereKeyNot($excludeScoreId);
        }

        $this->applyAcademicYearFilter($monthlyQuery, $academicYear);
        $this->applyAcademicYearFilter($semesterExamQuery, $academicYear);

        $monthlyTotals = $monthlyQuery->pluck('total_score')
            ->map(fn ($value): float => (float) $value)
            ->values()
            ->all();
        $semesterExamScores = $semesterExamQuery->pluck('exam_score')
            ->map(fn ($value): float => (float) $value)
            ->values()
            ->all();

        return array_merge($monthlyTotals, $semesterExamScores);
    }

    private function applyAcademicYearFilter(Builder $query, mixed $academicYear): void
    {
        $year = trim((string) ($academicYear ?? ''));
        if ($year === '') {
            $query->whereNull('academic_year');

            return;
        }

        $query->where('academic_year', $year);
    }

    private function assertWithinSubjectFullScore(int $subjectId, float $examScore, float $totalScore): void
    {
        $subject = Subject::query()->find($subjectId);
        $maxScore = (float) ($subject?->full_score ?? 100.0);

        if ($examScore > $maxScore) {
            throw ValidationException::withMessages([
                'exam_score' => ["Exam score cannot exceed subject full score ({$maxScore})."],
            ]);
        }

        if ($totalScore > $maxScore) {
            throw ValidationException::withMessages([
                'total_score' => ["Total score cannot exceed subject full score ({$maxScore})."],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function normalizeAssessmentPayload(array $payload, ?Score $existingScore = null): array
    {
        $assessmentType = (string) ($payload['assessment_type'] ?? $existingScore?->assessment_type ?? '');
        if (! in_array($assessmentType, ['monthly', 'semester', 'yearly'], true)) {
            if (array_key_exists('semester', $payload) && $payload['semester'] !== null) {
                $assessmentType = 'semester';
            } elseif (array_key_exists('academic_year', $payload) && ! array_key_exists('month', $payload)) {
                $assessmentType = 'yearly';
            } else {
                $assessmentType = 'monthly';
            }
        }

        $payload['assessment_type'] = $assessmentType;

        if (array_key_exists('academic_year', $payload)) {
            $academicYear = trim((string) ($payload['academic_year'] ?? ''));
            $payload['academic_year'] = $academicYear !== '' ? $academicYear : null;
        }

        if ($assessmentType === 'monthly') {
            $payload['semester'] = null;
        } elseif ($assessmentType === 'semester') {
            $payload['month'] = null;
        } elseif ($assessmentType === 'yearly') {
            $payload['month'] = null;
            $payload['semester'] = null;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function scoreBucket(array $fields): array
    {
        return [
            'class_id' => $fields['class_id'] ?? null,
            'subject_id' => $fields['subject_id'] ?? null,
            'assessment_type' => $fields['assessment_type'] ?? 'monthly',
            'academic_year' => $fields['academic_year'] ?? null,
            'month' => $fields['month'] ?? null,
            'semester' => $fields['semester'] ?? null,
            'quarter' => $fields['quarter'] ?? null,
            'period' => $fields['period'] ?? null,
        ];
    }

    private function recomputeRankForBucket(Score $score): void
    {
        $this->recomputeRankForBucketValues($this->scoreBucket([
            'class_id' => $score->class_id,
            'subject_id' => $score->subject_id,
            'assessment_type' => $score->assessment_type,
            'academic_year' => $score->academic_year,
            'month' => $score->month,
            'semester' => $score->semester,
            'quarter' => $score->quarter,
            'period' => $score->period,
        ]));
    }

    /**
     * @param  array<string, mixed>  $bucket
     */
    private function recomputeRankForBucketValues(array $bucket): void
    {
        if (empty($bucket['class_id']) || empty($bucket['subject_id'])) {
            return;
        }

        $query = Score::query()
            ->where('class_id', $bucket['class_id'])
            ->where('subject_id', $bucket['subject_id']);

        foreach (['assessment_type', 'academic_year', 'month', 'semester', 'quarter', 'period'] as $field) {
            $value = $bucket[$field] ?? null;
            if ($value === null || $value === '') {
                $query->whereNull($field);
            } else {
                $query->where($field, $value);
            }
        }

        $rows = $query
            ->orderByDesc('total_score')
            ->orderBy('id')
            ->get(['id', 'total_score']);

        $rank = 0;
        $position = 0;
        $lastScore = null;

        foreach ($rows as $row) {
            $position++;
            $currentScore = (float) $row->total_score;
            if ($lastScore === null || $currentScore < $lastScore) {
                $rank = $position;
                $lastScore = $currentScore;
            }

            Score::query()->whereKey($row->id)->update(['rank_in_class' => $rank]);
        }
    }

    private function resolveGrade(float $totalScore): string
    {
        return match (true) {
            $totalScore >= 90 => 'A',
            $totalScore >= 80 => 'B',
            $totalScore >= 70 => 'C',
            $totalScore >= 60 => 'D',
            $totalScore >= 50 => 'E',
            default => 'F',
        };
    }
}
