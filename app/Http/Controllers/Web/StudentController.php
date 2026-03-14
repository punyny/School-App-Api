<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\BuildsAcademicDashboardMetrics;
use App\Http\Controllers\Web\Concerns\InteractsWithInternalApi;
use App\Services\InternalApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentController extends Controller
{
    use BuildsAcademicDashboardMetrics;
    use InteractsWithInternalApi;

    public function dashboard(Request $request, InternalApiClient $api): View|RedirectResponse
    {
        $meResult = $api->get($request, '/api/auth/me');
        if (($meResult['status'] ?? 0) !== 200) {
            return redirect()->away(route('login', [], false))->withErrors($this->extractErrors($meResult));
        }

        $userPayload = $meResult['data']['user'] ?? [];
        $studentProfile = is_array($userPayload['student_profile'] ?? null)
            ? $userPayload['student_profile']
            : [];

        $studentId = (int) ($studentProfile['id'] ?? 0);
        $classId = (int) ($studentProfile['class_id'] ?? 0);

        $attendancePresent = $this->fetchPaginatedTotal($request, $api, '/api/attendance', [
            'student_id' => $studentId,
            'status' => 'P',
        ]);
        $attendanceAbsent = $this->fetchPaginatedTotal($request, $api, '/api/attendance', [
            'student_id' => $studentId,
            'status' => 'A',
        ]);
        $attendanceLeave = $this->fetchPaginatedTotal($request, $api, '/api/attendance', [
            'student_id' => $studentId,
            'status' => 'L',
        ]);

        $homeworks = $classId > 0
            ? $this->fetchPaginatedItems($request, $api, '/api/homeworks', ['class_id' => $classId], 6)
            : [];
        $homeworkSummary = $this->summarizeHomeworkStatuses($homeworks, $studentId);

        $scores = $this->fetchPaginatedItems($request, $api, '/api/scores', [
            'student_id' => $studentId,
        ], 6);
        $gpaSummary = $this->summarizeGpa($scores, $classId > 0 ? $classId : null);

        $acknowledgedIncidents = $this->fetchPaginatedTotal($request, $api, '/api/incident-reports', [
            'student_id' => $studentId,
            'acknowledged' => 1,
        ]);
        $unacknowledgedIncidents = $this->fetchPaginatedTotal($request, $api, '/api/incident-reports', [
            'student_id' => $studentId,
            'acknowledged' => 0,
        ]);

        return view('web.panel', [
            'title' => 'Student Dashboard',
            'subtitle' => 'Attendance, homework, GPA and incident overview.',
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
            'rows' => $this->buildHomeworkRows($homeworks, $studentId),
            'panel' => 'student',
        ]);
    }
}
