<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithInternalApi;
use App\Services\InternalApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceCrudController extends Controller
{
    use InteractsWithInternalApi;

    public function index(Request $request, InternalApiClient $api): View|RedirectResponse
    {
        $filters = $request->validate([
            'class_id' => ['nullable', 'integer'],
            'student_id' => ['nullable', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:P,A,L'],
            'period_type' => ['nullable', 'in:month,semester,year,range'],
            'month' => ['nullable', 'date_format:Y-m'],
            'year' => ['nullable', 'integer', 'digits:4', 'min:2000', 'max:2100'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if (
            empty($filters['period_type'])
            && empty($filters['month'])
            && empty($filters['year'])
            && empty($filters['semester'])
            && empty($filters['date_from'])
            && empty($filters['date_to'])
        ) {
            $filters['period_type'] = 'month';
            $filters['month'] = now()->format('Y-m');
        }

        $options = $this->loadAttendanceOptions($request, $api, true);
        $result = $api->get($request, '/api/attendance', array_filter($filters, fn ($v) => $v !== null && $v !== ''));
        $reportFilters = [
            'class_id' => $filters['class_id'] ?? null,
            'subject_id' => $filters['subject_id'] ?? null,
            'period_type' => $filters['period_type'] ?? null,
            'month' => $filters['month'] ?? null,
            'year' => $filters['year'] ?? null,
            'semester' => $filters['semester'] ?? null,
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
        ];
        $reportRequirementMessage = $this->resolveReportRequirementMessage((string) $request->user()->role, $filters);
        $reportResult = null;

        if ($reportRequirementMessage === null) {
            $reportResult = $api->get($request, '/api/attendance/monthly-report', array_filter($reportFilters, fn ($v) => $v !== null && $v !== ''));
        }

        if ($result['status'] !== 200) {
            return redirect()->away(route('dashboard', [], false))->withErrors($this->extractErrors($result));
        }

        $payload = $result['data'] ?? [];

        return view('web.crud.attendance.index', [
            'items' => $payload['data'] ?? [],
            'meta' => $payload,
            'filters' => $filters,
            'userRole' => $request->user()->role,
            'monthlyReport' => ((($reportResult['status'] ?? 0) === 200) && is_array($reportResult['data'] ?? null))
                ? ($reportResult['data']['data'] ?? null)
                : null,
            'reportRequirementMessage' => $reportRequirementMessage,
        ] + $options);
    }

    public function create(Request $request, InternalApiClient $api): View
    {
        return view('web.crud.attendance.form', [
            'mode' => 'create',
            'item' => null,
            'userRole' => $request->user()->role,
        ] + $this->loadAttendanceOptions($request, $api, true));
    }

    public function store(Request $request, InternalApiClient $api): RedirectResponse
    {
        if ($request->has('records')) {
            $payload = $request->validate([
                'class_id' => ['required', 'integer'],
                'subject_id' => ['required', 'integer'],
                'date' => ['required', 'date'],
                'time_start' => ['required', 'date_format:H:i'],
                'time_end' => ['nullable', 'date_format:H:i'],
                'records' => ['required', 'array', 'min:1'],
                'records.*.student_id' => ['required', 'integer'],
                'records.*.status' => ['required', 'in:P,A,L'],
                'records.*.remarks' => ['nullable', 'string', 'max:255'],
            ]);

            $result = $api->post($request, '/api/attendance/daily-sheet', $payload);

            if (($result['status'] ?? 0) !== 201) {
                return back()->withInput()->withErrors($this->extractErrors($result));
            }

            return redirect()->away(route('panel.attendance.index', [], false))
                ->with('success', (string) ($result['data']['message'] ?? 'Daily attendance saved successfully.'));
        }

        $payload = $request->validate([
            'student_id' => ['required', 'integer'],
            'class_id' => ['required', 'integer'],
            'subject_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end' => ['nullable', 'date_format:H:i'],
            'status' => ['required', 'in:P,A,L'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $api->post($request, '/api/attendance', $payload);

        if ($result['status'] !== 201) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.attendance.index', [], false))->with('success', 'Attendance created successfully.');
    }

    public function edit(Request $request, int $attendance, InternalApiClient $api): View|RedirectResponse
    {
        $result = $api->get($request, '/api/attendance/'.$attendance);

        if ($result['status'] !== 200) {
            return redirect()->away(route('panel.attendance.index', [], false))->withErrors($this->extractErrors($result));
        }

        return view('web.crud.attendance.form', [
            'mode' => 'edit',
            'item' => $result['data']['data'] ?? null,
            'userRole' => $request->user()->role,
        ] + $this->loadAttendanceOptions($request, $api, true));
    }

    public function update(Request $request, int $attendance, InternalApiClient $api): RedirectResponse
    {
        $payload = $request->validate([
            'student_id' => ['required', 'integer'],
            'class_id' => ['required', 'integer'],
            'subject_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end' => ['nullable', 'date_format:H:i'],
            'status' => ['required', 'in:P,A,L'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $api->put($request, '/api/attendance/'.$attendance, $payload);

        if ($result['status'] !== 200) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.attendance.index', [], false))->with('success', 'Attendance updated successfully.');
    }

    public function destroy(Request $request, int $attendance, InternalApiClient $api): RedirectResponse
    {
        $result = $api->delete($request, '/api/attendance/'.$attendance);

        if ($result['status'] !== 200) {
            return back()->withErrors($this->extractErrors($result));
        }

        return redirect()->away(route('panel.attendance.index', [], false))->with('success', 'Attendance deleted successfully.');
    }

    /**
     * @return array{
     *     classOptions: array<int, array{id:int,label:string,school_id:int|null}>,
     *     subjectOptions: array<int, array{id:int,label:string,class_ids:array<int,int>}>,
     *     subjectOptionsByClass: array<string, array<int, array{id:int,label:string}>>,
     *     studentOptions: array<int, array{id:int,label:string,class_id:int|null,school_id:int|null}>
     * }
     */
    private function loadAttendanceOptions(Request $request, InternalApiClient $api, bool $needStudents): array
    {
        $options = $this->loadAcademicSelectOptions($request, $api, true, false, $needStudents);
        $subjectOptionsByClass = [];
        $subjectOptionsById = [];

        foreach ($options['classOptions'] as $classOption) {
            $classId = (int) ($classOption['id'] ?? 0);
            if ($classId <= 0) {
                continue;
            }

            $subjectItems = $this->extractOptionItems(
                $api->get($request, '/api/subjects', [
                    'class_id' => $classId,
                    'per_page' => 100,
                ])
            );

            foreach ($subjectItems as $subjectItem) {
                $subjectId = (int) ($subjectItem['id'] ?? 0);
                if ($subjectId <= 0) {
                    continue;
                }

                $label = trim((string) ($subjectItem['name'] ?? 'Subject'));
                $option = [
                    'id' => $subjectId,
                    'label' => ($label !== '' ? $label : 'Subject '.$subjectId).' - ID: '.$subjectId,
                ];

                $subjectOptionsByClass[(string) $classId] ??= [];
                $subjectOptionsByClass[(string) $classId][$subjectId] = $option;

                if (! isset($subjectOptionsById[$subjectId])) {
                    $subjectOptionsById[$subjectId] = $option + ['class_ids' => [$classId]];
                    continue;
                }

                $subjectOptionsById[$subjectId]['class_ids'][] = $classId;
                $subjectOptionsById[$subjectId]['class_ids'] = array_values(array_unique($subjectOptionsById[$subjectId]['class_ids']));
            }
        }

        $options['subjectOptions'] = array_values($subjectOptionsById);
        $options['subjectOptionsByClass'] = array_map(
            fn (array $rows): array => array_values($rows),
            $subjectOptionsByClass
        );

        return $options;
    }

    /**
     * @param  array{status:int, data:array<string,mixed>|null}  $result
     * @return array<int, array<string, mixed>>
     */
    private function extractOptionItems(array $result): array
    {
        if (($result['status'] ?? 0) !== 200) {
            return [];
        }

        $payload = $result['data'] ?? [];
        if (! is_array($payload)) {
            return [];
        }

        $items = $payload['data'] ?? [];
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, fn (mixed $item): bool => is_array($item)));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function resolveReportRequirementMessage(string $role, array $filters): ?string
    {
        if (! in_array($role, ['super-admin', 'admin', 'teacher'], true)) {
            return null;
        }

        if (empty($filters['class_id'])) {
            return 'Please select a class before loading the attendance report.';
        }

        if ($role === 'teacher' && empty($filters['subject_id'])) {
            return 'Please select one of your teaching subjects to load the report.';
        }

        return null;
    }
}
