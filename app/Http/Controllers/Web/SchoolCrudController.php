<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithInternalApi;
use App\Services\InternalApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolCrudController extends Controller
{
    use InteractsWithInternalApi;

    public function enrollmentDate(Request $request, InternalApiClient $api): View|RedirectResponse
    {
        $user = $request->user();
        $role = (string) ($user?->normalizedRole() ?? '');
        $querySchoolId = (int) $request->integer('school_id');
        $assignedSchoolId = (int) ($user?->school_id ?? 0);

        $schoolOptions = $this->loadSchoolSelectOptions($request, $api);
        $canChooseSchool = $role === 'super-admin';
        $selectionRequired = false;

        if ($role === 'admin' && $assignedSchoolId <= 0) {
            return view('web.crud.schools.enrollment-date', [
                'school' => [],
                'schoolId' => 0,
                'defaultEnrollmentDate' => '',
                'schoolMissing' => true,
                'selectionRequired' => false,
                'canChooseSchool' => false,
                'schoolOptions' => [],
            ]);
        }

        $schoolId = $role === 'admin'
            ? $assignedSchoolId
            : ($querySchoolId > 0 ? $querySchoolId : 0);

        if ($role === 'super-admin' && $schoolId <= 0) {
            $selectionRequired = true;
        }

        if ($schoolId <= 0) {
            return view('web.crud.schools.enrollment-date', [
                'school' => [],
                'schoolId' => 0,
                'defaultEnrollmentDate' => '',
                'schoolMissing' => false,
                'selectionRequired' => $selectionRequired,
                'canChooseSchool' => $canChooseSchool,
                'schoolOptions' => $schoolOptions,
            ]);
        }

        $result = $api->get($request, '/api/schools/'.$schoolId);
        if ($result['status'] !== 200) {
            return redirect()->away(route('panel.schools.index', [], false))
                ->withErrors($this->extractErrors($result));
        }

        $school = $result['data']['data'] ?? [];
        $defaultEnrollmentDate = (string) data_get($school, 'config_details.default_enrollment_date', '');

        return view('web.crud.schools.enrollment-date', [
            'school' => $school,
            'schoolId' => $schoolId,
            'defaultEnrollmentDate' => $defaultEnrollmentDate,
            'schoolMissing' => false,
            'selectionRequired' => false,
            'canChooseSchool' => $canChooseSchool,
            'schoolOptions' => $schoolOptions,
        ]);
    }

    public function updateEnrollmentDate(Request $request, InternalApiClient $api): RedirectResponse
    {
        $user = $request->user();
        $role = (string) ($user?->normalizedRole() ?? '');

        $payload = $request->validate([
            'school_id' => ['required', 'integer', 'min:1'],
            'default_enrollment_date' => ['nullable', 'date'],
        ]);

        $schoolId = (int) $payload['school_id'];
        if ($role === 'admin' && (int) ($user?->school_id ?? 0) !== $schoolId) {
            return back()->withErrors([
                'school_id' => ['You can only update your own school enrollment date.'],
            ]);
        }

        if (! in_array($role, ['super-admin', 'admin'], true)) {
            return back()->withErrors([
                'role' => ['Only super-admin or admin can update enrollment date.'],
            ]);
        }

        $result = $api->put($request, '/api/schools/'.$schoolId, [
            'default_enrollment_date' => $payload['default_enrollment_date'] ?? null,
        ]);

        if ($result['status'] !== 200) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.schools.enrollment-date', ['school_id' => $schoolId], false))
            ->with('success', 'Enrollment Date updated successfully.');
    }

    public function index(Request $request, InternalApiClient $api): View|RedirectResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $result = $api->get($request, '/api/schools', array_filter($filters, fn ($v) => $v !== null && $v !== ''));

        if ($result['status'] !== 200) {
            return redirect()->away(route('dashboard', [], false))->withErrors($this->extractErrors($result));
        }

        $payload = $result['data'] ?? [];

        return view('web.crud.schools.index', [
            'items' => $payload['data'] ?? [],
            'meta' => $payload,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('web.crud.schools.form', [
            'mode' => 'create',
            'item' => null,
        ]);
    }

    public function store(Request $request, InternalApiClient $api): RedirectResponse
    {
        $payload = $this->validatePayload($request);
        $result = $api->post($request, '/api/schools', $payload);

        if ($result['status'] !== 201) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.schools.index', [], false))->with('success', 'School created successfully.');
    }

    public function edit(Request $request, int $school, InternalApiClient $api): View|RedirectResponse
    {
        $result = $api->get($request, '/api/schools/'.$school);

        if ($result['status'] !== 200) {
            return redirect()->away(route('panel.schools.index', [], false))->withErrors($this->extractErrors($result));
        }

        return view('web.crud.schools.form', [
            'mode' => 'edit',
            'item' => $result['data']['data'] ?? null,
        ]);
    }

    public function update(Request $request, int $school, InternalApiClient $api): RedirectResponse
    {
        $payload = $this->validatePayload($request);
        $result = $api->put($request, '/api/schools/'.$school, $payload);

        if ($result['status'] !== 200) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.schools.index', [], false))->with('success', 'School updated successfully.');
    }

    public function destroy(Request $request, int $school, InternalApiClient $api): RedirectResponse
    {
        $result = $api->delete($request, '/api/schools/'.$school);

        if ($result['status'] !== 200) {
            return back()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.schools.index', [], false))->with('success', 'School deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'school_code' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'config_details' => ['nullable', 'string'],
            'default_enrollment_date' => ['nullable', 'date'],
        ]);

        $payload['config_details'] = $this->normalizeConfigDetails($payload['config_details'] ?? null);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeConfigDetails(mixed $value): array
    {
        $rawConfig = trim((string) $value);
        if ($rawConfig === '') {
            return [];
        }

        $decoded = json_decode($rawConfig, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return [
            'notes' => $rawConfig,
        ];
    }
}
