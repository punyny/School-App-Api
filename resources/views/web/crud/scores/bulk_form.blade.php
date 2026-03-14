@extends('web.layouts.app')

@section('content')
    <h1 class="title">តារាងបញ្ចូលពិន្ទុសិស្ស</h1>
    <p class="subtitle">ជ្រើសរើសថ្នាក់ → ឆ្នាំសិក្សា → ប្រភេទពិន្ទុ (ប្រចាំខែ/ឆមាស/ប្រចាំឆ្នាំ) បន្ទាប់មកបញ្ចូលពិន្ទុរបស់សិស្សម្នាក់ៗក្នុងតារាង។</p>

    <div class="nav">
        <a href="{{ route('panel.scores.index') }}">Back to list</a>
    </div>

    @if (session('success'))
        <p class="flash-success">{{ session('success') }}</p>
    @endif

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    @php
        $selectedClassId = (string) ($filters['class_id'] ?? '');
        $selectedType = (string) ($filters['assessment_type'] ?? 'monthly');
        $selectedMonth = isset($filters['month']) ? (string) $filters['month'] : '';
        $selectedSemester = isset($filters['semester']) ? (string) $filters['semester'] : '';
        $selectedAcademicYear = (string) ($filters['academic_year'] ?? '');
        $khmerMonths = \App\Support\KhmerMonth::options();
        $classSelectOptions = collect($classOptions ?? []);

        if ($selectedClassId !== '' && ! $classSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedClassId)) {
            $classSelectOptions = $classSelectOptions->prepend([
                'id' => (int) $selectedClassId,
                'label' => 'Class ID: '.$selectedClassId,
            ]);
        }
    @endphp

    <form method="GET" action="{{ route('panel.scores.create') }}" class="panel panel-form panel-spaced">
        <div class="form-grid">
            <div>
                <input type="text" class="searchable-select-search" placeholder="Search class..." data-select-search-for="bulk_class_id">
                <select id="bulk_class_id" name="class_id" required>
                    <option value="">ជ្រើសថ្នាក់</option>
                    @foreach($classSelectOptions as $option)
                        <option value="{{ $option['id'] }}" {{ $selectedClassId === (string) $option['id'] ? 'selected' : '' }}>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <input id="bulk_academic_year" type="text" name="academic_year" placeholder="Academic Year (2025-2026)" value="{{ $selectedAcademicYear }}" required>

            <select id="bulk_assessment_type" name="assessment_type" required>
                <option value="monthly" {{ $selectedType === 'monthly' ? 'selected' : '' }}>ប្រចាំខែ</option>
                <option value="semester" {{ $selectedType === 'semester' ? 'selected' : '' }}>ឆមាស</option>
                <option value="yearly" {{ $selectedType === 'yearly' ? 'selected' : '' }}>ប្រចាំឆ្នាំ</option>
            </select>

            <select id="bulk_month" name="month">
                <option value="">ជ្រើសរើសខែ</option>
                @foreach($khmerMonths as $monthNumber => $monthName)
                    <option value="{{ $monthNumber }}" {{ $selectedMonth === (string) $monthNumber ? 'selected' : '' }}>
                        {{ $monthName }}
                    </option>
                @endforeach
            </select>

            <select id="bulk_semester" name="semester">
                <option value="">ជ្រើសរើសឆមាស</option>
                <option value="1" {{ $selectedSemester === '1' ? 'selected' : '' }}>ឆមាសទី 1</option>
                <option value="2" {{ $selectedSemester === '2' ? 'selected' : '' }}>ឆមាសទី 2</option>
            </select>
        </div>
        <button type="submit" class="btn-space-top">ស្វែងរកតារាង</button>
    </form>

    @if($canInput)
        <form method="POST" action="{{ route('panel.scores.store') }}" class="panel panel-spaced">
            @csrf
            <input type="hidden" name="bulk_mode" value="1">
            <input type="hidden" name="class_id" value="{{ $selectedClassId }}">
            <input type="hidden" name="assessment_type" value="{{ $selectedType }}">
            <input type="hidden" name="month" value="{{ $selectedMonth }}">
            <input type="hidden" name="semester" value="{{ $selectedSemester }}">
            <input type="hidden" name="academic_year" value="{{ $selectedAcademicYear }}">

            <div class="panel-head">តារាងបញ្ចូលពិន្ទុ</div>

            <div class="score-matrix-wrap">
                <table class="score-matrix">
                    <thead>
                        <tr>
                            <th class="score-matrix-student">មុខវិជ្ជា</th>
                            @foreach($students as $student)
                                <th class="score-matrix-subject">{{ $student['name'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($subjects as $subject)
                            <tr>
                                <td class="score-matrix-student"><strong>{{ $subject['name'] }}</strong></td>
                                @foreach($students as $student)
                                    @php
                                        $studentId = (int) $student['id'];
                                        $subjectId = (int) $subject['id'];
                                        $existingValue = old("bulk_marks.{$studentId}.{$subjectId}", $scoreMatrix[$studentId][$subjectId] ?? '');
                                    @endphp
                                    <td>
                                        <input
                                            class="score-matrix-input"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="1000"
                                            name="bulk_marks[{{ $studentId }}][{{ $subjectId }}]"
                                            data-student-id="{{ $studentId }}"
                                            data-subject-id="{{ $subjectId }}"
                                            value="{{ $existingValue }}"
                                            placeholder="0-1000"
                                        >
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="panel panel-form panel-spaced" style="margin-top:14px;">
                <div class="panel-head">បង្ហាញលទ្ធផលការសិក្សា និង របាយការសិក្សា</div>
                <table>
                    <thead>
                        <tr>
                            <th>ឈ្មោះសិស្ស</th>
                            <th>ចំនួនមុខវិជ្ជាបានបញ្ចូល</th>
                            <th>មធ្យមភាគ</th>
                            <th>និទ្ទេស</th>
                            <th>របាយការណ៍</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($students as $student)
                            <tr data-result-row data-student-id="{{ (int) $student['id'] }}">
                                <td>{{ $student['name'] }}</td>
                                <td data-result-count>0</td>
                                <td data-result-avg>-</td>
                                <td data-result-grade>-</td>
                                <td data-result-report>មិនទាន់មាន</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="subtitle" style="margin-top:8px;">តារាងនេះនឹង Update ស្វ័យប្រវត្តិពេលអ្នកបញ្ចូលពិន្ទុក្នុងតារាងខាងលើ។</p>
            </div>

            <button type="submit" class="btn-space-top">រក្សាទុកពិន្ទុទាំងអស់</button>
        </form>
    @else
        <section class="panel panel-form panel-spaced">
            <p class="subtitle" style="margin:0;">
                សូមជ្រើសរើសថ្នាក់ និង ឆ្នាំសិក្សា និង ប្រភេទពិន្ទុ។
                @if($selectedType === 'monthly')
                    សម្រាប់ប្រចាំខែ ត្រូវជ្រើសរើស <strong>ខែ</strong> មុន។
                @elseif($selectedType === 'semester')
                    សម្រាប់ឆមាស ត្រូវជ្រើសរើស <strong>ឆមាស</strong> មុន។
                @else
                    សម្រាប់ប្រចាំឆ្នាំ ត្រូវបំពេញ <strong>ឆ្នាំសិក្សា</strong> មុន។
                @endif
            </p>
            @if(empty($students))
                <p class="subtitle" style="margin:8px 0 0;">មិនមានសិស្សក្នុងថ្នាក់នេះទេ។ សូម Assign Students ជាមុនសិន នៅក្នុង Class Setup។</p>
            @endif
            @if(empty($subjects))
                <p class="subtitle" style="margin:8px 0 0;">មិនមានមុខវិជ្ជា Available សម្រាប់ថ្នាក់នេះទេ។ សូមពិនិត្យ Subject/Teacher Assignment ម្តងទៀត។</p>
            @endif
        </section>
    @endif
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var typeSelect = document.getElementById('bulk_assessment_type');
            var monthSelect = document.getElementById('bulk_month');
            var semesterSelect = document.getElementById('bulk_semester');
            var scoreInputs = Array.prototype.slice.call(document.querySelectorAll('.score-matrix-input'));
            var resultRows = Array.prototype.slice.call(document.querySelectorAll('[data-result-row]'));

            if (!typeSelect || !monthSelect || !semesterSelect) {
                return;
            }

            var togglePeriodFields = function () {
                var type = typeSelect.value || 'monthly';
                var isMonthly = type === 'monthly';
                var isSemester = type === 'semester';
                var isYearly = type === 'yearly';

                monthSelect.required = isMonthly;
                semesterSelect.required = isSemester;
                monthSelect.disabled = !isMonthly;
                semesterSelect.disabled = !isSemester;

                monthSelect.style.display = isMonthly ? '' : 'none';
                semesterSelect.style.display = isSemester ? '' : 'none';

                if (isMonthly && semesterSelect.value !== '') {
                    semesterSelect.value = '';
                }
                if (isSemester && monthSelect.value !== '') {
                    monthSelect.value = '';
                }
                if (isYearly) {
                    monthSelect.value = '';
                    semesterSelect.value = '';
                }
            };

            typeSelect.addEventListener('change', togglePeriodFields);
            togglePeriodFields();

            var gradeFromAverage = function (average) {
                if (average >= 90) return 'A';
                if (average >= 80) return 'B';
                if (average >= 70) return 'C';
                if (average >= 60) return 'D';
                if (average >= 50) return 'E';
                return 'F';
            };

            var refreshStudyResultTable = function () {
                var summaryByStudent = {};

                scoreInputs.forEach(function (input) {
                    var studentId = String(input.getAttribute('data-student-id') || '');
                    if (studentId === '') {
                        return;
                    }

                    if (!summaryByStudent[studentId]) {
                        summaryByStudent[studentId] = { count: 0, total: 0 };
                    }

                    var raw = String(input.value || '').trim();
                    if (raw === '') {
                        return;
                    }

                    var numericValue = parseFloat(raw);
                    if (isNaN(numericValue)) {
                        return;
                    }

                    summaryByStudent[studentId].count += 1;
                    summaryByStudent[studentId].total += numericValue;
                });

                resultRows.forEach(function (row) {
                    var studentId = String(row.getAttribute('data-student-id') || '');
                    var summary = summaryByStudent[studentId] || { count: 0, total: 0 };
                    var countCell = row.querySelector('[data-result-count]');
                    var avgCell = row.querySelector('[data-result-avg]');
                    var gradeCell = row.querySelector('[data-result-grade]');
                    var reportCell = row.querySelector('[data-result-report]');

                    var hasScores = summary.count > 0;
                    var average = hasScores ? (summary.total / summary.count) : 0;

                    if (countCell) countCell.textContent = String(summary.count);
                    if (avgCell) avgCell.textContent = hasScores ? average.toFixed(2) : '-';
                    if (gradeCell) gradeCell.textContent = hasScores ? gradeFromAverage(average) : '-';
                    if (reportCell) reportCell.textContent = hasScores ? 'មានទិន្នន័យ' : 'មិនទាន់មាន';
                });
            };

            scoreInputs.forEach(function (input) {
                input.addEventListener('input', refreshStudyResultTable);
            });
            refreshStudyResultTable();
        });
    </script>
@endpush
