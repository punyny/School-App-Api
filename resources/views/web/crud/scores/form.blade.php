@extends('web.layouts.app')

@section('content')
    <h1 class="title">{{ $mode === 'create' ? 'Create Score' : 'Edit Score' }}</h1>
    <p class="subtitle">Create Score: How to Input Score (ប្រចាំខែ/ឆមាស/ប្រចាំឆ្នាំ) តាមទម្រង់សាលា។</p>

    <div class="nav">
        <a href="{{ route('panel.scores.index') }}">Back to list</a>
    </div>

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    <section class="panel panel-form panel-spaced">
        <div class="panel-head">How To Input Score</div>
        <ol style="margin:0; padding-left:20px; display:grid; gap:8px;">
            <li>ជ្រើសរើសថ្នាក់ (Class) និងមុខវិជ្ជា (Subject) មុនគេ</li>
            <li>ជ្រើសសិស្ស (Student) ដែលស្ថិតក្នុងថ្នាក់នោះ</li>
            <li>ជ្រើសប្រភេទពិន្ទុ (Assessment Type): Monthly / Semester / Yearly</li>
            <li>បំពេញពេលវេលាតាមប្រភេទ៖ Monthly = Month + Academic Year, Semester = Semester + Academic Year, Yearly = Academic Year</li>
            <li>បញ្ចូល Exam Score និង Total Score (0 - 1000)</li>
            <li>Grade អាចទុកទទេបាន (Optional); Rank In Class គួរទុកឲ្យប្រព័ន្ធគណនាស្វ័យប្រវត្តិ</li>
            <li>ចុច Create/Update ដើម្បីរក្សាទុកទិន្នន័យ</li>
        </ol>
    </section>

    <form method="POST" action="{{ $mode === 'create' ? route('panel.scores.store') : route('panel.scores.update', $item['id']) }}" class="panel panel-form">
        @csrf
        @if($mode === 'edit')
            @method('PUT')
        @endif

        @php
            $selectedStudentId = (string) old('student_id', $item['student_id'] ?? '');
            $selectedClassId = (string) old('class_id', $item['class_id'] ?? '');
            $selectedSubjectId = (string) old('subject_id', $item['subject_id'] ?? '');
            $studentSelectOptions = collect($studentOptions ?? []);
            $classSelectOptions = collect($classOptions ?? []);
            $subjectSelectOptions = collect($subjectOptions ?? []);
            $role = (string) ($userRole ?? auth()->user()->role ?? '');
            $teacherMap = collect($teacherClassSubjectMap ?? [])
                ->mapWithKeys(function ($subjectIds, $classId): array {
                    $normalizedSubjectIds = collect($subjectIds ?? [])
                        ->map(fn ($id): int => (int) $id)
                        ->filter(fn (int $id): bool => $id > 0)
                        ->unique()
                        ->values()
                        ->all();

                    return [(int) $classId => $normalizedSubjectIds];
                })
                ->all();
            $subjectAllowedClasses = [];
            $khmerMonths = \App\Support\KhmerMonth::options();

            foreach ($teacherMap as $classId => $subjectIds) {
                foreach ($subjectIds as $subjectId) {
                    $subjectAllowedClasses[$subjectId] = $subjectAllowedClasses[$subjectId] ?? [];
                    $subjectAllowedClasses[$subjectId][] = (int) $classId;
                }
            }

            if ($selectedStudentId !== '' && ! $studentSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedStudentId)) {
                $studentSelectOptions = $studentSelectOptions->prepend([
                    'id' => (int) $selectedStudentId,
                    'label' => 'Student ID: '.$selectedStudentId,
                    'class_id' => $selectedClassId !== '' ? (int) $selectedClassId : null,
                ]);
            }

            if ($selectedClassId !== '' && ! $classSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedClassId)) {
                $classSelectOptions = $classSelectOptions->prepend([
                    'id' => (int) $selectedClassId,
                    'label' => 'Class ID: '.$selectedClassId,
                ]);
            }

            if ($selectedSubjectId !== '' && ! $subjectSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedSubjectId)) {
                $subjectSelectOptions = $subjectSelectOptions->prepend([
                    'id' => (int) $selectedSubjectId,
                    'label' => 'Subject ID: '.$selectedSubjectId,
                ]);
            }
        @endphp

        <label>Class (ថ្នាក់)</label>
        <div class="searchable-select-wrap">
            <p>រើសរើសថ្នាក់ (Class) និងមុខវិជ្ជា (Subject) មុនគេ</p>
            <input type="text" class="searchable-select-search" placeholder="Search class..." data-select-search-for="class_id">
            <select id="class_id" name="class_id" required>
                <option value="">Select class</option>
                @foreach($classSelectOptions as $option)
                    <option value="{{ $option['id'] }}" {{ $selectedClassId === (string) $option['id'] ? 'selected' : '' }}>
                        {{ $option['label'] }}
                    </option>
                @endforeach
            </select>
        </div>

        <label>Subject (មុខវិជ្ជា)</label>
        <div class="searchable-select-wrap">
            <input type="text" class="searchable-select-search" placeholder="Search subject..." data-select-search-for="subject_id">
            <select id="subject_id" name="subject_id" required>
                <option value="">Select subject</option>
                @foreach($subjectSelectOptions as $option)
                    <option
                        value="{{ $option['id'] }}"
                        data-allowed-classes="{{ collect($subjectAllowedClasses[(int) $option['id']] ?? [])->implode(',') }}"
                        {{ $selectedSubjectId === (string) $option['id'] ? 'selected' : '' }}
                    >
                        {{ $option['label'] }}
                    </option>
                @endforeach
            </select>
        </div>

        <label>Student (ឈ្មោះសិស្ស)</label>
        <div class="searchable-select-wrap">
            <input type="text" class="searchable-select-search" placeholder="Search student..." data-select-search-for="student_id">
            <select id="student_id" name="student_id" required>
                <option value="">Select student</option>
                @foreach($studentSelectOptions as $option)
                    <option
                        value="{{ $option['id'] }}"
                        data-class-id="{{ (int) ($option['class_id'] ?? 0) }}"
                        {{ $selectedStudentId === (string) $option['id'] ? 'selected' : '' }}
                    >
                        {{ $option['label'] }}
                    </option>
                @endforeach
            </select>
        </div>

        <label>Assessment Type (ប្រភេទពិន្ទុ)</label>
        @php $assessmentType = old('assessment_type', $item['assessment_type'] ?? 'monthly'); @endphp
        <select id="assessment_type" name="assessment_type">
            <option value="monthly" {{ $assessmentType === 'monthly' ? 'selected' : '' }}>Monthly (ប្រចាំខែ)</option>
            <option value="semester" {{ $assessmentType === 'semester' ? 'selected' : '' }}>Semester (ឆមាស)</option>
            <option value="yearly" {{ $assessmentType === 'yearly' ? 'selected' : '' }}>Yearly (ប្រចាំឆ្នាំ)</option>
        </select>

        <div id="month_wrap">
            <label>Month (ខែ)</label>
            <select id="month" name="month">
                <option value="">Select month</option>
                @foreach($khmerMonths as $monthNumber => $monthName)
                    <option value="{{ $monthNumber }}" {{ (string) old('month', $item['month'] ?? '') === (string) $monthNumber ? 'selected' : '' }}>
                        {{ $monthName }}
                    </option>
                @endforeach
            </select>
        </div>

        <div id="semester_wrap">
            <label>Semester (ឆមាស 1-2)</label>
            <input id="semester" type="number" min="1" max="2" name="semester" value="{{ old('semester', $item['semester'] ?? '') }}">
        </div>

        <div id="academic_year_wrap">
            <label>Academic Year (ឆ្នាំសិក្សា, ឧ. 2025-2026)</label>
            <input id="academic_year" type="text" name="academic_year" value="{{ old('academic_year', $item['academic_year'] ?? '') }}">
        </div>

        <label>Exam Score (ពិន្ទុប្រឡង)</label>
        <input id="exam_score" type="number" step="0.01" min="0" max="1000" name="exam_score" value="{{ old('exam_score', $item['exam_score'] ?? '') }}" required>

        <label>Total Score (ពិន្ទុសរុប)</label>
        <input id="total_score" type="number" step="0.01" min="0" max="1000" name="total_score" value="{{ old('total_score', $item['total_score'] ?? '') }}" required>

        <label>Quarter</label>
        <input type="number" min="1" max="4" name="quarter" value="{{ old('quarter', $item['quarter'] ?? '') }}">

        <label>Period</label>
        <input type="text" name="period" value="{{ old('period', $item['period'] ?? '') }}">

        <label>Grade (optional)</label>
        <input type="text" name="grade" value="{{ old('grade', $item['grade'] ?? '') }}">

        <label>Rank In Class (ចំណាត់ថ្នាក់)</label>
        <input id="rank_in_class" type="number" min="1" name="rank_in_class" value="{{ old('rank_in_class', $item['rank_in_class'] ?? '') }}">

        <div class="panel panel-spaced" style="margin-top:14px;">
            <div class="panel-head">Preview (ទម្រង់សរសេរពិន្ទុ)</div>
            <table>
                <thead>
                    <tr>
                        <th>ឈ្មោះសិស្ស</th>
                        <th>ពិន្ទុប្រឡង</th>
                        <th>ពិន្ទុសរុប</th>
                        <th>ល.រ</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td id="preview_student">-</td>
                        <td id="preview_exam">-</td>
                        <td id="preview_total">-</td>
                        <td id="preview_rank">-</td>
                    </tr>
                </tbody>
            </table>
            <p class="subtitle" style="margin-top:8px;">
                ប្រភេទ: <strong id="preview_type">-</strong> |
                ពេលវេលា: <strong id="preview_period">-</strong>
            </p>
        </div>

        <button type="submit" class="btn-space-top">{{ $mode === 'create' ? 'Create' : 'Update' }}</button>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var role = @json($role);
            var classSelect = document.getElementById('class_id');
            var studentSelect = document.getElementById('student_id');
            var subjectSelect = document.getElementById('subject_id');
            var assessmentTypeSelect = document.getElementById('assessment_type');
            var monthInput = document.getElementById('month');
            var semesterInput = document.getElementById('semester');
            var academicYearInput = document.getElementById('academic_year');
            var monthWrap = document.getElementById('month_wrap');
            var semesterWrap = document.getElementById('semester_wrap');
            var academicYearWrap = document.getElementById('academic_year_wrap');
            var examScoreInput = document.getElementById('exam_score');
            var totalScoreInput = document.getElementById('total_score');
            var rankInput = document.getElementById('rank_in_class');
            var khmerMonths = @json(\App\Support\KhmerMonth::options());

            var previewStudent = document.getElementById('preview_student');
            var previewExam = document.getElementById('preview_exam');
            var previewTotal = document.getElementById('preview_total');
            var previewRank = document.getElementById('preview_rank');
            var previewType = document.getElementById('preview_type');
            var previewPeriod = document.getElementById('preview_period');

            if (!classSelect || !studentSelect || !subjectSelect || !assessmentTypeSelect) {
                return;
            }

            var filterStudents = function () {
                var selectedClass = classSelect.value;
                var studentOptions = Array.prototype.slice.call(studentSelect.options);

                studentOptions.forEach(function (option) {
                    if (option.value === '') {
                        option.hidden = false;
                        return;
                    }

                    var optionClassId = option.getAttribute('data-class-id') || '';
                    option.hidden = selectedClass !== '' && optionClassId !== '' && optionClassId !== selectedClass;
                });

                if (studentSelect.selectedOptions.length > 0 && studentSelect.selectedOptions[0].hidden) {
                    studentSelect.value = '';
                }
            };

            var filterSubjectsForTeacher = function () {
                var selectedClass = classSelect.value;
                var subjectOptions = Array.prototype.slice.call(subjectSelect.options);
                var shouldRestrictByAssignment = role === 'teacher';

                subjectOptions.forEach(function (option) {
                    if (option.value === '') {
                        option.hidden = false;
                        return;
                    }

                    if (!shouldRestrictByAssignment || selectedClass === '') {
                        option.hidden = false;
                        return;
                    }

                    var allowedClassesRaw = option.getAttribute('data-allowed-classes') || '';
                    var allowedClasses = allowedClassesRaw
                        .split(',')
                        .map(function (value) { return value.trim(); })
                        .filter(function (value) { return value !== ''; });

                    option.hidden = allowedClasses.length > 0 && allowedClasses.indexOf(selectedClass) === -1;
                });

                if (subjectSelect.selectedOptions.length > 0 && subjectSelect.selectedOptions[0].hidden) {
                    subjectSelect.value = '';
                }
            };

            var typeLabel = function (type) {
                if (type === 'semester') return 'Semester (ឆមាស)';
                if (type === 'yearly') return 'Yearly (ប្រចាំឆ្នាំ)';
                return 'Monthly (ប្រចាំខែ)';
            };

            var toggleAssessmentFields = function () {
                var type = assessmentTypeSelect.value || 'monthly';
                monthWrap.style.display = type === 'monthly' ? '' : 'none';
                semesterWrap.style.display = type === 'semester' ? '' : 'none';
                academicYearWrap.style.display = type === 'yearly' ? '' : 'none';

                monthInput.required = type === 'monthly';
                semesterInput.required = type === 'semester';
                academicYearInput.required = type === 'yearly';
            };

            var updatePreview = function () {
                var selectedStudentOption = studentSelect.options[studentSelect.selectedIndex];
                previewStudent.textContent = selectedStudentOption && selectedStudentOption.value !== ''
                    ? selectedStudentOption.text
                    : '-';
                previewExam.textContent = examScoreInput.value || '-';
                previewTotal.textContent = totalScoreInput.value || '-';
                previewRank.textContent = rankInput.value || '-';

                var type = assessmentTypeSelect.value || 'monthly';
                previewType.textContent = typeLabel(type);

                if (type === 'monthly') {
                    previewPeriod.textContent = monthInput.value !== ''
                        ? (khmerMonths[monthInput.value] || ('ខែទី ' + monthInput.value))
                        : '-';
                } else if (type === 'semester') {
                    previewPeriod.textContent = semesterInput.value !== '' ? ('ឆមាសទី ' + semesterInput.value) : '-';
                } else {
                    previewPeriod.textContent = academicYearInput.value || '-';
                }
            };

            classSelect.addEventListener('change', function () {
                filterStudents();
                filterSubjectsForTeacher();
                updatePreview();
            });
            studentSelect.addEventListener('change', updatePreview);
            assessmentTypeSelect.addEventListener('change', function () {
                toggleAssessmentFields();
                updatePreview();
            });
            monthInput.addEventListener('change', updatePreview);
            semesterInput.addEventListener('input', updatePreview);
            academicYearInput.addEventListener('input', updatePreview);
            examScoreInput.addEventListener('input', updatePreview);
            totalScoreInput.addEventListener('input', updatePreview);
            rankInput.addEventListener('input', updatePreview);

            filterStudents();
            filterSubjectsForTeacher();
            toggleAssessmentFields();
            updatePreview();
        });
    </script>
@endpush
