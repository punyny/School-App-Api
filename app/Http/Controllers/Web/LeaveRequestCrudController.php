<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithInternalApi;
use App\Services\InternalApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveRequestCrudController extends Controller
{
    use InteractsWithInternalApi;

    public function index(Request $request, InternalApiClient $api): View|RedirectResponse
    {
        $filters = $request->validate([
            'student_id' => ['nullable', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:pending,approved,rejected'],
            'start_date_from' => ['nullable', 'date'],
            'start_date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $result = $api->get($request, '/api/leave-requests', array_filter($filters, fn ($v) => $v !== null && $v !== ''));

        if ($result['status'] !== 200) {
            return redirect()->away(route('dashboard', [], false))->withErrors($this->extractErrors($result));
        }

        $payload = $result['data'] ?? [];

        return view('web.crud.leave-requests.index', [
            'items' => $payload['data'] ?? [],
            'meta' => $payload,
            'filters' => $filters,
            'userRole' => $request->user()->normalizedRole(),
            'authUserId' => (int) $request->user()->id,
            'canApproveLeaveRequest' => $request->user()->can('web-approve-leave-requests'),
        ] + $this->loadAcademicSelectOptions($request, $api, false, true, true));
    }

    public function create(Request $request, InternalApiClient $api): View
    {
        $options = $this->loadAcademicSelectOptions($request, $api, false, true, true);
        $filteredStudentOptions = $this->filterStudentOptionsForSubmitter($request, $api, $options['studentOptions'] ?? []);
        [$filteredSubjectOptions, $subjectOptionsByStudent] = $this->buildSubjectOptionsByStudent($request, $api, $filteredStudentOptions, $options['subjectOptions'] ?? []);
        $defaultStudentId = (int) (($filteredStudentOptions[0]['id'] ?? 0));

        return view('web.crud.leave-requests.form', [
            'mode' => 'create',
            'item' => null,
            'userRole' => $request->user()->normalizedRole(),
            'canApproveLeaveRequest' => $request->user()->can('web-approve-leave-requests'),
            'studentOptions' => $filteredStudentOptions,
            'subjectOptions' => $filteredSubjectOptions,
            'subjectOptionsByStudent' => $subjectOptionsByStudent,
            'defaultStudentId' => $defaultStudentId > 0 ? $defaultStudentId : null,
        ]);
    }

    public function store(Request $request, InternalApiClient $api): RedirectResponse
    {
        $payload = $request->validate([
            'student_id' => ['nullable', 'integer'],
            'subject_ids' => ['required', 'array', 'min:1'],
            'subject_ids.*' => ['integer'],
            'request_type' => ['required', 'in:hourly,multi_day'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'return_date' => ['nullable', 'date'],
            'total_days' => ['nullable', 'integer', 'min:1'],
            'reason' => ['required', 'string'],
        ]);

        $result = $api->post($request, '/api/leave-requests', $payload);

        if ($result['status'] !== 201) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.leave-requests.index', [], false))->with('success', 'Leave request created successfully.');
    }

    public function edit(Request $request, int $leaveRequest, InternalApiClient $api): View|RedirectResponse
    {
        $result = $api->get($request, '/api/leave-requests/'.$leaveRequest);

        if ($result['status'] !== 200) {
            return redirect()->away(route('panel.leave-requests.index', [], false))->withErrors($this->extractErrors($result));
        }

        return view('web.crud.leave-requests.form', [
            'mode' => 'edit',
            'item' => $result['data']['data'] ?? null,
            'userRole' => $request->user()->normalizedRole(),
            'canApproveLeaveRequest' => $request->user()->can('web-approve-leave-requests'),
            'subjectOptionsByStudent' => [],
            'defaultStudentId' => null,
        ] + $this->loadAcademicSelectOptions($request, $api, false, true, true));
    }

    public function update(Request $request, int $leaveRequest, InternalApiClient $api): RedirectResponse
    {
        $payload = $request->validate([
            'subject_ids' => ['nullable', 'array', 'min:1'],
            'subject_ids.*' => ['integer'],
            'request_type' => ['nullable', 'in:hourly,multi_day'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'return_date' => ['nullable', 'date'],
            'total_days' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string'],
            'status' => ['nullable', 'in:pending,approved,rejected'],
        ]);

        $status = $payload['status'] ?? null;
        unset($payload['status']);
        $payload = array_filter($payload, fn ($value): bool => $value !== null && $value !== '' && $value !== []);

        if ($payload !== []) {
            $result = $api->put($request, '/api/leave-requests/'.$leaveRequest, $payload);
            if ($result['status'] !== 200) {
                return back()->withInput()->withErrors($this->extractErrors($result));
            }
        }

        if ($status !== null) {
            $statusResult = $api->patch($request, '/api/leave-requests/'.$leaveRequest.'/status', [
                'status' => $status,
            ]);

            if ($statusResult['status'] !== 200) {
                return back()->withInput()->withErrors($this->extractErrors($statusResult));
            }
        }

        if ($payload === [] && $status === null) {
            return back()->withErrors(['api' => ['No changes to update.']]);
        }

        return redirect()->away(route('panel.leave-requests.index', [], false))->with('success', 'Leave request updated successfully.');
    }

    public function destroy(Request $request, int $leaveRequest, InternalApiClient $api): RedirectResponse
    {
        $result = $api->delete($request, '/api/leave-requests/'.$leaveRequest);

        if ($result['status'] !== 200) {
            return back()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.leave-requests.index', [], false))->with('success', 'Leave request deleted successfully.');
    }

    /**
     * @param  array<int, array{id:int,label:string,class_id:int|null,school_id:int|null}>  $studentOptions
     * @return array<int, array{id:int,label:string,class_id:int|null,school_id:int|null}>
     */
    private function filterStudentOptionsForSubmitter(Request $request, InternalApiClient $api, array $studentOptions): array
    {
        $role = $request->user()->normalizedRole();
        if (! in_array($role, ['student', 'parent'], true)) {
            return $studentOptions;
        }

        $meResult = $api->get($request, '/api/auth/me');
        if (($meResult['status'] ?? 0) !== 200 || ! is_array($meResult['data']['user'] ?? null)) {
            return $studentOptions;
        }

        $me = $meResult['data']['user'];
        $allowedStudentIds = [];

        if ($role === 'student') {
            $studentId = (int) ($me['student_profile']['id'] ?? $me['studentProfile']['id'] ?? 0);
            if ($studentId > 0) {
                $allowedStudentIds[] = $studentId;
            }
        }

        if ($role === 'parent') {
            $children = $me['children'] ?? [];
            if (is_array($children)) {
                foreach ($children as $child) {
                    if (! is_array($child)) {
                        continue;
                    }
                    $studentId = (int) ($child['id'] ?? 0);
                    if ($studentId > 0) {
                        $allowedStudentIds[] = $studentId;
                    }
                }
            }
        }

        $allowedStudentIds = array_values(array_unique(array_filter($allowedStudentIds)));
        if ($allowedStudentIds === []) {
            return [];
        }

        return array_values(array_filter($studentOptions, fn (array $option): bool => in_array((int) ($option['id'] ?? 0), $allowedStudentIds, true)));
    }

    /**
     * @param  array<int, array{id:int,label:string,class_id:int|null,school_id:int|null}>  $studentOptions
     * @param  array<int, array{id:int,label:string}>  $fallbackSubjectOptions
     * @return array{
     *   0: array<int, array{id:int,label:string}>,
     *   1: array<int, array<int, array{id:int,label:string}>>
     * }
     */
    private function buildSubjectOptionsByStudent(
        Request $request,
        InternalApiClient $api,
        array $studentOptions,
        array $fallbackSubjectOptions
    ): array {
        $subjectsByClass = [];
        $subjectsByStudent = [];
        $merged = [];

        foreach ($studentOptions as $studentOption) {
            $studentId = (int) ($studentOption['id'] ?? 0);
            $classId = (int) ($studentOption['class_id'] ?? 0);
            if ($studentId <= 0 || $classId <= 0) {
                continue;
            }

            if (! isset($subjectsByClass[$classId])) {
                $subjectsByClass[$classId] = $this->loadClassSubjects($request, $api, $classId);
            }

            $subjectsByStudent[$studentId] = $subjectsByClass[$classId];
            foreach ($subjectsByClass[$classId] as $subjectOption) {
                $merged[(int) $subjectOption['id']] = $subjectOption;
            }
        }

        if ($merged === []) {
            foreach ($fallbackSubjectOptions as $subjectOption) {
                $id = (int) ($subjectOption['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $merged[$id] = [
                    'id' => $id,
                    'label' => (string) ($subjectOption['label'] ?? 'Subject '.$id),
                ];
            }
        }

        return [array_values($merged), $subjectsByStudent];
    }

    /**
     * @return array<int, array{id:int,label:string}>
     */
    private function loadClassSubjects(Request $request, InternalApiClient $api, int $classId): array
    {
        $result = $api->get($request, '/api/classes/'.$classId);
        if (($result['status'] ?? 0) !== 200 || ! is_array($result['data']['data'] ?? null)) {
            return [];
        }

        $subjects = $result['data']['data']['subjects'] ?? [];
        if (! is_array($subjects)) {
            return [];
        }

        $options = [];
        foreach ($subjects as $subject) {
            if (! is_array($subject)) {
                continue;
            }

            $subjectId = (int) ($subject['id'] ?? 0);
            if ($subjectId <= 0) {
                continue;
            }

            $name = trim((string) ($subject['name'] ?? $subject['subject_name'] ?? 'Subject '.$subjectId));
            $options[] = [
                'id' => $subjectId,
                'label' => $name.' - ID: '.$subjectId,
            ];
        }

        return $options;
    }
}
