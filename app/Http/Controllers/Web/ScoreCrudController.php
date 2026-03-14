<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithInternalApi;
use App\Services\InternalApiClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ScoreCrudController extends Controller
{
    use InteractsWithInternalApi;

    public function index(Request $request, InternalApiClient $api): View|RedirectResponse
    {
        $filters = $request->validate([
            'class_id' => ['nullable', 'integer'],
            'student_id' => ['nullable', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'assessment_type' => ['nullable', 'in:monthly,semester,yearly'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'semester' => ['nullable', 'integer', 'between:1,2'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'period' => ['nullable', 'string', 'max:50'],
            'rank_in_class' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $result = $api->get($request, '/api/scores', array_filter($filters, fn ($v) => $v !== null && $v !== ''));

        if ($result['status'] !== 200) {
            return redirect()->away(route('dashboard', [], false))->withErrors($this->extractErrors($result));
        }

        $payload = $result['data'] ?? [];

        return view('web.crud.scores.index', [
            'items' => $payload['data'] ?? [],
            'meta' => $payload,
            'filters' => $filters,
        ] + $this->loadAcademicSelectOptions($request, $api, true, true, true));
    }

    public function create(Request $request, InternalApiClient $api): View
    {
        $filters = $request->validate([
            'class_id' => ['nullable', 'integer'],
            'assessment_type' => ['nullable', 'in:monthly,semester,yearly'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'semester' => ['nullable', 'integer', 'between:1,2'],
            'academic_year' => ['nullable', 'string', 'max:20'],
        ]);

        $selectedClassId = (int) ($filters['class_id'] ?? 0);
        $selectedType = (string) ($filters['assessment_type'] ?? 'monthly');
        $selectedMonth = isset($filters['month']) ? (int) $filters['month'] : null;
        $selectedSemester = isset($filters['semester']) ? (int) $filters['semester'] : null;
        $selectedAcademicYear = trim((string) ($filters['academic_year'] ?? now()->format('Y').'-'.now()->addYear()->format('Y')));

        $teacherMap = $this->teacherClassSubjectMap($request);
        $students = [];
        $subjects = [];
        $scoreMatrix = [];
        $canInput = false;

        if ($selectedClassId > 0) {
            $classResult = $api->get($request, '/api/classes/'.$selectedClassId);
            if (($classResult['status'] ?? 0) === 200) {
                $classPayload = $classResult['data']['data'] ?? [];
                $classSchoolId = (int) ($classPayload['school_id'] ?? data_get($classPayload, 'school.id', 0));
                $classSubjectIds = collect($classPayload['subjects'] ?? [])
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->filter(fn (int $id): bool => $id > 0)
                    ->values()
                    ->all();

                $allowedSubjectIds = $classSubjectIds;
                if ($request->user()->role === 'teacher') {
                    $allowedSubjectIds = collect($teacherMap[$selectedClassId] ?? [])
                        ->map(fn ($id): int => (int) $id)
                        ->filter(fn (int $id): bool => $id > 0)
                        ->values()
                        ->all();
                }

                $subjects = collect($classPayload['subjects'] ?? [])
                    ->filter(fn ($subject) => in_array((int) ($subject['id'] ?? 0), $allowedSubjectIds, true))
                    ->map(fn ($subject): array => [
                        'id' => (int) ($subject['id'] ?? 0),
                        'name' => (string) ($subject['name'] ?? 'Subject'),
                    ])
                    ->filter(fn (array $subject): bool => $subject['id'] > 0)
                    ->values()
                    ->all();

                // Admin/super-admin can input scores for any subject in their school.
                // If class subject assignments are empty, fallback to school subject list.
                if ($request->user()->role !== 'teacher' && $subjects === []) {
                    $subjectFilters = ['per_page' => 100];
                    if ($request->user()->role === 'super-admin' && $classSchoolId > 0) {
                        $subjectFilters['school_id'] = $classSchoolId;
                    }

                    $subjectResult = $api->get($request, '/api/subjects', $subjectFilters);
                    if (($subjectResult['status'] ?? 0) === 200) {
                        $subjects = collect($subjectResult['data']['data'] ?? [])
                            ->filter(fn ($subject): bool => is_array($subject))
                            ->map(fn (array $subject): array => [
                                'id' => (int) ($subject['id'] ?? 0),
                                'name' => (string) ($subject['name'] ?? 'Subject'),
                            ])
                            ->filter(fn (array $subject): bool => $subject['id'] > 0)
                            ->values()
                            ->all();
                    }
                }

                $students = collect($classPayload['students'] ?? [])
                    ->map(fn ($student): array => [
                        'id' => (int) ($student['id'] ?? 0),
                        'name' => (string) ($student['user']['name'] ?? ('Student '.($student['id'] ?? ''))),
                    ])
                    ->filter(fn (array $student): bool => $student['id'] > 0)
                    ->values()
                    ->all();

                $canInput = $subjects !== []
                    && $students !== []
                    && $selectedAcademicYear !== ''
                    && (
                        ($selectedType === 'monthly' && $selectedMonth !== null)
                        || ($selectedType === 'semester' && $selectedSemester !== null)
                        || $selectedType === 'yearly'
                    );

                if ($canInput) {
                    $scoreFilters = [
                        'class_id' => $selectedClassId,
                        'assessment_type' => $selectedType,
                        'academic_year' => $selectedAcademicYear,
                        'per_page' => 100,
                    ];
                    if ($selectedType === 'monthly') {
                        $scoreFilters['month'] = $selectedMonth;
                    }
                    if ($selectedType === 'semester') {
                        $scoreFilters['semester'] = $selectedSemester;
                    }

                    $scoresResult = $api->get($request, '/api/scores', $scoreFilters);
                    if (($scoresResult['status'] ?? 0) === 200) {
                        foreach (($scoresResult['data']['data'] ?? []) as $row) {
                            if (! is_array($row)) {
                                continue;
                            }
                            $studentId = (int) ($row['student_id'] ?? 0);
                            $subjectId = (int) ($row['subject_id'] ?? 0);
                            if ($studentId > 0 && $subjectId > 0) {
                                $scoreMatrix[$studentId][$subjectId] = (string) ($row['total_score'] ?? '');
                            }
                        }
                    }
                }
            }
        }

        return view('web.crud.scores.bulk_form', [
            'filters' => [
                'class_id' => $selectedClassId > 0 ? $selectedClassId : '',
                'assessment_type' => $selectedType,
                'month' => $selectedMonth,
                'semester' => $selectedSemester,
                'academic_year' => $selectedAcademicYear,
            ],
            'students' => $students,
            'subjects' => $subjects,
            'scoreMatrix' => $scoreMatrix,
            'canInput' => $canInput,
        ] + $this->loadAcademicSelectOptions($request, $api, true, false, false));
    }

    public function store(Request $request, InternalApiClient $api): RedirectResponse
    {
        if ($request->boolean('bulk_mode')) {
            return $this->storeBulkScores($request, $api);
        }

        $payload = $this->validatePayload($request);
        $result = $api->post($request, '/api/scores', $payload);

        if ($result['status'] !== 201) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.scores.index', [], false))->with('success', 'Score created successfully.');
    }

    public function edit(Request $request, int $score, InternalApiClient $api): View|RedirectResponse
    {
        $result = $api->get($request, '/api/scores/'.$score);

        if ($result['status'] !== 200) {
            return redirect()->away(route('panel.scores.index', [], false))->withErrors($this->extractErrors($result));
        }

        return view('web.crud.scores.form', [
            'mode' => 'edit',
            'item' => $result['data']['data'] ?? null,
            'userRole' => $request->user()->role,
            'teacherClassSubjectMap' => $this->teacherClassSubjectMap($request),
        ] + $this->loadAcademicSelectOptions($request, $api, true, true, true));
    }

    public function update(Request $request, int $score, InternalApiClient $api): RedirectResponse
    {
        $payload = $this->validatePayload($request);
        $result = $api->put($request, '/api/scores/'.$score, $payload);

        if ($result['status'] !== 200) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.scores.index', [], false))->with('success', 'Score updated successfully.');
    }

    public function destroy(Request $request, int $score, InternalApiClient $api): RedirectResponse
    {
        $result = $api->delete($request, '/api/scores/'.$score);

        if ($result['status'] !== 200) {
            return back()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.scores.index', [], false))->with('success', 'Score deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'student_id' => ['required', 'integer'],
            'subject_id' => ['required', 'integer'],
            'class_id' => ['required', 'integer'],
            'exam_score' => ['required', 'numeric', 'min:0', 'max:1000'],
            'total_score' => ['required', 'numeric', 'min:0', 'max:1000'],
            'assessment_type' => ['nullable', 'in:monthly,semester,yearly'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'semester' => ['nullable', 'integer', 'between:1,2'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'period' => ['nullable', 'string', 'max:50'],
            'grade' => ['nullable', 'string', 'max:5'],
            'rank_in_class' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    /**
     * @return array<int, array<int, int>>
     */
    private function teacherClassSubjectMap(Request $request): array
    {
        if ($request->user()->role !== 'teacher') {
            return [];
        }

        return DB::table('teacher_class')
            ->where('teacher_id', $request->user()->id)
            ->orderBy('class_id')
            ->get(['class_id', 'subject_id'])
            ->groupBy('class_id')
            ->map(fn ($rows) => $rows
                ->pluck('subject_id')
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all())
            ->mapWithKeys(fn (array $subjectIds, $classId): array => [(int) $classId => $subjectIds])
            ->all();
    }

    private function storeBulkScores(Request $request, InternalApiClient $api): RedirectResponse
    {
        $payload = $request->validate([
            'bulk_mode' => ['required', 'accepted'],
            'class_id' => ['required', 'integer'],
            'assessment_type' => ['required', 'in:monthly,semester,yearly'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'semester' => ['nullable', 'integer', 'between:1,2'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'bulk_marks' => ['required', 'array'],
            'bulk_marks.*' => ['array'],
            'bulk_marks.*.*' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);

        if ($payload['assessment_type'] === 'monthly' && ! isset($payload['month'])) {
            return back()->withInput()->withErrors(['month' => ['Please select month first.']]);
        }
        if ($payload['assessment_type'] === 'semester' && ! isset($payload['semester'])) {
            return back()->withInput()->withErrors(['semester' => ['Please select semester first.']]);
        }

        $classId = (int) $payload['class_id'];
        $assessmentType = (string) $payload['assessment_type'];
        $academicYear = (string) ($payload['academic_year'] ?? now()->format('Y').'-'.now()->addYear()->format('Y'));

        $classResult = $api->get($request, '/api/classes/'.$classId);
        if (($classResult['status'] ?? 0) !== 200) {
            return back()->withInput()->withErrors($this->extractErrors($classResult));
        }

        $classPayload = $classResult['data']['data'] ?? [];
        $classSchoolId = (int) ($classPayload['school_id'] ?? data_get($classPayload, 'school.id', 0));
        $validStudentIds = collect($classPayload['students'] ?? [])
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();
        $classSubjectIds = collect($classPayload['subjects'] ?? [])
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $allowedSubjectIds = $classSubjectIds;
        if ($request->user()->role === 'teacher') {
            $teacherMap = $this->teacherClassSubjectMap($request);
            $allowedSubjectIds = collect($teacherMap[$classId] ?? [])
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->values()
                ->all();
        } elseif ($allowedSubjectIds === []) {
            $subjectFilters = ['per_page' => 100];
            if ($request->user()->role === 'super-admin' && $classSchoolId > 0) {
                $subjectFilters['school_id'] = $classSchoolId;
            }

            $subjectResult = $api->get($request, '/api/subjects', $subjectFilters);
            if (($subjectResult['status'] ?? 0) === 200) {
                $allowedSubjectIds = collect($subjectResult['data']['data'] ?? [])
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->filter(fn (int $id): bool => $id > 0)
                    ->values()
                    ->all();
            }
        }

        if ($allowedSubjectIds === []) {
            return back()->withInput()->withErrors([
                'class_id' => ['No allowed subjects found for this class.'],
            ]);
        }

        $rows = [];
        foreach ((array) ($payload['bulk_marks'] ?? []) as $studentIdRaw => $subjectMarks) {
            $studentId = (int) $studentIdRaw;
            if (! in_array($studentId, $validStudentIds, true) || ! is_array($subjectMarks)) {
                continue;
            }

            foreach ($subjectMarks as $subjectIdRaw => $scoreValue) {
                if ($scoreValue === null || $scoreValue === '') {
                    continue;
                }

                $subjectId = (int) $subjectIdRaw;
                if (! in_array($subjectId, $allowedSubjectIds, true)) {
                    continue;
                }

                $score = (float) $scoreValue;
                $rows[] = [
                    'student_id' => $studentId,
                    'class_id' => $classId,
                    'subject_id' => $subjectId,
                    'exam_score' => $score,
                    'total_score' => $score,
                    'assessment_type' => $assessmentType,
                    'month' => $assessmentType === 'monthly' ? (int) ($payload['month'] ?? 0) : null,
                    'semester' => $assessmentType === 'semester' ? (int) ($payload['semester'] ?? 0) : null,
                    'academic_year' => $academicYear,
                    'period' => 'Bulk Table',
                    'quarter' => null,
                ];
            }
        }

        if ($rows === []) {
            return back()->withInput()->withErrors([
                'bulk_marks' => ['Please input at least one score in the table.'],
            ]);
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'scores_bulk_');
        if ($tmpPath === false) {
            return back()->withInput()->withErrors([
                'file' => ['Unable to prepare bulk score file.'],
            ]);
        }

        $handle = fopen($tmpPath, 'wb');
        if ($handle === false) {
            @unlink($tmpPath);

            return back()->withInput()->withErrors([
                'file' => ['Unable to write bulk score file.'],
            ]);
        }

        fputcsv($handle, [
            'student_id',
            'class_id',
            'subject_id',
            'exam_score',
            'total_score',
            'assessment_type',
            'month',
            'semester',
            'academic_year',
            'quarter',
            'period',
        ]);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['student_id'],
                $row['class_id'],
                $row['subject_id'],
                $row['exam_score'],
                $row['total_score'],
                $row['assessment_type'],
                $row['month'],
                $row['semester'],
                $row['academic_year'],
                $row['quarter'],
                $row['period'],
            ]);
        }

        fclose($handle);

        $upload = new UploadedFile($tmpPath, 'scores_bulk.csv', 'text/csv', null, true);
        $result = $api->post($request, '/api/scores/import/csv', ['file' => $upload]);
        @unlink($tmpPath);

        if (($result['status'] ?? 0) !== 201) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        $data = $result['data']['data'] ?? [];
        $created = (int) ($data['created'] ?? 0);
        $updated = (int) ($data['updated'] ?? 0);
        $errorCount = count((array) ($data['errors'] ?? []));

        return redirect()->away(route('panel.scores.create', [
            'class_id' => $classId,
            'assessment_type' => $assessmentType,
            'month' => $assessmentType === 'monthly' ? (int) ($payload['month'] ?? 0) : null,
            'semester' => $assessmentType === 'semester' ? (int) ($payload['semester'] ?? 0) : null,
            'academic_year' => $academicYear,
        ], false))->with('success', "Bulk scores saved. Created: {$created}, Updated: {$updated}, Errors: {$errorCount}");
    }
}
