<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithInternalApi;
use App\Models\Student;
use App\Services\InternalApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClassCrudController extends Controller
{
    use InteractsWithInternalApi;

    private const GRADE_OPTIONS = [
        '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12',
    ];

    private const WEEK_DAYS = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    public function index(Request $request, InternalApiClient $api): View|RedirectResponse
    {
        $filters = $request->validate([
            'school_id' => ['nullable', 'integer'],
            'name' => ['nullable', 'string', 'max:50'],
            'grade_level' => ['nullable', 'string', 'max:50'],
            'room' => ['nullable', 'string', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $result = $api->get($request, '/api/classes', array_filter($filters, fn ($v) => $v !== null && $v !== ''));

        if (($result['status'] ?? 0) !== 200) {
            return redirect()->away(route('dashboard', [], false))->withErrors($this->extractErrors($result));
        }

        $payload = $result['data'] ?? [];
        $items = $this->hydrateClassListRows($request, $api, $payload['data'] ?? []);

        return view('web.crud.classes.index', [
            'items' => $items,
            'meta' => $payload,
            'filters' => $filters,
            'userRole' => $request->user()->role,
        ]);
    }

    public function create(Request $request, InternalApiClient $api): View
    {
        return view('web.crud.classes.form', [
            'mode' => 'create',
            'item' => null,
            'userRole' => $request->user()->role,
        ] + $this->prepareFormOptions($request, $api));
    }

    public function store(Request $request, InternalApiClient $api): RedirectResponse
    {
        $payload = $this->validatePayload($request);
        $result = $api->post($request, '/api/classes', $this->extractClassPayload($payload));

        if (($result['status'] ?? 0) !== 201) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        $classItem = $result['data']['data'] ?? null;
        if (! is_array($classItem)) {
            return redirect()->away(route('panel.classes.index', [], false))->withErrors([
                'class' => ['Class was created but detail payload is missing.'],
            ]);
        }

        $classId = (int) ($classItem['id'] ?? 0);
        if ($classId <= 0) {
            return redirect()->away(route('panel.classes.index', [], false))->withErrors([
                'class' => ['Invalid class identifier returned from API.'],
            ]);
        }

        $schoolId = (int) ($classItem['school_id'] ?? ($this->resolveScopedSchoolId($request, $classItem) ?? 0));
        $setupErrors = $this->synchronizeClassSetup($request, $api, $classId, $schoolId, $payload);

        if ($setupErrors !== []) {
            return redirect()
                ->away(route('panel.classes.show', $classId, false))
                ->withErrors($setupErrors)
                ->with('success', 'Class created. Please review setup warnings below.');
        }

        return redirect()
            ->away(route('panel.classes.show', $classId, false))
            ->with('success', 'Class created and setup saved successfully.');
    }

    public function show(Request $request, int $schoolClass, InternalApiClient $api): View|RedirectResponse
    {
        $classResult = $api->get($request, '/api/classes/'.$schoolClass);

        if (($classResult['status'] ?? 0) !== 200) {
            return redirect()->away(route('panel.classes.index', [], false))->withErrors($this->extractErrors($classResult));
        }

        $item = $classResult['data']['data'] ?? null;
        if (! is_array($item)) {
            return redirect()->away(route('panel.classes.index', [], false))->withErrors([
                'class' => ['Class data could not be loaded.'],
            ]);
        }

        $students = $this->fetchStudentsForClassScope($request, $api, $item);
        $selectedIds = old('student_ids', collect($item['students'] ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all());

        return view('web.crud.classes.show', [
            'item' => $item,
            'students' => $students,
            'selectedIds' => collect($selectedIds)->map(fn ($id) => (int) $id)->filter()->values()->all(),
            'userRole' => $request->user()->role,
        ]);
    }

    public function syncStudents(Request $request, int $schoolClass, InternalApiClient $api): RedirectResponse
    {
        $payload = $request->validate([
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer'],
        ]);

        $classResult = $api->get($request, '/api/classes/'.$schoolClass);
        if (($classResult['status'] ?? 0) !== 200) {
            return redirect()->away(route('panel.classes.index', [], false))->withErrors($this->extractErrors($classResult));
        }

        $classItem = $classResult['data']['data'] ?? null;
        if (! is_array($classItem)) {
            return redirect()->away(route('panel.classes.index', [], false))->withErrors([
                'class' => ['Class data could not be loaded.'],
            ]);
        }

        $schoolId = (int) ($classItem['school_id'] ?? data_get($classItem, 'school.id', 0));
        $studentErrors = $this->synchronizeStudentsForClass(
            $request,
            $api,
            $schoolClass,
            $schoolId,
            collect($payload['student_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all()
        );

        if ($studentErrors !== []) {
            return back()->withInput()->withErrors($studentErrors);
        }

        return redirect()
            ->away(route('panel.classes.show', $schoolClass, false))
            ->with('success', 'Class student assignment updated successfully.');
    }

    public function edit(Request $request, int $schoolClass, InternalApiClient $api): View|RedirectResponse
    {
        $result = $api->get($request, '/api/classes/'.$schoolClass);

        if (($result['status'] ?? 0) !== 200) {
            return redirect()->away(route('panel.classes.index', [], false))->withErrors($this->extractErrors($result));
        }

        $item = $result['data']['data'] ?? null;
        if (! is_array($item)) {
            return redirect()->away(route('panel.classes.index', [], false))->withErrors([
                'class' => ['Class data could not be loaded.'],
            ]);
        }

        return view('web.crud.classes.form', [
            'mode' => 'edit',
            'item' => $item,
            'userRole' => $request->user()->role,
        ] + $this->prepareFormOptions($request, $api, $item));
    }

    public function update(Request $request, int $schoolClass, InternalApiClient $api): RedirectResponse
    {
        $payload = $this->validatePayload($request);
        $result = $api->put($request, '/api/classes/'.$schoolClass, $this->extractClassPayload($payload));

        if (($result['status'] ?? 0) !== 200) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        $classItem = $result['data']['data'] ?? null;
        $schoolId = is_array($classItem)
            ? (int) ($classItem['school_id'] ?? 0)
            : ($this->resolveScopedSchoolId($request) ?? 0);

        $setupErrors = $this->synchronizeClassSetup($request, $api, $schoolClass, $schoolId, $payload);
        if ($setupErrors !== []) {
            return redirect()
                ->away(route('panel.classes.show', $schoolClass, false))
                ->withErrors($setupErrors)
                ->with('success', 'Class updated. Please review setup warnings below.');
        }

        return redirect()
            ->away(route('panel.classes.show', $schoolClass, false))
            ->with('success', 'Class updated successfully.');
    }

    public function destroy(Request $request, int $schoolClass, InternalApiClient $api): RedirectResponse
    {
        $result = $api->delete($request, '/api/classes/'.$schoolClass);

        if (($result['status'] ?? 0) !== 200) {
            return back()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.classes.index', [], false))->with('success', 'Class deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        $payload = $request->validate([
            'school_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:50'],
            'grade_level' => ['nullable', 'string', 'max:50'],
            'room' => ['nullable', 'string', 'max:50'],
            'study_days' => ['nullable', 'array', 'min:1'],
            'study_days.*' => ['string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday', 'distinct'],
            'study_time_start' => ['nullable', 'date_format:H:i'],
            'study_time_end' => ['nullable', 'date_format:H:i'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer'],
            'teacher_assignments' => ['nullable', 'array'],
            'teacher_assignments.*.teacher_id' => ['nullable', 'integer'],
            'teacher_assignments.*.subject_id' => ['nullable', 'integer'],
            'timetable_rows' => ['nullable', 'array'],
            'timetable_rows.*.day_of_week' => ['nullable', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'timetable_rows.*.subject_id' => ['nullable', 'integer'],
            'timetable_rows.*.teacher_id' => ['nullable', 'integer'],
            'timetable_rows.*.time_start' => ['nullable', 'date_format:H:i'],
            'timetable_rows.*.time_end' => ['nullable', 'date_format:H:i'],
        ]);

        if ($request->user()->role !== 'super-admin') {
            unset($payload['school_id']);
        }

        if (array_key_exists('study_days', $payload) && is_array($payload['study_days'])) {
            $payload['study_days'] = collect($payload['study_days'])
                ->map(fn ($day): string => strtolower(trim((string) $day)))
                ->filter(fn (string $day): bool => $day !== '')
                ->unique()
                ->values()
                ->all();
        }

        $hasStudyStart = ! empty($payload['study_time_start'] ?? null);
        $hasStudyEnd = ! empty($payload['study_time_end'] ?? null);
        if ($hasStudyStart xor $hasStudyEnd) {
            throw ValidationException::withMessages([
                'study_time_end' => ['Study start and end time must both be set together.'],
            ]);
        }
        if ($hasStudyStart && $hasStudyEnd && (string) $payload['study_time_end'] <= (string) $payload['study_time_start']) {
            throw ValidationException::withMessages([
                'study_time_end' => ['Study end time must be after study start time.'],
            ]);
        }

        $this->normalizeTeacherAssignments($payload['teacher_assignments'] ?? []);
        $this->normalizeTimetableRows($payload['timetable_rows'] ?? []);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function extractClassPayload(array $payload): array
    {
        return collect($payload)
            ->only([
                'school_id',
                'name',
                'grade_level',
                'room',
                'study_days',
                'study_time_start',
                'study_time_end',
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $classItem
     * @return array<string, mixed>
     */
    private function prepareFormOptions(Request $request, InternalApiClient $api, ?array $classItem = null): array
    {
        $selectedSchoolId = $this->resolveScopedSchoolId($request, $classItem);

        return [
            'schoolOptions' => $request->user()->role === 'super-admin'
                ? $this->loadSchoolSelectOptions($request, $api)
                : [],
            'selectedSchoolId' => $selectedSchoolId,
            'gradeOptions' => self::GRADE_OPTIONS,
            'weekdayOptions' => self::WEEK_DAYS,
            'teacherOptions' => $this->fetchTeacherOptions($request, $api, $selectedSchoolId),
            'subjectOptions' => $this->fetchSubjectOptions($request, $api, $selectedSchoolId),
            'studentOptions' => $this->fetchStudentsForSchoolScope($request, $api, $selectedSchoolId),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $classItem
     */
    private function resolveScopedSchoolId(Request $request, ?array $classItem = null): ?int
    {
        if ($request->user()->role !== 'super-admin') {
            $schoolId = (int) ($request->user()->school_id ?? 0);

            return $schoolId > 0 ? $schoolId : null;
        }

        $oldSchoolId = old('school_id');
        if ($oldSchoolId !== null && $oldSchoolId !== '') {
            $candidate = (int) $oldSchoolId;

            return $candidate > 0 ? $candidate : null;
        }

        if (is_array($classItem)) {
            $candidate = (int) ($classItem['school_id'] ?? data_get($classItem, 'school.id', 0));
            if ($candidate > 0) {
                return $candidate;
            }
        }

        $candidate = (int) $request->input('school_id', 0);

        return $candidate > 0 ? $candidate : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchTeacherOptions(Request $request, InternalApiClient $api, ?int $schoolId): array
    {
        $filters = [
            'role' => 'teacher',
            'per_page' => 100,
        ];

        if ($request->user()->role === 'super-admin' && $schoolId) {
            $filters['school_id'] = $schoolId;
        }

        $items = $this->fetchPaginatedItems($request, $api, '/api/users', $filters);

        return collect($items)
            ->filter(fn ($item): bool => is_array($item))
            ->map(function (array $item): array {
                $id = (int) ($item['id'] ?? 0);
                $name = trim((string) ($item['name'] ?? 'Teacher'));
                $email = trim((string) ($item['email'] ?? ''));

                return [
                    'id' => $id,
                    'name' => $name,
                    'email' => $email,
                    'school_id' => (int) ($item['school_id'] ?? data_get($item, 'school.id', 0)),
                    'label' => $name.($email !== '' ? ' - '.$email : ''),
                ];
            })
            ->filter(fn (array $teacher): bool => $teacher['id'] > 0)
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchSubjectOptions(Request $request, InternalApiClient $api, ?int $schoolId): array
    {
        $filters = ['per_page' => 100];
        if ($request->user()->role === 'super-admin' && $schoolId) {
            $filters['school_id'] = $schoolId;
        }

        $items = $this->fetchPaginatedItems($request, $api, '/api/subjects', $filters);

        return collect($items)
            ->filter(fn ($item): bool => is_array($item))
            ->map(function (array $item): array {
                $id = (int) ($item['id'] ?? 0);
                $name = trim((string) ($item['name'] ?? 'Subject'));

                return [
                    'id' => $id,
                    'name' => $name,
                    'school_id' => (int) ($item['school_id'] ?? data_get($item, 'school.id', 0)),
                    'label' => $name !== '' ? $name : 'Subject '.$id,
                ];
            })
            ->filter(fn (array $subject): bool => $subject['id'] > 0)
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchStudentsForSchoolScope(Request $request, InternalApiClient $api, ?int $schoolId): array
    {
        $filters = ['per_page' => 100];
        if ($request->user()->role === 'super-admin' && $schoolId) {
            $filters['school_id'] = $schoolId;
        }

        return collect($this->fetchPaginatedItems($request, $api, '/api/students', $filters))
            ->filter(fn ($item): bool => is_array($item))
            ->map(function (array $student): array {
                return [
                    'id' => (int) ($student['id'] ?? 0),
                    'name' => (string) data_get($student, 'user.name', 'Student'),
                    'khmer_name' => (string) data_get($student, 'user.khmer_name', ''),
                    'student_code' => (string) ($student['student_code'] ?? ''),
                    'email' => (string) data_get($student, 'user.email', ''),
                    'phone' => (string) data_get($student, 'user.phone', ''),
                    'class_id' => isset($student['class_id']) ? (int) $student['class_id'] : null,
                    'class_name' => (string) data_get($student, 'class.name', ''),
                    'grade' => (string) ($student['grade'] ?? ''),
                    'school_id' => (int) data_get($student, 'user.school_id', 0),
                ];
            })
            ->filter(fn (array $student): bool => $student['id'] > 0)
            ->sortBy([
                ['class_id', 'asc'],
                ['name', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $classItem
     * @return array<int, array<string, mixed>>
     */
    private function fetchStudentsForClassScope(Request $request, InternalApiClient $api, array $classItem): array
    {
        $schoolId = (int) ($classItem['school_id'] ?? data_get($classItem, 'school.id', 0));

        return $this->fetchStudentsForSchoolScope(
            $request,
            $api,
            $schoolId > 0 ? $schoolId : null
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array<int, string>>
     */
    private function synchronizeClassSetup(
        Request $request,
        InternalApiClient $api,
        int $classId,
        int $schoolId,
        array $payload
    ): array {
        $errors = [];

        $studentIds = collect($payload['student_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $studentErrors = $this->synchronizeStudentsForClass($request, $api, $classId, $schoolId, $studentIds);
        if ($studentErrors !== []) {
            $errors = array_merge($errors, $studentErrors);
        }

        $assignmentErrors = $this->synchronizeTeacherAssignmentsForClass(
            $request,
            $api,
            $classId,
            $this->normalizeTeacherAssignments($payload['teacher_assignments'] ?? [])
        );
        if ($assignmentErrors !== []) {
            $errors = array_merge($errors, $assignmentErrors);
        }

        $timetableErrors = $this->createTimetableRowsForClass(
            $request,
            $api,
            $classId,
            $this->normalizeTimetableRows($payload['timetable_rows'] ?? [])
        );
        if ($timetableErrors !== []) {
            $errors = array_merge($errors, $timetableErrors);
        }

        return $errors;
    }

    /**
     * @param  array<int, int>  $selectedIds
     * @return array<string, array<int, string>>
     */
    private function synchronizeStudentsForClass(
        Request $request,
        InternalApiClient $api,
        int $classId,
        int $schoolId,
        array $selectedIds
    ): array {
        $classResult = $api->get($request, '/api/classes/'.$classId);
        if (($classResult['status'] ?? 0) !== 200) {
            return $this->extractErrors($classResult);
        }

        $classItem = $classResult['data']['data'] ?? null;
        if (! is_array($classItem)) {
            return ['class_id' => ['Class detail could not be loaded for student sync.']];
        }

        $selected = collect($selectedIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $allowedIds = Student::query()
            ->whereHas('user', fn ($query) => $query->where('school_id', $schoolId))
            ->pluck('id');

        $invalidIds = $selected->diff($allowedIds);
        if ($invalidIds->isNotEmpty()) {
            return ['student_ids' => ['Some selected students do not belong to this school.']];
        }

        $currentAssignedIds = collect($classItem['students'] ?? [])
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $assignIds = $selected->diff($currentAssignedIds)->values();
        $removeIds = $currentAssignedIds->diff($selected)->values();

        foreach ($assignIds as $studentId) {
            $result = $api->put($request, '/api/students/'.$studentId, $this->studentSyncPayload($request, $schoolId, $classId));
            if (($result['status'] ?? 0) !== 200) {
                return $this->extractErrors($result);
            }
        }

        foreach ($removeIds as $studentId) {
            $result = $api->put($request, '/api/students/'.$studentId, $this->studentSyncPayload($request, $schoolId, null));
            if (($result['status'] ?? 0) !== 200) {
                return $this->extractErrors($result);
            }
        }

        return [];
    }

    /**
     * @param  array<int, array{teacher_id:int,subject_id:int}>  $assignments
     * @return array<string, array<int, string>>
     */
    private function synchronizeTeacherAssignmentsForClass(
        Request $request,
        InternalApiClient $api,
        int $classId,
        array $assignments
    ): array {
        $result = $api->put($request, '/api/classes/'.$classId.'/teacher-assignments', [
            'assignments' => $assignments,
        ]);

        if (($result['status'] ?? 0) !== 200) {
            return $this->extractErrors($result);
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array<int, string>>
     */
    private function createTimetableRowsForClass(
        Request $request,
        InternalApiClient $api,
        int $classId,
        array $rows
    ): array {
        foreach ($rows as $row) {
            $result = $api->post($request, '/api/timetables', [
                'class_id' => $classId,
                'subject_id' => (int) $row['subject_id'],
                'teacher_id' => (int) $row['teacher_id'],
                'day_of_week' => (string) $row['day_of_week'],
                'time_start' => (string) $row['time_start'],
                'time_end' => (string) $row['time_end'],
            ]);

            if (($result['status'] ?? 0) !== 201) {
                return $this->extractErrors($result);
            }
        }

        return [];
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array{teacher_id:int,subject_id:int}>
     */
    private function normalizeTeacherAssignments(array $rows): array
    {
        $normalized = collect($rows)
            ->filter(fn ($row): bool => is_array($row))
            ->map(function (array $row, int $index): array {
                $teacherId = (int) ($row['teacher_id'] ?? 0);
                $subjectId = (int) ($row['subject_id'] ?? 0);

                if (($teacherId > 0 && $subjectId <= 0) || ($teacherId <= 0 && $subjectId > 0)) {
                    throw ValidationException::withMessages([
                        'teacher_assignments.'.$index => ['Each teacher assignment row needs both teacher and subject.'],
                    ]);
                }

                return [
                    'teacher_id' => $teacherId,
                    'subject_id' => $subjectId,
                ];
            })
            ->filter(fn (array $row): bool => $row['teacher_id'] > 0 && $row['subject_id'] > 0)
            ->unique(fn (array $row): string => $row['teacher_id'].'-'.$row['subject_id'])
            ->values();

        return $normalized->all();
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array{day_of_week:string,subject_id:int,teacher_id:int,time_start:string,time_end:string}>
     */
    private function normalizeTimetableRows(array $rows): array
    {
        $normalized = collect($rows)
            ->filter(fn ($row): bool => is_array($row))
            ->map(function (array $row, int $index): ?array {
                $day = trim((string) ($row['day_of_week'] ?? ''));
                $subjectId = (int) ($row['subject_id'] ?? 0);
                $teacherId = (int) ($row['teacher_id'] ?? 0);
                $timeStart = trim((string) ($row['time_start'] ?? ''));
                $timeEnd = trim((string) ($row['time_end'] ?? ''));

                $hasAnyValue = $day !== '' || $subjectId > 0 || $teacherId > 0 || $timeStart !== '' || $timeEnd !== '';
                if (! $hasAnyValue) {
                    return null;
                }

                if (! in_array($day, self::WEEK_DAYS, true)) {
                    throw ValidationException::withMessages([
                        'timetable_rows.'.$index.'.day_of_week' => ['Please select day from Monday to Sunday.'],
                    ]);
                }

                if ($subjectId <= 0 || $teacherId <= 0 || $timeStart === '' || $timeEnd === '') {
                    throw ValidationException::withMessages([
                        'timetable_rows.'.$index => ['Each timetable row needs subject, teacher, start time and end time.'],
                    ]);
                }

                if ($timeEnd <= $timeStart) {
                    throw ValidationException::withMessages([
                        'timetable_rows.'.$index.'.time_end' => ['End time must be later than start time.'],
                    ]);
                }

                return [
                    'day_of_week' => $day,
                    'subject_id' => $subjectId,
                    'teacher_id' => $teacherId,
                    'time_start' => $timeStart,
                    'time_end' => $timeEnd,
                ];
            })
            ->filter(fn ($row): bool => is_array($row))
            ->values();

        /** @var array<int, array{day_of_week:string,subject_id:int,teacher_id:int,time_start:string,time_end:string}> $result */
        $result = $normalized->all();

        return $result;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    private function hydrateClassListRows(Request $request, InternalApiClient $api, array $items): array
    {
        return collect($items)
            ->filter(fn ($item): bool => is_array($item))
            ->map(function (array $item) use ($request, $api): array {
                $classId = (int) ($item['id'] ?? 0);
                if ($classId <= 0) {
                    return $item;
                }

                $detailResult = $api->get($request, '/api/classes/'.$classId);
                if (($detailResult['status'] ?? 0) !== 200 || ! is_array($detailResult['data']['data'] ?? null)) {
                    return $item + [
                        'teacher_preview' => [],
                        'teacher_preview_more' => 0,
                        'student_preview' => [],
                        'student_preview_more' => 0,
                        'timetable_preview' => [],
                        'timetable_preview_more' => 0,
                        'student_total' => (int) ($item['students_count'] ?? 0),
                    ];
                }

                return $item + $this->buildClassPreview($detailResult['data']['data']);
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $classItem
     * @return array<string, mixed>
     */
    private function buildClassPreview(array $classItem): array
    {
        $subjects = collect($classItem['subjects'] ?? [])
            ->filter(fn ($subject): bool => is_array($subject))
            ->mapWithKeys(fn (array $subject): array => [(int) ($subject['id'] ?? 0) => (string) ($subject['name'] ?? 'Subject')]);

        $teacherLines = collect($classItem['teachers'] ?? [])
            ->filter(fn ($teacher): bool => is_array($teacher))
            ->map(function (array $teacher) use ($subjects): string {
                $name = trim((string) ($teacher['name'] ?? 'Teacher'));
                $subjectId = (int) data_get($teacher, 'pivot.subject_id', 0);
                $subjectName = (string) ($subjects[$subjectId] ?? 'Subject');

                return $name.' ('.$subjectName.')';
            })
            ->filter(fn (string $line): bool => $line !== '')
            ->unique()
            ->values();

        $studentLines = collect($classItem['students'] ?? [])
            ->filter(fn ($student): bool => is_array($student))
            ->map(function (array $student): string {
                $name = trim((string) data_get($student, 'user.name', 'Student'));
                $khmerName = trim((string) data_get($student, 'user.khmer_name', ''));

                if ($khmerName === '') {
                    return $name;
                }

                return $name.' / '.$khmerName;
            })
            ->filter(fn (string $line): bool => $line !== '')
            ->values();

        $dayOrder = array_flip(self::WEEK_DAYS);
        $timetableLines = collect($classItem['timetables'] ?? [])
            ->filter(fn ($row): bool => is_array($row))
            ->filter(fn (array $row): bool => in_array((string) ($row['day_of_week'] ?? ''), self::WEEK_DAYS, true))
            ->sortBy(fn (array $row): string => sprintf(
                '%02d-%s',
                $dayOrder[(string) ($row['day_of_week'] ?? 'monday')] ?? 99,
                (string) ($row['time_start'] ?? '00:00:00')
            ))
            ->map(function (array $row): string {
                $day = ucfirst((string) ($row['day_of_week'] ?? '-'));
                $timeStart = substr((string) ($row['time_start'] ?? '-'), 0, 5);
                $timeEnd = substr((string) ($row['time_end'] ?? '-'), 0, 5);
                $subject = (string) data_get($row, 'subject.name', (string) ($row['subject_id'] ?? 'Subject'));
                $teacher = (string) data_get($row, 'teacher.name', (string) ($row['teacher_id'] ?? 'Teacher'));

                return $day.' '.$timeStart.'-'.$timeEnd.' | '.$subject.' | '.$teacher;
            })
            ->values();

        return [
            'teacher_preview' => $teacherLines->take(3)->all(),
            'teacher_preview_more' => max(0, $teacherLines->count() - 3),
            'student_preview' => $studentLines->take(4)->all(),
            'student_preview_more' => max(0, $studentLines->count() - 4),
            'timetable_preview' => $timetableLines->take(4)->all(),
            'timetable_preview_more' => max(0, $timetableLines->count() - 4),
            'student_total' => $studentLines->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchPaginatedItems(
        Request $request,
        InternalApiClient $api,
        string $endpoint,
        array $filters = [],
        int $maxPages = 10
    ): array {
        $page = 1;
        $items = collect();

        do {
            $result = $api->get($request, $endpoint, array_merge($filters, [
                'page' => $page,
            ]));

            if (($result['status'] ?? 0) !== 200) {
                break;
            }

            $payload = $result['data'] ?? [];
            $pageItems = collect($payload['data'] ?? [])
                ->filter(fn ($item): bool => is_array($item));

            $items = $items->merge($pageItems);

            $currentPage = (int) ($payload['current_page'] ?? $page);
            $lastPage = (int) ($payload['last_page'] ?? $currentPage);
            $page++;
        } while ($currentPage < $lastPage && $page <= $maxPages);

        return $items->values()->all();
    }

    /**
     * @return array<string, int|null>
     */
    private function studentSyncPayload(Request $request, int $schoolId, ?int $classId): array
    {
        $payload = ['class_id' => $classId];

        if ($request->user()->role === 'super-admin') {
            $payload['school_id'] = $schoolId;
        }

        return $payload;
    }
}
