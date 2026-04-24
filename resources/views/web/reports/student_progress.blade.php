@extends('web.layouts.app')

@section('content')
    <h1 class="title">{{ __('ui.student_reports.title') }}</h1>
    <p class="subtitle">{{ __('ui.student_reports.subtitle') }}</p>

    <div class="nav">
        <a href="{{ route('dashboard') }}">{{ __('ui.layout.dashboard') }}</a>
        <a href="{{ route('panel.scores.index') }}">{{ __('ui.layout.scores') }}</a>
    </div>

    @if (session('success'))
        <p class="flash-success">{{ session('success') }}</p>
    @endif

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    @if (!empty($reportError))
        <p class="flash-error">{{ $reportError }}</p>
    @endif

    @php
        $selectedSchoolId = (string) ($filters['school_id'] ?? '');
        $selectedClassId = (string) ($filters['class_id'] ?? '');
        $selectedStudentId = (string) ($filters['student_id'] ?? '');
        $selectedMode = (string) ($filters['report_mode'] ?? 'monthly');
        $selectedMonth = (string) ($filters['month'] ?? '');
        $selectedSemester = (string) ($filters['semester'] ?? '');
        $selectedAcademicYear = (string) ($filters['academic_year'] ?? '');

        $schoolSelectOptions = collect($schoolOptions ?? []);
        $classSelectOptions = collect($classOptions ?? []);
        $studentSelectOptions = collect($studentOptions ?? []);

        if ($selectedClassId !== '' && ! $classSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedClassId)) {
            $classSelectOptions = $classSelectOptions->prepend([
                'id' => (int) $selectedClassId,
                'label' => __('ui.student_reports.class_id_label', ['id' => $selectedClassId]),
                'school_id' => null,
            ]);
        }

        if ($selectedStudentId !== '' && ! $studentSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedStudentId)) {
            $studentSelectOptions = $studentSelectOptions->prepend([
                'id' => (int) $selectedStudentId,
                'label' => __('ui.student_reports.student_id_label', ['id' => $selectedStudentId]),
                'class_id' => null,
                'school_id' => null,
            ]);
        }

        $report = is_array($reportCard ?? null) ? $reportCard : [];
        $student = is_array($report['student'] ?? null) ? $report['student'] : [];
        $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
        $subjects = collect($report['subjects'] ?? [])->filter(fn ($row): bool => is_array($row))->values();

        $attendance = is_array($attendanceReport ?? null) ? $attendanceReport : [];
        $attendanceSummary = is_array($attendance['summary'] ?? null) ? $attendance['summary'] : [];

        $pdfParams = http_build_query($reportQuery ?? []);
        $pdfUrl = $selectedStudentId !== ''
            ? url('/api/report-cards/'.$selectedStudentId.'/pdf').($pdfParams !== '' ? '?'.$pdfParams : '')
            : '';

        $exportQuery = collect([
            'school_id' => $selectedSchoolId !== '' ? $selectedSchoolId : null,
            'class_id' => $selectedClassId !== '' ? $selectedClassId : null,
            'student_id' => $selectedStudentId !== '' ? $selectedStudentId : null,
            'report_mode' => $selectedMode,
            'month' => $selectedMonth !== '' ? $selectedMonth : null,
            'semester' => $selectedSemester !== '' ? $selectedSemester : null,
            'academic_year' => $selectedAcademicYear,
        ])->reject(fn ($value): bool => $value === null || $value === '')->all();

        $excelUrl = $selectedStudentId !== ''
            ? route('panel.student-reports.export-excel', $exportQuery)
            : '';

        $monthLabels = is_array($monthOptions ?? null) ? $monthOptions : [];
        $assessmentTypeLabels = [
            'monthly' => __('ui.student_reports.mode_monthly'),
            'semester' => __('ui.student_reports.mode_semester'),
            'yearly' => __('ui.student_reports.mode_yearly'),
        ];
    @endphp

    <form method="GET" action="{{ route('panel.student-reports.index') }}" class="panel panel-form panel-spaced">
        <div class="form-grid">
            @if (($userRole ?? '') === 'super-admin')
                <div>
                    <input type="text" class="searchable-select-search" placeholder="{{ __('ui.student_reports.search_school') }}" data-select-search-for="report_school_id">
                    <select id="report_school_id" name="school_id">
                        <option value="">{{ __('ui.student_reports.all_schools') }}</option>
                        @foreach ($schoolSelectOptions as $option)
                            <option value="{{ $option['id'] }}" {{ $selectedSchoolId === (string) $option['id'] ? 'selected' : '' }}>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <input type="text" class="searchable-select-search" placeholder="{{ __('ui.student_reports.search_class') }}" data-select-search-for="report_class_id">
                <select id="report_class_id" name="class_id">
                    <option value="">{{ __('ui.student_reports.all_classes') }}</option>
                    @foreach ($classSelectOptions as $option)
                        <option
                            value="{{ $option['id'] }}"
                            data-school-id="{{ $option['school_id'] ?? '' }}"
                            {{ $selectedClassId === (string) $option['id'] ? 'selected' : '' }}
                        >
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <input type="text" class="searchable-select-search" placeholder="{{ __('ui.student_reports.search_student') }}" data-select-search-for="report_student_id">
                <select id="report_student_id" name="student_id" required>
                    <option value="">{{ __('ui.student_reports.select_student') }}</option>
                    @foreach ($studentSelectOptions as $option)
                        <option
                            value="{{ $option['id'] }}"
                            data-school-id="{{ $option['school_id'] ?? '' }}"
                            data-class-id="{{ $option['class_id'] ?? '' }}"
                            {{ $selectedStudentId === (string) $option['id'] ? 'selected' : '' }}
                        >
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <select id="report_mode" name="report_mode">
                <option value="monthly" {{ $selectedMode === 'monthly' ? 'selected' : '' }}>{{ __('ui.student_reports.mode_monthly') }}</option>
                <option value="semester" {{ $selectedMode === 'semester' ? 'selected' : '' }}>{{ __('ui.student_reports.mode_semester') }}</option>
                <option value="yearly" {{ $selectedMode === 'yearly' ? 'selected' : '' }}>{{ __('ui.student_reports.mode_yearly') }}</option>
            </select>

            <select id="report_month" name="month">
                <option value="">{{ __('ui.student_reports.month_placeholder') }}</option>
                @foreach ($monthLabels as $monthValue => $monthLabel)
                    <option value="{{ $monthValue }}" {{ $selectedMonth === (string) $monthValue ? 'selected' : '' }}>
                        {{ $monthLabel }}
                    </option>
                @endforeach
            </select>

            <select id="report_semester" name="semester">
                <option value="">{{ __('ui.student_reports.semester_placeholder') }}</option>
                <option value="1" {{ $selectedSemester === '1' ? 'selected' : '' }}>{{ __('ui.student_reports.semester_1') }}</option>
                <option value="2" {{ $selectedSemester === '2' ? 'selected' : '' }}>{{ __('ui.student_reports.semester_2') }}</option>
            </select>

            <input type="text" name="academic_year" placeholder="{{ __('ui.student_reports.academic_year_placeholder') }}" value="{{ $selectedAcademicYear }}">
        </div>
        <button type="submit" class="btn-space-top">{{ __('ui.student_reports.generate_report') }}</button>
    </form>

    @if ($selectedStudentId === '')
        <section class="panel">
            <div class="panel-head">{{ __('ui.student_reports.panel_title') }}</div>
            <p class="text-muted" style="margin:0;">{{ __('ui.student_reports.choose_prompt') }}</p>
        </section>
    @elseif (!empty($report))
        <section class="metric-grid">
            <article class="metric-card metric-card-purple">
                <p class="metric-number">{{ $summary['average_score'] ?? '0.00' }}</p>
                <p class="metric-label">{{ __('ui.student_reports.average_score') }}</p>
            </article>
            <article class="metric-card metric-card-blue">
                <p class="metric-number">{{ $summary['gpa'] ?? '0.00' }}</p>
                <p class="metric-label">{{ __('ui.student_reports.gpa') }}</p>
            </article>
            <article class="metric-card metric-card-orange">
                <p class="metric-number">{{ $summary['overall_grade'] ?? '-' }}</p>
                <p class="metric-label">{{ __('ui.student_reports.overall_grade') }}</p>
            </article>
            <article class="metric-card metric-card-green">
                <p class="metric-number">{{ $summary['rank_in_class'] ?? '-' }}</p>
                <p class="metric-label">{{ __('ui.student_reports.class_rank') }}</p>
            </article>
        </section>

        <section class="panel panel-spaced">
            <div class="panel-head">{{ __('ui.student_reports.student_information') }}</div>
            <div class="cards">
                <div class="card">
                    <div class="label">{{ __('ui.student_reports.name') }}</div>
                    <div class="value">{{ $student['user']['name'] ?? __('ui.student_reports.default_student_name') }}</div>
                </div>
                <div class="card">
                    <div class="label">{{ __('ui.student_reports.class') }}</div>
                    <div class="value">{{ $student['class']['name'] ?? '-' }}</div>
                </div>
                <div class="card">
                    <div class="label">{{ __('ui.student_reports.records') }}</div>
                    <div class="value">{{ (int) ($summary['records_count'] ?? 0) }}</div>
                </div>
                <div class="card">
                    <div class="label">{{ __('ui.student_reports.subjects') }}</div>
                    <div class="value">{{ (int) ($summary['subjects_count'] ?? 0) }}</div>
                </div>
            </div>

            @if ($pdfUrl !== '' || $excelUrl !== '')
                <div class="quick-actions" style="margin-top:12px;">
                    @if ($pdfUrl !== '')
                        <a href="{{ $pdfUrl }}" target="_blank" rel="noopener">{{ __('ui.student_reports.download_pdf') }}</a>
                    @endif
                    @if ($excelUrl !== '')
                        <a href="{{ $excelUrl }}">{{ __('ui.student_reports.export_excel') }}</a>
                    @endif
                </div>
            @endif
        </section>

        <section class="panel panel-spaced">
            <div class="panel-head">{{ __('ui.student_reports.attendance_summary') }}</div>
            <div class="cards">
                <div class="card">
                    <div class="label">{{ __('ui.student_reports.present') }}</div>
                    <div class="value">{{ (int) ($attendanceSummary['present_count'] ?? 0) }}</div>
                </div>
                <div class="card">
                    <div class="label">{{ __('ui.student_reports.absent') }}</div>
                    <div class="value">{{ (int) ($attendanceSummary['absent_count'] ?? 0) }}</div>
                </div>
                <div class="card">
                    <div class="label">{{ __('ui.student_reports.leave') }}</div>
                    <div class="value">{{ (int) ($attendanceSummary['leave_count'] ?? 0) }}</div>
                </div>
                <div class="card">
                    <div class="label">{{ __('ui.student_reports.total_records') }}</div>
                    <div class="value">{{ (int) ($attendanceSummary['total_records'] ?? 0) }}</div>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head">{{ __('ui.student_reports.subject_breakdown') }}</div>
            <table>
                <thead>
                    <tr>
                        <th>{{ __('ui.student_reports.subject') }}</th>
                        <th>{{ __('ui.student_reports.average') }}</th>
                        <th>{{ __('ui.student_reports.grade') }}</th>
                        <th>{{ __('ui.student_reports.entries') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subjects as $subject)
                        <tr>
                            <td>{{ $subject['subject_name'] ?? '-' }}</td>
                            <td>{{ $subject['average_score'] ?? '-' }}</td>
                            <td>{{ $subject['grade'] ?? '-' }}</td>
                            <td>{{ count($subject['entries'] ?? []) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">{{ __('ui.student_reports.no_score_data') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        @foreach ($subjects as $subject)
            @php
                $entries = collect($subject['entries'] ?? [])->filter(fn ($row): bool => is_array($row))->values();
            @endphp
            @if ($entries->isNotEmpty())
                <section class="panel panel-spaced">
                    <div class="panel-head">{{ __('ui.student_reports.detailed_entries_for_subject', ['subject' => $subject['subject_name'] ?? __('ui.student_reports.subject')]) }}</div>
                    <table>
                        <thead>
                            <tr>
                                <th>{{ __('ui.student_reports.assessment') }}</th>
                                <th>{{ __('ui.student_reports.month') }}</th>
                                <th>{{ __('ui.student_reports.semester') }}</th>
                                <th>{{ __('ui.student_reports.academic_year') }}</th>
                                <th>{{ __('ui.student_reports.quarter') }}</th>
                                <th>{{ __('ui.student_reports.exam') }}</th>
                                <th>{{ __('ui.student_reports.total') }}</th>
                                <th>{{ __('ui.student_reports.grade') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($entries as $entry)
                                @php
                                    $entryAssessmentType = (string) ($entry['assessment_type'] ?? '');
                                    $entryMonthRaw = $entry['month'] ?? null;
                                    $entryMonthLabel = '-';
                                    if (is_numeric($entryMonthRaw)) {
                                        $entryMonthLabel = $monthLabels[(int) $entryMonthRaw] ?? (string) $entryMonthRaw;
                                    } elseif (is_string($entryMonthRaw) && trim($entryMonthRaw) !== '') {
                                        $entryMonthLabel = trim($entryMonthRaw);
                                    }
                                @endphp
                                <tr>
                                    <td>{{ $assessmentTypeLabels[$entryAssessmentType] ?? ($entryAssessmentType !== '' ? $entryAssessmentType : '-') }}</td>
                                    <td>{{ $entryMonthLabel }}</td>
                                    <td>{{ $entry['semester'] ?? '-' }}</td>
                                    <td>{{ $entry['academic_year'] ?? '-' }}</td>
                                    <td>{{ $entry['quarter'] ?? '-' }}</td>
                                    <td>{{ $entry['exam_score'] ?? '-' }}</td>
                                    <td>{{ $entry['total_score'] ?? '-' }}</td>
                                    <td>{{ $entry['grade'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endif
        @endforeach
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modeSelect = document.getElementById('report_mode');
            var monthSelect = document.getElementById('report_month');
            var semesterSelect = document.getElementById('report_semester');
            var schoolSelect = document.getElementById('report_school_id');
            var classSelect = document.getElementById('report_class_id');
            var studentSelect = document.getElementById('report_student_id');

            var applyModeFields = function () {
                var mode = modeSelect ? modeSelect.value : 'monthly';
                if (monthSelect) {
                    monthSelect.style.display = mode === 'monthly' ? '' : 'none';
                }
                if (semesterSelect) {
                    semesterSelect.style.display = mode === 'semester' ? '' : 'none';
                }
            };

            var applyScopeFilter = function () {
                if (!studentSelect) {
                    return;
                }

                var selectedSchool = schoolSelect ? String(schoolSelect.value || '') : '';
                var selectedClass = classSelect ? String(classSelect.value || '') : '';

                Array.prototype.slice.call(studentSelect.options).forEach(function (option, index) {
                    if (index === 0) {
                        option.hidden = false;
                        option.disabled = false;
                        return;
                    }

                    var optionSchool = String(option.getAttribute('data-school-id') || '');
                    var optionClass = String(option.getAttribute('data-class-id') || '');

                    var schoolAllowed = selectedSchool === '' || optionSchool === '' || optionSchool === selectedSchool;
                    var classAllowed = selectedClass === '' || optionClass === '' || optionClass === selectedClass;
                    var allowed = schoolAllowed && classAllowed;

                    option.hidden = !allowed;
                    option.disabled = !allowed;
                });

                var selectedOption = studentSelect.options[studentSelect.selectedIndex];
                if (selectedOption && selectedOption.disabled) {
                    studentSelect.value = '';
                }
            };

            modeSelect?.addEventListener('change', applyModeFields);
            schoolSelect?.addEventListener('change', applyScopeFilter);
            classSelect?.addEventListener('change', applyScopeFilter);

            applyModeFields();
            applyScopeFilter();
        });
    </script>
@endsection
