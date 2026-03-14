<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\BuildsAcademicDashboardMetrics;
use App\Http\Controllers\Web\Concerns\InteractsWithInternalApi;
use App\Services\InternalApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ParentController extends Controller
{
    use BuildsAcademicDashboardMetrics;
    use InteractsWithInternalApi;

    public function dashboard(Request $request, InternalApiClient $api): View|RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => ['nullable', 'integer'],
        ]);

        $meResult = $api->get($request, '/api/auth/me');
        if (($meResult['status'] ?? 0) !== 200) {
            return redirect()->away(route('login', [], false))->withErrors($this->extractErrors($meResult));
        }

        $userPayload = $meResult['data']['user'] ?? [];
        $children = collect($userPayload['children'] ?? [])->filter(fn ($item) => is_array($item))->values();
        $childOptions = $this->buildChildOptions($children);

        $selectedStudentId = (int) ($validated['student_id'] ?? 0);
        if ($selectedStudentId <= 0 || ! $childOptions->contains(fn ($option) => $option['id'] === $selectedStudentId)) {
            $selectedStudentId = (int) ($childOptions->first()['id'] ?? 0);
        }

        $selectedChild = $childOptions->firstWhere('id', $selectedStudentId);
        $selectedClassId = (int) ($selectedChild['class_id'] ?? 0);
        $selectedChildName = (string) ($selectedChild['label'] ?? 'No child selected');

        $attendancePresent = 0;
        $attendanceAbsent = 0;
        $attendanceLeave = 0;
        $homeworkSummary = ['done' => 0, 'not_done' => 0, 'overdue' => 0];
        $gpaSummary = ['gpa' => '0.00', 'average_score' => '0.00'];
        $acknowledgedIncidents = 0;
        $unacknowledgedIncidents = 0;
        $homeworkRows = [];

        if ($selectedStudentId > 0) {
            $attendancePresent = $this->fetchPaginatedTotal($request, $api, '/api/attendance', [
                'student_id' => $selectedStudentId,
                'status' => 'P',
            ]);
            $attendanceAbsent = $this->fetchPaginatedTotal($request, $api, '/api/attendance', [
                'student_id' => $selectedStudentId,
                'status' => 'A',
            ]);
            $attendanceLeave = $this->fetchPaginatedTotal($request, $api, '/api/attendance', [
                'student_id' => $selectedStudentId,
                'status' => 'L',
            ]);

            $homeworks = $selectedClassId > 0
                ? $this->fetchPaginatedItems($request, $api, '/api/homeworks', ['class_id' => $selectedClassId], 6)
                : [];
            $homeworkSummary = $this->summarizeHomeworkStatuses($homeworks, $selectedStudentId);
            $homeworkRows = $this->buildHomeworkRows($homeworks, $selectedStudentId);

            $scores = $this->fetchPaginatedItems($request, $api, '/api/scores', [
                'student_id' => $selectedStudentId,
            ], 6);
            $gpaSummary = $this->summarizeGpa($scores, $selectedClassId > 0 ? $selectedClassId : null);

            $acknowledgedIncidents = $this->fetchPaginatedTotal($request, $api, '/api/incident-reports', [
                'student_id' => $selectedStudentId,
                'acknowledged' => 1,
            ]);
            $unacknowledgedIncidents = $this->fetchPaginatedTotal($request, $api, '/api/incident-reports', [
                'student_id' => $selectedStudentId,
                'acknowledged' => 0,
            ]);
        }

        return view('web.panel', [
            'title' => 'Parent Dashboard',
            'subtitle' => $selectedStudentId > 0
                ? 'Monitoring: '.$selectedChildName
                : 'No child is linked to this parent account.',
            'stats' => [
                ['label' => 'Present (P)', 'value' => $attendancePresent],
                ['label' => 'Absent (A)', 'value' => $attendanceAbsent],
                ['label' => 'Leave (L)', 'value' => $attendanceLeave],
                ['label' => 'Homework Done', 'value' => $homeworkSummary['done']],
                ['label' => 'Homework Not Done', 'value' => $homeworkSummary['not_done']],
                ['label' => 'Homework Overdue', 'value' => $homeworkSummary['overdue']],
                ['label' => 'GPA', 'value' => $gpaSummary['gpa']],
                ['label' => 'Avg Score', 'value' => $gpaSummary['average_score']],
                ['label' => 'Incidents Acknowledged', 'value' => $acknowledgedIncidents],
                ['label' => 'Incidents Unacknowledged', 'value' => $unacknowledgedIncidents],
            ],
            'tableTitle' => 'Recent Homework Status',
            'columns' => ['Title', 'Subject', 'Due Date', 'Status'],
            'rows' => $homeworkRows,
            'panel' => 'parent',
            'childOptions' => $childOptions->map(fn ($option) => [
                'id' => $option['id'],
                'label' => $option['label'],
            ])->all(),
            'selectedChildId' => $selectedStudentId > 0 ? (string) $selectedStudentId : '',
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $children
     * @return Collection<int, array{id:int,label:string,class_id:int}>
     */
    private function buildChildOptions(Collection $children): Collection
    {
        return $children->map(function (array $child): array {
            $id = (int) ($child['id'] ?? 0);
            $name = trim((string) ($child['user']['name'] ?? 'Child '.$id));
            $className = trim((string) ($child['class']['name'] ?? ''));
            $label = $name !== '' ? $name : 'Child '.$id;
            if ($className !== '') {
                $label .= ' ('.$className.')';
            }
            $label .= ' - ID: '.$id;

            return [
                'id' => $id,
                'label' => $label,
                'class_id' => (int) ($child['class_id'] ?? 0),
            ];
        })->filter(fn (array $option) => $option['id'] > 0)->values();
    }
}
