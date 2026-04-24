<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithInternalApi;
use App\Services\InternalApiClient;
use App\Support\KhmerMonth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentReportController extends Controller
{
    use InteractsWithInternalApi;

    public function index(Request $request, InternalApiClient $api): View|RedirectResponse
    {
        $userRole = (string) ($request->user()->role ?? '');
        $filters = $this->validateFilters($request, false);

        if ($userRole !== 'super-admin') {
            unset($filters['school_id']);
        }

        $reportMode = (string) ($filters['report_mode'] ?? 'monthly');
        $academicYear = trim((string) ($filters['academic_year'] ?? $this->defaultAcademicYear()));
        $selectedSchoolId = isset($filters['school_id']) ? (int) $filters['school_id'] : 0;
        $selectedClassId = isset($filters['class_id']) ? (int) $filters['class_id'] : 0;
        $selectedStudentId = isset($filters['student_id']) ? (int) $filters['student_id'] : 0;
        $selectedMonth = isset($filters['month']) ? (int) $filters['month'] : null;
        $selectedSemester = isset($filters['semester']) ? (int) $filters['semester'] : null;

        $schoolOptions = $userRole === 'super-admin'
            ? $this->loadSchoolSelectOptions($request, $api)
            : [];
        $classOptions = $this->fetchClassOptions($request, $api, $selectedSchoolId > 0 ? $selectedSchoolId : null);
        $studentOptions = $this->fetchStudentOptions(
            $request,
            $api,
            $selectedSchoolId > 0 ? $selectedSchoolId : null,
            $selectedClassId > 0 ? $selectedClassId : null
        );

        $reportCard = null;
        $attendanceReport = null;
        $reportError = null;
        $reportQuery = [];

        if ($selectedStudentId > 0) {
            $loadedReport = $this->loadReportData(
                request: $request,
                api: $api,
                studentId: $selectedStudentId,
                classId: $selectedClassId > 0 ? $selectedClassId : null,
                reportMode: $reportMode,
                month: $selectedMonth,
                semester: $selectedSemester,
                academicYear: $academicYear,
            );

            $reportCard = $loadedReport['reportCard'];
            $attendanceReport = $loadedReport['attendanceReport'];
            $reportError = $loadedReport['reportError'];
            $reportQuery = $loadedReport['reportQuery'];
        }

        return view('web.reports.student_progress', [
            'filters' => [
                'school_id' => $selectedSchoolId > 0 ? $selectedSchoolId : '',
                'class_id' => $selectedClassId > 0 ? $selectedClassId : '',
                'student_id' => $selectedStudentId > 0 ? $selectedStudentId : '',
                'report_mode' => $reportMode,
                'month' => $selectedMonth,
                'semester' => $selectedSemester,
                'academic_year' => $academicYear,
            ],
            'userRole' => $userRole,
            'schoolOptions' => $schoolOptions,
            'classOptions' => $classOptions,
            'studentOptions' => $studentOptions,
            'reportCard' => $reportCard,
            'attendanceReport' => $attendanceReport,
            'reportError' => $reportError,
            'reportQuery' => $reportQuery,
            'monthOptions' => $this->monthOptions(),
        ]);
    }

    public function exportExcel(Request $request, InternalApiClient $api): StreamedResponse|RedirectResponse
    {
        $userRole = (string) ($request->user()->role ?? '');
        $filters = $this->validateFilters($request, true);

        if ($userRole !== 'super-admin') {
            unset($filters['school_id']);
        }

        $selectedSchoolId = isset($filters['school_id']) ? (int) $filters['school_id'] : 0;
        $selectedClassId = isset($filters['class_id']) ? (int) $filters['class_id'] : 0;
        $selectedStudentId = (int) ($filters['student_id'] ?? 0);
        $reportMode = (string) ($filters['report_mode'] ?? 'monthly');
        $selectedMonth = isset($filters['month']) ? (int) $filters['month'] : null;
        $selectedSemester = isset($filters['semester']) ? (int) $filters['semester'] : null;
        $academicYear = trim((string) ($filters['academic_year'] ?? $this->defaultAcademicYear()));

        $loadedReport = $this->loadReportData(
            request: $request,
            api: $api,
            studentId: $selectedStudentId,
            classId: $selectedClassId > 0 ? $selectedClassId : null,
            reportMode: $reportMode,
            month: $selectedMonth,
            semester: $selectedSemester,
            academicYear: $academicYear,
        );

        if (! is_array($loadedReport['reportCard'])) {
            return redirect()
                ->route('panel.student-reports.index', $this->buildFilterQuery(
                    schoolId: $selectedSchoolId,
                    classId: $selectedClassId,
                    studentId: $selectedStudentId,
                    reportMode: $reportMode,
                    month: $selectedMonth,
                    semester: $selectedSemester,
                    academicYear: $academicYear,
                ))
                ->withErrors([
                    'student_id' => $loadedReport['reportError'] ?? __('ui.student_reports.unable_to_load'),
                ]);
        }

        $rows = $this->buildExportRows(
            reportCard: $loadedReport['reportCard'],
            attendanceReport: $loadedReport['attendanceReport'],
            reportMode: $reportMode,
            month: $selectedMonth,
            semester: $selectedSemester,
            academicYear: $academicYear,
        );

        $fileName = sprintf(
            'student_report_%d_%s_%s.xls',
            $selectedStudentId,
            $reportMode,
            now()->format('Ymd_His')
        );

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            if (! $handle) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");

            foreach ($rows as $row) {
                fputcsv($handle, $row, "\t");
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    /**
     * @return array{reportCard:array<string,mixed>|null,attendanceReport:array<string,mixed>|null,reportError:string|null,reportQuery:array<string,mixed>}
     */
    private function loadReportData(
        Request $request,
        InternalApiClient $api,
        int $studentId,
        ?int $classId,
        string $reportMode,
        ?int $month,
        ?int $semester,
        string $academicYear,
    ): array {
        $reportQuery = $this->buildReportQuery(
            reportMode: $reportMode,
            month: $month,
            semester: $semester,
            academicYear: $academicYear,
        );

        $reportCard = null;
        $attendanceReport = null;
        $reportError = null;

        $reportResult = $api->get($request, '/api/report-cards/'.$studentId, $reportQuery);
        if (($reportResult['status'] ?? 0) === 200 && is_array($reportResult['data']['data'] ?? null)) {
            $reportCard = $reportResult['data']['data'];
        } else {
            $reportError = $this->firstError($this->extractErrors($reportResult));
        }

        $attendanceQuery = $this->buildAttendanceQuery(
            studentId: $studentId,
            classId: $classId,
            reportMode: $reportMode,
            month: $month,
            semester: $semester,
            academicYear: $academicYear,
        );

        $attendanceResult = $api->get($request, '/api/attendance/monthly-report', $attendanceQuery);
        if (($attendanceResult['status'] ?? 0) === 200 && is_array($attendanceResult['data']['data'] ?? null)) {
            $attendanceReport = $attendanceResult['data']['data'];
        }

        return [
            'reportCard' => $reportCard,
            'attendanceReport' => $attendanceReport,
            'reportError' => $reportError,
            'reportQuery' => $reportQuery,
        ];
    }

    /**
     * @return array<int, array<int, string|int|float|null>>
     */
    private function buildExportRows(
        array $reportCard,
        ?array $attendanceReport,
        string $reportMode,
        ?int $month,
        ?int $semester,
        string $academicYear,
    ): array {
        $student = is_array($reportCard['student'] ?? null) ? $reportCard['student'] : [];
        $summary = is_array($reportCard['summary'] ?? null) ? $reportCard['summary'] : [];
        $subjects = collect($reportCard['subjects'] ?? [])->filter(fn ($row): bool => is_array($row))->values();

        $attendance = is_array($attendanceReport) ? $attendanceReport : [];
        $attendanceSummary = is_array($attendance['summary'] ?? null) ? $attendance['summary'] : [];

        $rows = [[
            __('ui.student_reports.export_columns.student_id'),
            __('ui.student_reports.export_columns.student_name'),
            __('ui.student_reports.export_columns.class_name'),
            __('ui.student_reports.export_columns.report_mode'),
            __('ui.student_reports.export_columns.period'),
            __('ui.student_reports.export_columns.academic_year'),
            __('ui.student_reports.export_columns.average_score'),
            __('ui.student_reports.export_columns.gpa'),
            __('ui.student_reports.export_columns.overall_grade'),
            __('ui.student_reports.export_columns.class_rank'),
            __('ui.student_reports.export_columns.present'),
            __('ui.student_reports.export_columns.absent'),
            __('ui.student_reports.export_columns.leave'),
            __('ui.student_reports.export_columns.attendance_total'),
            __('ui.student_reports.export_columns.subject'),
            __('ui.student_reports.export_columns.subject_average'),
            __('ui.student_reports.export_columns.subject_grade'),
            __('ui.student_reports.export_columns.assessment'),
            __('ui.student_reports.export_columns.month'),
            __('ui.student_reports.export_columns.semester'),
            __('ui.student_reports.export_columns.quarter'),
            __('ui.student_reports.export_columns.exam'),
            __('ui.student_reports.export_columns.total'),
            __('ui.student_reports.export_columns.entry_grade'),
        ]];

        $baseColumns = [
            (string) ($student['id'] ?? ''),
            (string) data_get($student, 'user.name', ''),
            (string) data_get($student, 'class.name', ''),
            $this->reportModeLabel($reportMode),
            $this->selectedPeriodLabel($reportMode, $month, $semester),
            $academicYear,
            (string) ($summary['average_score'] ?? '0.00'),
            (string) ($summary['gpa'] ?? '0.00'),
            (string) ($summary['overall_grade'] ?? '-'),
            (string) ($summary['rank_in_class'] ?? '-'),
            (string) ((int) ($attendanceSummary['present_count'] ?? 0)),
            (string) ((int) ($attendanceSummary['absent_count'] ?? 0)),
            (string) ((int) ($attendanceSummary['leave_count'] ?? 0)),
            (string) ((int) ($attendanceSummary['total_records'] ?? 0)),
        ];

        if ($subjects->isEmpty()) {
            $rows[] = array_merge($baseColumns, array_fill(0, 10, ''));

            return $rows;
        }

        foreach ($subjects as $subject) {
            $entries = collect($subject['entries'] ?? [])->filter(fn ($row): bool => is_array($row))->values();
            $subjectColumns = [
                (string) ($subject['subject_name'] ?? '-'),
                (string) ($subject['average_score'] ?? '-'),
                (string) ($subject['grade'] ?? '-'),
            ];

            if ($entries->isEmpty()) {
                $rows[] = array_merge($baseColumns, $subjectColumns, array_fill(0, 7, ''));

                continue;
            }

            foreach ($entries as $entry) {
                $entryMonth = '-';
                if (isset($entry['month']) && is_numeric($entry['month'])) {
                    $entryMonth = $this->monthLabel((int) $entry['month']);
                } elseif (isset($entry['month']) && is_string($entry['month']) && trim($entry['month']) !== '') {
                    $entryMonth = trim($entry['month']);
                }

                $rows[] = array_merge($baseColumns, $subjectColumns, [
                    $this->assessmentTypeLabel((string) ($entry['assessment_type'] ?? '')),
                    $entryMonth,
                    (string) ($entry['semester'] ?? '-'),
                    (string) ($entry['quarter'] ?? '-'),
                    (string) ($entry['exam_score'] ?? '-'),
                    (string) ($entry['total_score'] ?? '-'),
                    (string) ($entry['grade'] ?? '-'),
                ]);
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateFilters(Request $request, bool $requireStudent): array
    {
        $studentRules = ['integer'];
        array_unshift($studentRules, $requireStudent ? 'required' : 'nullable');

        return $request->validate([
            'school_id' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'student_id' => $studentRules,
            'report_mode' => ['nullable', 'in:monthly,semester,yearly'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
            'academic_year' => ['nullable', 'string', 'max:20'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFilterQuery(
        int $schoolId,
        int $classId,
        int $studentId,
        string $reportMode,
        ?int $month,
        ?int $semester,
        string $academicYear,
    ): array {
        $query = [
            'school_id' => $schoolId > 0 ? $schoolId : null,
            'class_id' => $classId > 0 ? $classId : null,
            'student_id' => $studentId > 0 ? $studentId : null,
            'report_mode' => $reportMode,
            'month' => $month,
            'semester' => $semester,
            'academic_year' => $academicYear,
        ];

        return collect($query)
            ->reject(fn ($value): bool => $value === null || $value === '')
            ->all();
    }

    private function reportModeLabel(string $reportMode): string
    {
        return match ($reportMode) {
            'monthly' => __('ui.student_reports.mode_monthly'),
            'semester' => __('ui.student_reports.mode_semester'),
            'yearly' => __('ui.student_reports.mode_yearly'),
            default => __('ui.student_reports.period_custom_value'),
        };
    }

    private function selectedPeriodLabel(string $reportMode, ?int $month, ?int $semester): string
    {
        return match ($reportMode) {
            'monthly' => __('ui.student_reports.period_monthly_value', [
                'month' => $this->monthLabel($month ?? (int) now()->month),
            ]),
            'semester' => __('ui.student_reports.period_semester_value', [
                'semester' => $semester ?? 1,
            ]),
            'yearly' => __('ui.student_reports.period_yearly_value'),
            default => __('ui.student_reports.period_custom_value'),
        };
    }

    private function assessmentTypeLabel(string $assessmentType): string
    {
        return match ($assessmentType) {
            'monthly' => __('ui.student_reports.mode_monthly'),
            'semester' => __('ui.student_reports.mode_semester'),
            'yearly' => __('ui.student_reports.mode_yearly'),
            default => $assessmentType !== '' ? $assessmentType : '-',
        };
    }

    private function monthLabel(int $month): string
    {
        $options = $this->monthOptions();

        return $options[$month] ?? '-';
    }

    /**
     * @return array<int, string>
     */
    private function monthOptions(): array
    {
        if (app()->getLocale() === 'km') {
            return KhmerMonth::options();
        }

        return [
            1 => __('ui.student_reports.month_names.1'),
            2 => __('ui.student_reports.month_names.2'),
            3 => __('ui.student_reports.month_names.3'),
            4 => __('ui.student_reports.month_names.4'),
            5 => __('ui.student_reports.month_names.5'),
            6 => __('ui.student_reports.month_names.6'),
            7 => __('ui.student_reports.month_names.7'),
            8 => __('ui.student_reports.month_names.8'),
            9 => __('ui.student_reports.month_names.9'),
            10 => __('ui.student_reports.month_names.10'),
            11 => __('ui.student_reports.month_names.11'),
            12 => __('ui.student_reports.month_names.12'),
        ];
    }

    /**
     * @return array<int, array{id:int,label:string,school_id:int|null}>
     */
    private function fetchClassOptions(Request $request, InternalApiClient $api, ?int $schoolId): array
    {
        $query = ['per_page' => 100];
        if ((string) ($request->user()->role ?? '') === 'super-admin' && $schoolId) {
            $query['school_id'] = $schoolId;
        }

        $result = $api->get($request, '/api/classes', $query);
        if (($result['status'] ?? 0) !== 200) {
            return [];
        }

        return collect($result['data']['data'] ?? [])
            ->filter(fn ($row): bool => is_array($row))
            ->map(function (array $row): array {
                $id = (int) ($row['id'] ?? 0);
                $name = trim((string) ($row['name'] ?? __('ui.student_reports.class_default_name', ['id' => $id])));
                $grade = trim((string) ($row['grade_level'] ?? ''));
                $room = trim((string) ($row['room'] ?? ''));
                $label = $name;
                if ($grade !== '') {
                    $label .= ' ('.__('ui.student_reports.grade_prefix', ['grade' => $grade]).')';
                }
                if ($room !== '') {
                    $label .= ' - '.$room;
                }
                $label .= ' - '.__('ui.student_reports.id_label').': '.$id;

                $schoolIdValue = (int) ($row['school_id'] ?? 0);

                return [
                    'id' => $id,
                    'label' => $label,
                    'school_id' => $schoolIdValue > 0 ? $schoolIdValue : null,
                ];
            })
            ->filter(fn (array $row): bool => $row['id'] > 0)
            ->sortBy('label')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id:int,label:string,class_id:int|null,school_id:int|null}>
     */
    private function fetchStudentOptions(
        Request $request,
        InternalApiClient $api,
        ?int $schoolId,
        ?int $classId,
    ): array {
        $query = ['per_page' => '100'];
        if ((string) ($request->user()->role ?? '') === 'super-admin' && $schoolId) {
            $query['school_id'] = $schoolId;
        }
        if ($classId) {
            $query['class_id'] = $classId;
        }

        $result = $api->get($request, '/api/students', $query);
        if (($result['status'] ?? 0) !== 200) {
            return [];
        }

        return collect($result['data']['data'] ?? [])
            ->filter(fn ($row): bool => is_array($row))
            ->map(function (array $row): array {
                $id = (int) ($row['id'] ?? 0);
                $name = trim((string) data_get($row, 'user.name', __('ui.student_reports.student_default_name', ['id' => $id])));
                $className = trim((string) data_get($row, 'class.name', ''));
                $label = $name;
                if ($className !== '') {
                    $label .= ' ('.$className.')';
                }
                $label .= ' - '.__('ui.student_reports.id_label').': '.$id;
                $classId = isset($row['class_id']) ? (int) $row['class_id'] : 0;
                $schoolIdValue = isset($row['school_id'])
                    ? (int) $row['school_id']
                    : (int) data_get($row, 'user.school_id', 0);

                return [
                    'id' => $id,
                    'label' => $label,
                    'class_id' => $classId > 0 ? $classId : null,
                    'school_id' => $schoolIdValue > 0 ? $schoolIdValue : null,
                ];
            })
            ->filter(fn (array $row): bool => $row['id'] > 0)
            ->sortBy('label')
            ->values()
            ->all();
    }

    /**
     * @return array{assessment_type:string,academic_year:string,month?:int,semester?:int}
     */
    private function buildReportQuery(
        string $reportMode,
        ?int $month,
        ?int $semester,
        string $academicYear,
    ): array {
        $query = [
            'assessment_type' => $reportMode,
            'academic_year' => $academicYear,
        ];

        if ($reportMode === 'monthly') {
            $query['month'] = $month ?? (int) now()->month;
        }

        if ($reportMode === 'semester') {
            $query['semester'] = $semester ?? 1;
        }

        return $query;
    }

    /**
     * @return array{student_id:int,class_id?:int,period_type:string,month?:string,year?:int,semester?:int}
     */
    private function buildAttendanceQuery(
        int $studentId,
        ?int $classId,
        string $reportMode,
        ?int $month,
        ?int $semester,
        string $academicYear,
    ): array {
        [$startYear, $endYear] = $this->resolveAcademicYearBounds($academicYear);

        $query = [
            'student_id' => $studentId,
            'period_type' => 'month',
        ];

        if ($classId && $classId > 0) {
            $query['class_id'] = $classId;
        }

        if ($reportMode === 'semester') {
            $query['period_type'] = 'semester';
            $query['year'] = $startYear;
            $query['semester'] = $semester ?? 1;

            return $query;
        }

        if ($reportMode === 'yearly') {
            $query['period_type'] = 'year';
            $query['year'] = $startYear;

            return $query;
        }

        $selectedMonth = $month ?? (int) now()->month;
        $calendarYear = $selectedMonth >= 9 ? $startYear : $endYear;
        $query['period_type'] = 'month';
        $query['month'] = sprintf('%04d-%02d', $calendarYear, $selectedMonth);

        return $query;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function resolveAcademicYearBounds(string $academicYear): array
    {
        $value = trim($academicYear);

        if (preg_match('/(20\d{2})\D+(20\d{2})/', $value, $matches) === 1) {
            $start = (int) ($matches[1] ?? 0);
            $end = (int) ($matches[2] ?? 0);

            if ($start > 0 && $end > 0) {
                return [$start, $end];
            }
        }

        if (preg_match('/(20\d{2})/', $value, $matches) === 1) {
            $start = (int) ($matches[1] ?? (int) now()->year);

            return [$start, $start + 1];
        }

        $start = (int) now()->year;

        return [$start, $start + 1];
    }

    private function defaultAcademicYear(): string
    {
        $start = (int) now()->year;
        $end = $start + 1;

        return $start.'-'.$end;
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    private function firstError(array $errors): string
    {
        $message = Collection::make($errors)->flatten()->first();

        return is_string($message) && $message !== ''
            ? $message
            : __('ui.student_reports.unable_to_load');
    }
}
