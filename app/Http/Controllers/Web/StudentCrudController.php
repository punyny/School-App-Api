<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithInternalApi;
use App\Services\InternalApiClient;
use App\Support\PasswordRule;
use App\Support\ProfileImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StudentCrudController extends Controller
{
    use InteractsWithInternalApi;

    public function index(Request $request, InternalApiClient $api): View|RedirectResponse
    {
        $filters = $request->validate([
            'school_id' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'in:0,1'],
            'per_page' => ['nullable', 'string', 'max:20'],
        ]);

        $rawPerPage = trim((string) ($filters['per_page'] ?? ''));
        if ($rawPerPage !== '') {
            if (Str::lower($rawPerPage) === 'all') {
                $filters['per_page'] = 'all';
            } elseif (ctype_digit($rawPerPage) && (int) $rawPerPage >= 1 && (int) $rawPerPage <= 5000) {
                $filters['per_page'] = (string) ((int) $rawPerPage);
            } else {
                throw ValidationException::withMessages([
                    'per_page' => ['per_page must be 20 or all.'],
                ]);
            }
        } else {
            unset($filters['per_page']);
        }

        $result = $api->get($request, '/api/students', array_filter($filters, fn ($v) => $v !== null && $v !== ''));

        if ($result['status'] !== 200) {
            return redirect()->away(route('dashboard', [], false))->withErrors($this->extractErrors($result));
        }

        $payload = $result['data'] ?? [];

        return view('web.crud.students.index', [
            'items' => $payload['data'] ?? [],
            'meta' => $payload,
            'filters' => $filters,
            'userRole' => $request->user()->role,
        ] + $this->loadAcademicSelectOptions($request, $api, true, false, false));
    }

    public function create(Request $request, InternalApiClient $api): View
    {
        return view('web.crud.students.form', [
            'mode' => 'create',
            'item' => null,
            'enrollment' => null,
            'userRole' => $request->user()->role,
        ] + $this->loadAcademicSelectOptions($request, $api, true, false, false));
    }

    public function store(Request $request, InternalApiClient $api): RedirectResponse
    {
        $payload = $this->validatePayload($request, true);
        $enrollmentDate = trim((string) Arr::pull($payload, 'enrollment_date', ''));
        $requestedClassId = (int) ($payload['class_id'] ?? 0);
        $result = $api->post($request, '/api/students', $payload);

        if ($result['status'] !== 201) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        $messageParts = ['Student created successfully.'];
        $student = is_array($result['data']['data'] ?? null) ? $result['data']['data'] : [];
        $studentId = (int) ($student['id'] ?? 0);
        $storedClassId = (int) ($student['class_id'] ?? 0);

        if ($studentId > 0 && $requestedClassId > 0 && $storedClassId !== $requestedClassId) {
            $classUpdateResult = $api->put($request, '/api/students/'.$studentId, [
                'class_id' => $requestedClassId,
            ]);

            if (($classUpdateResult['status'] ?? 0) === 200 && is_array($classUpdateResult['data']['data'] ?? null)) {
                /** @var array<string, mixed> $student */
                $student = $classUpdateResult['data']['data'];
            } else {
                $messageParts[] = 'Class assignment was not saved: '.$this->firstApiErrorMessage($classUpdateResult, 'Unknown API error.');
            }
        }

        if ($enrollmentDate !== '') {
            $syncMessage = $this->syncEnrollmentDate($request, $api, $student, $enrollmentDate);
            if ($syncMessage !== null) {
                $messageParts[] = $syncMessage;
            }
        }

        return redirect()
            ->away(route('panel.students.index', [], false))
            ->with('success', implode(' ', $messageParts));
    }

    public function edit(Request $request, int $student, InternalApiClient $api): View|RedirectResponse
    {
        $result = $api->get($request, '/api/students/'.$student);

        if ($result['status'] !== 200) {
            return redirect()->away(route('panel.students.index', [], false))->withErrors($this->extractErrors($result));
        }

        return view('web.crud.students.form', [
            'mode' => 'edit',
            'item' => $result['data']['data'] ?? null,
            'enrollment' => $this->fetchLatestEnrollmentForStudent($request, $api, $student),
            'userRole' => $request->user()->role,
        ] + $this->loadAcademicSelectOptions($request, $api, true, false, false));
    }

    public function show(Request $request, int $student, InternalApiClient $api): View|RedirectResponse
    {
        $result = $api->get($request, '/api/students/'.$student);

        if ($result['status'] !== 200) {
            return redirect()->away(route('panel.students.index', [], false))->withErrors($this->extractErrors($result));
        }

        return view('web.crud.students.show', [
            'item' => $result['data']['data'] ?? null,
            'userRole' => $request->user()->role,
        ]);
    }

    public function update(Request $request, int $student, InternalApiClient $api): RedirectResponse
    {
        $payload = $this->validatePayload($request, false);
        $enrollmentDate = trim((string) Arr::pull($payload, 'enrollment_date', ''));
        $result = $api->put($request, '/api/students/'.$student, $payload);

        if ($result['status'] !== 200) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        $messageParts = ['Student updated successfully.'];
        if ($enrollmentDate !== '') {
            $studentItem = is_array($result['data']['data'] ?? null) ? $result['data']['data'] : [];
            $syncMessage = $this->syncEnrollmentDate($request, $api, $studentItem, $enrollmentDate);
            if ($syncMessage !== null) {
                $messageParts[] = $syncMessage;
            }
        }

        return redirect()
            ->away(route('panel.students.index', [], false))
            ->with('success', implode(' ', $messageParts));
    }

    public function destroy(Request $request, int $student, InternalApiClient $api): RedirectResponse
    {
        $result = $api->delete($request, '/api/students/'.$student);

        if ($result['status'] !== 200) {
            return back()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.students.index', [], false))->with('success', 'Student deleted successfully.');
    }

    public function importCsv(Request $request, InternalApiClient $api): RedirectResponse
    {
        $payload = $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt'],
            'school_id' => ['nullable', 'integer'],
        ]);

        $apiPayload = [
            'file' => $request->file('csv_file'),
        ];

        if ($request->user()->role === 'super-admin' && isset($payload['school_id'])) {
            $apiPayload['school_id'] = (int) $payload['school_id'];
        }

        $result = $api->post($request, '/api/students/import/csv', $apiPayload);

        if (($result['status'] ?? 0) !== 201) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        $data = $result['data']['data'] ?? [];
        $created = (int) ($data['created'] ?? 0);
        $updated = (int) ($data['updated'] ?? 0);
        $errors = is_array($data['errors'] ?? null) ? $data['errors'] : [];

        if (($created + $updated) === 0 && $errors !== []) {
            $messages = collect($errors)
                ->take(5)
                ->map(function ($row): string {
                    $line = (int) ($row['line'] ?? 0);
                    $message = (string) ($row['message'] ?? 'Unknown CSV error');

                    return $line > 0 ? "Line {$line}: {$message}" : $message;
                })
                ->all();

            return back()->withInput()->withErrors(['csv' => $messages]);
        }

        $message = sprintf(
            'Student CSV import completed. Created: %d, Updated: %d, Errors: %d',
            $created,
            $updated,
            count($errors)
        );

        if ($errors !== []) {
            $firstError = (string) ($errors[0]['message'] ?? '');
            if ($firstError !== '') {
                $message .= ' | First error: '.$firstError;
            }
        }

        return redirect()->away(route('panel.students.index', [], false))->with('success', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $isCreate): array
    {
        if (! $isCreate) {
            $rawPassword = $request->input('password');
            if (is_string($rawPassword) && trim($rawPassword) === '') {
                $request->merge(['password' => null]);
            }
        }

        $studentIdRule = ['nullable', 'string', 'max:100'];
        $khmerNameRule = $isCreate
            ? ['required', 'string', 'max:255']
            : ['nullable', 'string', 'max:255'];
        $passwordRule = $isCreate
            ? ['required', 'string', 'max:255', PasswordRule::defaults()]
            : ['nullable', 'string', 'max:255', PasswordRule::defaults()];

        $payload = $request->validate([
            'school_id' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'student_id' => $studentIdRule,
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'khmer_name' => $khmerNameRule,
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'password' => $passwordRule,
            'phone' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'in:male,female,other'],
            'dob' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'image' => ProfileImageStorage::uploadValidationRules(),
            'remove_image' => ['nullable', 'boolean'],
            'grade' => ['nullable', 'string', 'max:20'],
            'parent_name' => ['nullable', 'string', 'max:255'],
            'parent_ids' => ['nullable', 'string'],
            'enrollment_date' => ['nullable', 'date'],
        ]);

        if (($payload['remove_image'] ?? false) === true) {
            $payload['image_url'] = null;
        }
        if (! $request->hasFile('image')) {
            unset($payload['image']);
        }
        unset($payload['remove_image']);

        if ($request->user()->role !== 'super-admin') {
            unset($payload['school_id']);
        }

        $rawParentIds = trim((string) Arr::get($payload, 'parent_ids', ''));
        if ($rawParentIds === '') {
            unset($payload['parent_ids']);
        } else {
            $payload['parent_ids'] = collect(explode(',', $rawParentIds))
                ->map(fn (string $id): int => (int) trim($id))
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchLatestEnrollmentForStudent(
        Request $request,
        InternalApiClient $api,
        int $studentId
    ): ?array {
        if ($studentId <= 0) {
            return null;
        }

        $result = $api->get($request, '/api/enrollments', [
            'student_id' => $studentId,
            'per_page' => 100,
        ]);

        if (($result['status'] ?? 0) !== 200) {
            return null;
        }

        $rows = $result['data']['data'] ?? null;
        if (! is_array($rows) || $rows === []) {
            return null;
        }

        $first = $rows[0] ?? null;
        if (! is_array($first)) {
            return null;
        }

        return $first;
    }

    /**
     * Returns a human-readable message when sync failed, otherwise null.
     *
     * @param  array<string, mixed>  $student
     */
    private function syncEnrollmentDate(
        Request $request,
        InternalApiClient $api,
        array $student,
        string $enrollmentDate
    ): ?string {
        $studentId = (int) ($student['id'] ?? 0);
        if ($studentId <= 0) {
            return 'Enrollment date was not saved because student information is incomplete.';
        }

        $classId = (int) ($student['class_id'] ?? 0);
        if ($classId <= 0) {
            return 'Enrollment date was not saved because this student has no class assigned yet.';
        }

        $existing = $this->fetchLatestEnrollmentForStudent($request, $api, $studentId);
        if ($existing !== null) {
            $enrollmentId = (int) ($existing['enrollment_id'] ?? $existing['id'] ?? 0);
            if ($enrollmentId <= 0) {
                return 'Enrollment date was not saved because enrollment id is missing.';
            }

            $academicYearId = (int) ($existing['academic_year_id'] ?? 0);
            $sectionId = $this->resolveSectionIdForEnrollment($request, $api, $classId, $academicYearId);

            $updatePayload = [
                'class_id' => $classId,
                'enrollment_date' => $enrollmentDate,
            ];
            if ($sectionId > 0) {
                $updatePayload['section_id'] = $sectionId;
            }

            $updateResult = $api->patch($request, '/api/enrollments/'.$enrollmentId, $updatePayload);
            if (($updateResult['status'] ?? 0) === 200) {
                return null;
            }

            return 'Enrollment date was not saved: '.$this->firstApiErrorMessage($updateResult, 'Unknown API error.');
        }

        $academicYearId = $this->resolveCurrentAcademicYearId($request, $api);
        if ($academicYearId <= 0) {
            return 'Enrollment date was not saved because no academic year is available.';
        }

        $sectionId = $this->resolveSectionIdForEnrollment($request, $api, $classId, $academicYearId);
        if ($sectionId <= 0) {
            return 'Enrollment date was not saved because no section is available for this class.';
        }

        $createResult = $api->post($request, '/api/enrollments', [
            'student_id' => $studentId,
            'academic_year_id' => $academicYearId,
            'class_id' => $classId,
            'section_id' => $sectionId,
            'enrollment_date' => $enrollmentDate,
            'status' => 'Enrolled',
        ]);

        if (($createResult['status'] ?? 0) === 201) {
            return null;
        }

        return 'Enrollment date was not saved: '.$this->firstApiErrorMessage($createResult, 'Unknown API error.');
    }

    private function resolveCurrentAcademicYearId(Request $request, InternalApiClient $api): int
    {
        $currentResult = $api->get($request, '/api/academic-years', [
            'is_current' => 1,
            'per_page' => 1,
        ]);

        $rows = $currentResult['data']['data'] ?? null;
        if (is_array($rows) && isset($rows[0]) && is_array($rows[0])) {
            return (int) ($rows[0]['academic_year_id'] ?? 0);
        }

        $fallbackResult = $api->get($request, '/api/academic-years', ['per_page' => 1]);
        $fallbackRows = $fallbackResult['data']['data'] ?? null;
        if (is_array($fallbackRows) && isset($fallbackRows[0]) && is_array($fallbackRows[0])) {
            return (int) ($fallbackRows[0]['academic_year_id'] ?? 0);
        }

        return 0;
    }

    private function resolveSectionIdForEnrollment(
        Request $request,
        InternalApiClient $api,
        int $classId,
        int $academicYearId
    ): int {
        if ($classId <= 0) {
            return 0;
        }

        $query = [
            'class_id' => $classId,
            'per_page' => 100,
        ];
        if ($academicYearId > 0) {
            $query['academic_year_id'] = $academicYearId;
        }

        $result = $api->get($request, '/api/sections', $query);
        $rows = $result['data']['data'] ?? null;
        if (is_array($rows) && isset($rows[0]) && is_array($rows[0])) {
            return (int) ($rows[0]['section_id'] ?? 0);
        }

        return 0;
    }

    /**
     * @param  array{status:int, data:array<string,mixed>|null}  $result
     */
    private function firstApiErrorMessage(array $result, string $fallback): string
    {
        $errors = $this->extractErrors($result);
        foreach ($errors as $fieldMessages) {
            if ($fieldMessages !== [] && isset($fieldMessages[0]) && $fieldMessages[0] !== '') {
                return (string) $fieldMessages[0];
            }
        }

        return $fallback;
    }
}
