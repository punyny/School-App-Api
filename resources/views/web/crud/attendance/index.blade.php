@extends('web.layouts.app')

@section('content')
    @php
        $selectedClassId = (string) ($filters['class_id'] ?? '');
        $selectedStudentId = (string) ($filters['student_id'] ?? '');
        $selectedSubjectId = (string) ($filters['subject_id'] ?? '');
        $selectedPeriodType = (string) ($filters['period_type'] ?? 'month');
        $selectedMonth = (string) ($filters['month'] ?? ($selectedPeriodType === 'month' ? now()->format('Y-m') : ''));
        $selectedYear = (string) ($filters['year'] ?? now()->format('Y'));
        $selectedSemester = (string) ($filters['semester'] ?? '1');
        $classSelectOptions = collect($classOptions ?? []);
        $studentSelectOptions = collect($studentOptions ?? []);
        $subjectSelectOptions = collect($subjectOptions ?? []);
        $subjectOptionsByClass = $subjectOptionsByClass ?? [];

        if ($selectedClassId !== '' && ! $classSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedClassId)) {
            $classSelectOptions = $classSelectOptions->prepend([
                'id' => (int) $selectedClassId,
                'label' => 'Class ID: '.$selectedClassId,
            ]);
        }

        if ($selectedStudentId !== '' && ! $studentSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedStudentId)) {
            $studentSelectOptions = $studentSelectOptions->prepend([
                'id' => (int) $selectedStudentId,
                'label' => 'Student ID: '.$selectedStudentId,
            ]);
        }

        if ($selectedSubjectId !== '' && ! $subjectSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedSubjectId)) {
            $subjectSelectOptions = $subjectSelectOptions->prepend([
                'id' => (int) $selectedSubjectId,
                'label' => 'Subject ID: '.$selectedSubjectId,
            ]);
        }

        $reportData = is_array($monthlyReport ?? null) ? $monthlyReport : [];
        $reportSummary = is_array($reportData['summary'] ?? null) ? $reportData['summary'] : [];
        $subjectReportRows = collect($reportData['subject_rows'] ?? []);
        $studentTotals = collect($reportData['student_totals'] ?? []);
        $absenceRows = collect($reportData['absence_rows'] ?? []);
        $classReportRows = collect($reportData['class_rows'] ?? []);
        $exportQuery = array_filter([
            'class_id' => $filters['class_id'] ?? null,
            'student_id' => $filters['student_id'] ?? null,
            'subject_id' => $filters['subject_id'] ?? null,
            'status' => $filters['status'] ?? null,
            'period_type' => $filters['period_type'] ?? null,
            'month' => $filters['month'] ?? null,
            'year' => $filters['year'] ?? null,
            'semester' => $filters['semester'] ?? null,
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
        $csvUrl = url('/api/attendance/export/csv').($exportQuery !== [] ? '?'.http_build_query($exportQuery) : '');
        $pdfUrl = url('/api/attendance/export/pdf').($exportQuery !== [] ? '?'.http_build_query($exportQuery) : '');
    @endphp

    <style>
        .attendance-dashboard {
            display: grid;
            gap: 16px;
        }

        .attendance-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .attendance-summary-card {
            padding: 18px;
            border-radius: 18px;
            background: linear-gradient(180deg, #ffffff 0%, #f7fbfa 100%);
            border: 1px solid var(--line);
            box-shadow: var(--shadow-sm);
        }

        .attendance-summary-card .label {
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .45px;
        }

        .attendance-summary-card .value {
            margin-top: 8px;
            font-size: 28px;
            font-weight: 800;
            color: var(--text-main);
        }

        .attendance-report-table {
            width: 100%;
            border-collapse: collapse;
        }

        .attendance-report-table th,
        .attendance-report-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #ebf1ee;
            vertical-align: top;
        }

        .attendance-report-table th {
            background: #f8fbfa;
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .45px;
        }

        .attendance-report-table tbody tr:nth-child(even) {
            background: #fcfefd;
        }

        .attendance-report-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .attendance-report-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1d4ed8;
            font-size: 11px;
            font-weight: 700;
        }

        .attendance-report-badge.badge-absent {
            background: #fff1f2;
            border-color: #fecdd3;
            color: #be123c;
        }

        .attendance-report-badge.badge-leave {
            background: #fff7ed;
            border-color: #fed7aa;
            color: #c2410c;
        }

        .attendance-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .attendance-actions a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 14px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--text-main);
        }

        .attendance-actions a.primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-2));
            color: #fff;
            border-color: transparent;
        }

        .attendance-filter-note {
            margin-top: 10px;
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.7;
        }

        .attendance-report-alert {
            margin-top: 14px;
            padding: 14px 16px;
            border-radius: 14px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
            font-weight: 600;
        }

        @media (max-width: 960px) {
            .attendance-summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .attendance-summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="attendance-dashboard">
        <div>
            <h1 class="title">ការគ្រប់គ្រងវត្តមាន</h1>
            <p class="subtitle">ស្រង់វត្តមានប្រចាំថ្ងៃតាមថ្នាក់ និងមុខវិជ្ជា ហើយពិនិត្យរបាយការណ៍អវត្តមានដោយបង្ហាញមូលហេតុ និងសរុបបានច្បាស់។</p>
        </div>

        <div class="attendance-actions">
            <a href="{{ route('dashboard') }}">ផ្ទាំងដើម</a>
            @can('web-manage-attendance')
                <a href="{{ route('panel.attendance.create') }}" class="primary">+ ស្រង់វត្តមានប្រចាំថ្ងៃ</a>
            @endcan
            @can('web-manage-substitute-assignments')
                <a href="{{ route('panel.substitute-assignments.index') }}">កំណត់គ្រូជំនួស</a>
            @endcan
            <a href="{{ $csvUrl }}">ទាញយក CSV</a>
            <a href="{{ $pdfUrl }}">ទាញយក PDF</a>
        </div>

        @if (session('success'))
            <p class="flash-success">{{ session('success') }}</p>
        @endif

        @if ($errors->any())
            <p class="flash-error">{{ $errors->first() }}</p>
        @endif

        <form method="GET" action="{{ route('panel.attendance.index') }}" class="panel panel-form panel-spaced">
            <div class="form-grid">
                <div>
                    <input type="text" class="searchable-select-search" placeholder="ស្វែងរកថ្នាក់..." data-select-search-for="filter_class_id">
                    <select id="filter_class_id" name="class_id">
                        <option value="">ជ្រើសថ្នាក់</option>
                        @foreach($classSelectOptions as $option)
                            <option value="{{ $option['id'] }}" {{ $selectedClassId === (string) $option['id'] ? 'selected' : '' }}>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <input type="text" class="searchable-select-search" placeholder="ស្វែងរកសិស្ស..." data-select-search-for="filter_student_id">
                    <select id="filter_student_id" name="student_id">
                        <option value="">ជ្រើសសិស្ស</option>
                        @foreach($studentSelectOptions as $option)
                            <option value="{{ $option['id'] }}" {{ $selectedStudentId === (string) $option['id'] ? 'selected' : '' }}>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <input type="text" class="searchable-select-search" placeholder="ស្វែងរកមុខវិជ្ជា..." data-select-search-for="filter_subject_id">
                    <select id="filter_subject_id" name="subject_id">
                        <option value="">{{ in_array($userRole ?? '', ['teacher'], true) ? 'ជ្រើសមុខវិជ្ជា (ចាំបាច់សម្រាប់គ្រូ)' : 'ជ្រើសមុខវិជ្ជា' }}</option>
                        @foreach($subjectSelectOptions as $option)
                            <option value="{{ $option['id'] }}" {{ $selectedSubjectId === (string) $option['id'] ? 'selected' : '' }}>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <select name="status">
                    <option value="">ស្ថានភាព</option>
                    <option value="P" {{ ($filters['status'] ?? '') === 'P' ? 'selected' : '' }}>វត្តមាន</option>
                    <option value="A" {{ ($filters['status'] ?? '') === 'A' ? 'selected' : '' }}>អវត្តមាន</option>
                    <option value="L" {{ ($filters['status'] ?? '') === 'L' ? 'selected' : '' }}>សុំច្បាប់</option>
                </select>

                <select name="period_type" id="attendance_period_type">
                    <option value="month" {{ $selectedPeriodType === 'month' ? 'selected' : '' }}>ប្រចាំខែ</option>
                    <option value="semester" {{ $selectedPeriodType === 'semester' ? 'selected' : '' }}>ឆមាស</option>
                    <option value="year" {{ $selectedPeriodType === 'year' ? 'selected' : '' }}>ប្រចាំឆ្នាំ</option>
                    <option value="range" {{ $selectedPeriodType === 'range' ? 'selected' : '' }}>ជ្រើសពីថ្ងៃណាដល់ថ្ងៃណា</option>
                </select>

                <input type="month" name="month" id="attendance_month" value="{{ $selectedMonth }}">
                <input type="number" name="year" id="attendance_year" value="{{ $selectedYear }}" min="2000" max="2100" placeholder="ឆ្នាំ">
                <select name="semester" id="attendance_semester">
                    <option value="1" {{ $selectedSemester === '1' ? 'selected' : '' }}>ឆមាសទី ១</option>
                    <option value="2" {{ $selectedSemester === '2' ? 'selected' : '' }}>ឆមាសទី ២</option>
                </select>
                <input type="date" name="date_from" id="attendance_date_from" value="{{ $filters['date_from'] ?? '' }}">
                <input type="date" name="date_to" id="attendance_date_to" value="{{ $filters['date_to'] ?? '' }}">
                <input type="number" name="per_page" placeholder="ចំនួនក្នុងមួយទំព័រ" value="{{ $filters['per_page'] ?? 20 }}">
            </div>
            <p class="attendance-filter-note">
                របៀបស្រង់របាយការណ៍:
                @if(in_array($userRole ?? '', ['teacher'], true))
                    សូមជ្រើសថ្នាក់សិន បន្ទាប់មកជ្រើសមុខវិជ្ជាដែលលោកគ្រូអ្នកគ្រូបានបង្រៀន ហើយចុងក្រោយជ្រើសរយៈពេលរបាយការណ៍។
                @else
                    សូមជ្រើសថ្នាក់សិន បន្ទាប់មកជ្រើសរយៈពេលរបាយការណ៍ជា ប្រចាំខែ ឆមាស ប្រចាំឆ្នាំ ឬជ្រើសពីថ្ងៃណាដល់ថ្ងៃណា។
                @endif
            </p>
            <button type="submit" class="btn-space-top">ស្វែងរក</button>
        </form>

        <section class="panel">
            <div class="panel-head">
                របាយការណ៍វត្តមាន
                <span class="text-muted">
                    @if (($reportData['period_mode'] ?? $selectedPeriodType) === 'month')
                        ប្រចាំខែ: {{ $reportData['month'] ?? $selectedMonth }}
                    @elseif (($reportData['period_mode'] ?? '') === 'semester')
                        ឆមាស: {{ $selectedSemester }} / {{ $selectedYear }}
                    @elseif (($reportData['period_mode'] ?? '') === 'year')
                        ឆ្នាំ: {{ $selectedYear }}
                    @else
                        រយៈពេល: {{ $reportData['date_from'] ?? ($filters['date_from'] ?? '-') }} ដល់ {{ $reportData['date_to'] ?? ($filters['date_to'] ?? '-') }}
                    @endif
                </span>
            </div>
            <div class="panel-body">
                @if($reportRequirementMessage ?? null)
                    <div class="attendance-report-alert">{{ $reportRequirementMessage }}</div>
                @else
                    <div class="attendance-summary-grid">
                        <div class="attendance-summary-card">
                            <div class="label">សិស្សដែលមានបញ្ហាវត្តមាន</div>
                            <div class="value">{{ (int) ($reportSummary['affected_students_count'] ?? 0) }}</div>
                        </div>
                        <div class="attendance-summary-card">
                            <div class="label">កំណត់ត្រាអវត្តមាន</div>
                            <div class="value">{{ (int) ($reportSummary['absent_count'] ?? 0) }}</div>
                        </div>
                        <div class="attendance-summary-card">
                            <div class="label">កំណត់ត្រាសុំច្បាប់</div>
                            <div class="value">{{ (int) ($reportSummary['leave_count'] ?? 0) }}</div>
                        </div>
                        <div class="attendance-summary-card">
                            <div class="label">ចំនួនម៉ោងខកខានសរុប</div>
                            <div class="value">{{ (int) ($reportSummary['total_missed_records'] ?? 0) }}</div>
                        </div>
                    </div>

                    <div class="panel-head btn-space-top">ព័ត៌មានអវត្តមានលម្អិត</div>
                    <div class="table-wrap">
                        <table class="attendance-report-table">
                            <thead>
                                <tr>
                                    <th>សិស្ស</th>
                                    <th>ថ្នាក់</th>
                                    <th>មុខវិជ្ជា</th>
                                    <th>កាលបរិច្ឆេទ</th>
                                    <th>ម៉ោង</th>
                                    <th>ស្ថានភាព</th>
                                    <th>មូលហេតុ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($absenceRows as $row)
                                    <tr>
                                        <td>{{ $row['student_name'] }}</td>
                                        <td>{{ $row['class_name'] }}</td>
                                        <td>{{ $row['subject_name'] }}</td>
                                        <td>{{ $row['date'] }}</td>
                                        <td>{{ $row['time_slot'] }}</td>
                                        <td>{{ $row['status'] === 'A' ? 'អវត្តមាន' : 'សុំច្បាប់' }}</td>
                                        <td>{{ $row['remarks'] !== '' ? $row['remarks'] : '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7">មិនមានទិន្នន័យអវត្តមានសម្រាប់លក្ខខណ្ឌដែលបានជ្រើសទេ។</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="panel-head btn-space-top">របាយការណ៍អវត្តមានតាមមុខវិជ្ជា</div>
                    <div class="table-wrap">
                        <table class="attendance-report-table">
                            <thead>
                                <tr>
                                    <th>សិស្ស</th>
                                    <th>មុខវិជ្ជា</th>
                                    <th>ម៉ោង</th>
                                    <th>អវត្តមាន</th>
                                    <th>សុំច្បាប់</th>
                                    <th>សរុបខកខាន</th>
                                    <th>ថ្ងៃ / មូលហេតុ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($subjectReportRows as $row)
                                    <tr>
                                        <td>{{ $row['student_name'] }}</td>
                                        <td>{{ $row['subject_name'] }}</td>
                                        <td>{{ $row['time_slot'] }}</td>
                                        <td>{{ $row['absent_count'] }}</td>
                                        <td>{{ $row['leave_count'] }}</td>
                                        <td>{{ $row['total_missed'] ?? ($row['absent_count'] + $row['leave_count']) }}</td>
                                        <td>
                                            @if (!empty($row['affected_dates']))
                                                <div class="attendance-report-badges">
                                                    @foreach($row['affected_dates'] as $dateRow)
                                                        <span class="attendance-report-badge {{ ($dateRow['status'] ?? '') === 'A' ? 'badge-absent' : 'badge-leave' }}">
                                                            {{ $dateRow['date'] }} {{ $dateRow['status'] }}{{ !empty($dateRow['remarks']) ? ' - '.$dateRow['remarks'] : '' }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="text-muted">មិនមានអវត្តមាន ឬសុំច្បាប់ទេ</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7">មិនមានរបាយការណ៍តាមមុខវិជ្ជាសម្រាប់លក្ខខណ្ឌដែលបានជ្រើសទេ។</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="panel-head btn-space-top">សរុបតាមសិស្ស</div>
                    <div class="table-wrap">
                        <table class="attendance-report-table">
                            <thead>
                                <tr>
                                    <th>សិស្ស</th>
                                    <th>ថ្នាក់</th>
                                    <th>មុខវិជ្ជា</th>
                                    <th>អវត្តមាន</th>
                                    <th>សុំច្បាប់</th>
                                    <th>សរុបខកខាន</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($studentTotals as $row)
                                    <tr>
                                        <td>{{ $row['student_name'] }}</td>
                                        <td>{{ $row['class_name'] }}</td>
                                        <td>{{ implode(', ', $row['subject_names'] ?? []) ?: '-' }}</td>
                                        <td>{{ $row['absent_count'] }}</td>
                                        <td>{{ $row['leave_count'] }}</td>
                                        <td>{{ $row['total_missed'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6">មិនមានសរុបតាមសិស្សសម្រាប់លក្ខខណ្ឌដែលបានជ្រើសទេ។</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if(in_array($userRole ?? '', ['super-admin', 'admin'], true) && $classReportRows->isNotEmpty())
                        <div class="panel-head btn-space-top">សរុបថ្នាក់ដែលបានជ្រើស</div>
                        <div class="table-wrap">
                            <table class="attendance-report-table">
                                <thead>
                                    <tr>
                                        <th>ថ្នាក់</th>
                                        <th>ចំនួនសិស្ស</th>
                                        <th>ចំនួនមុខវិជ្ជា</th>
                                        <th>អវត្តមាន</th>
                                        <th>សុំច្បាប់</th>
                                        <th>កំណត់ត្រាសរុប</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($classReportRows as $row)
                                        <tr>
                                            <td>{{ $row['class_name'] }}</td>
                                            <td>{{ $row['students_count'] }}</td>
                                            <td>{{ $row['subjects_count'] }}</td>
                                            <td>{{ $row['absent_count'] }}</td>
                                            <td>{{ $row['leave_count'] }}</td>
                                            <td>{{ $row['total_records'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif
            </div>
        </section>

        <section class="panel">
            <div class="panel-head">កំណត់ត្រាវត្តមាន</div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>លេខ</th>
                        <th>កាលបរិច្ឆេទ</th>
                        <th>ថ្នាក់</th>
                        <th>មុខវិជ្ជា</th>
                        <th>សិស្ស</th>
                        <th>ម៉ោង</th>
                        <th>ស្ថានភាព</th>
                        <th>កំណត់ចំណាំ</th>
                        <th>សកម្មភាព</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td>{{ $item['id'] }}</td>
                            <td>{{ $item['date'] }}</td>
                            <td>{{ $item['class']['name'] ?? ($item['class_id'] ?? '-') }}</td>
                            <td>{{ $item['subject']['name'] ?? '-' }}</td>
                            <td>{{ $item['student']['user']['name'] ?? ($item['student_id'] ?? '-') }}</td>
                            <td>{{ $item['time_start'] }} - {{ $item['time_end'] ?? '-' }}</td>
                            <td>{{ $item['status'] === 'P' ? 'វត្តមាន' : ($item['status'] === 'A' ? 'អវត្តមាន' : 'សុំច្បាប់') }}</td>
                            <td>{{ $item['remarks'] ?? '-' }}</td>
                            <td>
                                @can('web-manage-attendance')
                                    <a href="{{ route('panel.attendance.edit', $item['id']) }}">កែប្រែ</a>

                                    <form action="{{ route('panel.attendance.destroy', $item['id']) }}" method="POST" class="inline-form" onsubmit="return confirm('លុបកំណត់ត្រាវត្តមាននេះមែនទេ?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit">លុប</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9">មិនមានទិន្នន័យទេ។</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var classSelect = document.getElementById('filter_class_id');
            var subjectSelect = document.getElementById('filter_subject_id');
            var periodTypeSelect = document.getElementById('attendance_period_type');
            var monthInput = document.getElementById('attendance_month');
            var yearInput = document.getElementById('attendance_year');
            var semesterSelect = document.getElementById('attendance_semester');
            var dateFromInput = document.getElementById('attendance_date_from');
            var dateToInput = document.getElementById('attendance_date_to');
            var subjectOptionsByClass = @json($subjectOptionsByClass);
            var selectedSubjectId = @json($selectedSubjectId);

            var syncSubjectOptions = function () {
                if (!classSelect || !subjectSelect) {
                    return;
                }

                var classId = classSelect.value;
                var rows = classId !== '' && subjectOptionsByClass[classId] ? subjectOptionsByClass[classId] : [];
                var placeholder = @json(in_array($userRole ?? '', ['teacher'], true) ? 'ជ្រើសមុខវិជ្ជាដែលបានបង្រៀន' : 'ជ្រើសមុខវិជ្ជា');
                var options = ['<option value="">' + placeholder + '</option>'];

                rows.forEach(function (row) {
                    var isSelected = String(row.id) === String(selectedSubjectId) || String(row.id) === String(subjectSelect.value);
                    options.push('<option value="' + row.id + '"' + (isSelected ? ' selected' : '') + '>' + row.label + '</option>');
                });

                subjectSelect.innerHTML = options.join('');
                subjectSelect.disabled = classId === '';
            };

            var syncPeriodFields = function () {
                if (!periodTypeSelect) {
                    return;
                }

                var mode = periodTypeSelect.value;
                if (monthInput) monthInput.style.display = mode === 'month' ? '' : 'none';
                if (yearInput) yearInput.style.display = (mode === 'semester' || mode === 'year') ? '' : 'none';
                if (semesterSelect) semesterSelect.style.display = mode === 'semester' ? '' : 'none';
                if (dateFromInput) dateFromInput.style.display = mode === 'range' ? '' : 'none';
                if (dateToInput) dateToInput.style.display = mode === 'range' ? '' : 'none';
            };

            if (classSelect && subjectSelect) {
                classSelect.addEventListener('change', function () {
                    selectedSubjectId = '';
                    syncSubjectOptions();
                });
                syncSubjectOptions();
            }

            if (periodTypeSelect) {
                periodTypeSelect.addEventListener('change', syncPeriodFields);
                syncPeriodFields();
            }
        });
    </script>
@endpush
