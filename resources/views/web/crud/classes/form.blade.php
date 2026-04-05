@extends('web.layouts.app')

@section('content')
    @php
        $selectedSchoolId = (string) old('school_id', $selectedSchoolId ?? ($item['school_id'] ?? ''));
        $classNameValue = old('name', $item['name'] ?? '');
        $selectedGrade = (string) old('grade_level', $item['grade_level'] ?? '');
        $roomValue = old('room', $item['room'] ?? '');
        $studyDaysSelected = collect(old('study_days', $item['study_days'] ?? []))
            ->map(fn ($day) => strtolower((string) $day))
            ->filter(fn ($day) => $day !== '')
            ->values()
            ->all();
        $studyTimeStartValue = old('study_time_start', isset($item['study_time_start']) ? substr((string) $item['study_time_start'], 0, 5) : '');
        $studyTimeEndValue = old('study_time_end', isset($item['study_time_end']) ? substr((string) $item['study_time_end'], 0, 5) : '');

        $teacherOptions = collect($teacherOptions ?? [])->filter(fn ($row) => is_array($row))->values();
        $subjectOptions = collect($subjectOptions ?? [])->filter(fn ($row) => is_array($row))->values();
        $studentOptions = collect($studentOptions ?? [])->filter(fn ($row) => is_array($row))->values();

        $oldAssignments = old('teacher_assignments');
        if (is_array($oldAssignments)) {
            $assignmentRows = collect($oldAssignments)->map(fn ($row) => [
                'teacher_id' => (string) ($row['teacher_id'] ?? ''),
                'subject_id' => (string) ($row['subject_id'] ?? ''),
            ])->values();
        } elseif ($mode === 'edit') {
            $assignmentRows = collect($item['teachers'] ?? [])->map(fn ($teacher) => [
                'teacher_id' => (string) ($teacher['id'] ?? ''),
                'subject_id' => (string) data_get($teacher, 'pivot.subject_id', ''),
            ])->values();
        } else {
            $assignmentRows = collect();
        }
        if ($assignmentRows->isEmpty()) {
            $assignmentRows = collect([
                ['teacher_id' => '', 'subject_id' => ''],
            ]);
        }

        $oldRows = old('timetable_rows');
        if (is_array($oldRows)) {
            $timetableRows = collect($oldRows)->map(fn ($row) => [
                'day_of_week' => (string) ($row['day_of_week'] ?? ''),
                'subject_id' => (string) ($row['subject_id'] ?? ''),
                'teacher_id' => (string) ($row['teacher_id'] ?? ''),
                'time_start' => (string) ($row['time_start'] ?? ''),
                'time_end' => (string) ($row['time_end'] ?? ''),
            ])->values();
        } elseif ($mode === 'edit') {
            $timetableRows = collect($item['timetables'] ?? [])->map(fn ($row) => [
                'day_of_week' => (string) ($row['day_of_week'] ?? ''),
                'subject_id' => (string) ($row['subject_id'] ?? ''),
                'teacher_id' => (string) ($row['teacher_id'] ?? ''),
                'time_start' => substr((string) ($row['time_start'] ?? ''), 0, 5),
                'time_end' => substr((string) ($row['time_end'] ?? ''), 0, 5),
            ])->filter(fn ($row) => in_array($row['day_of_week'], ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'], true))->values();
        } else {
            $timetableRows = collect();
        }
        if ($timetableRows->isEmpty()) {
            $timetableRows = collect([
                ['day_of_week' => '', 'subject_id' => '', 'teacher_id' => '', 'time_start' => '', 'time_end' => ''],
            ]);
        }

        $selectedStudentIds = collect(old('student_ids', collect($item['students'] ?? [])->pluck('id')->all()))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();
    @endphp

    <style>
        .class-form-grid {
            display: grid;
            gap: 14px;
        }

        .class-form-card {
            border: 1px solid #dce8c7;
            border-radius: 16px;
            background: #fbfff7;
            padding: 14px;
        }

        .class-form-card h3 {
            margin: 0 0 8px;
            font-size: 15px;
        }

        .class-form-muted {
            margin: 0 0 10px;
            font-size: 12px;
            color: #5f6f52;
        }

        .class-basic-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .class-inline-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .class-inline-actions button {
            margin: 0;
        }

        .class-table-wrap {
            overflow: auto;
            border: 1px solid #dde9ca;
            border-radius: 12px;
            background: #fff;
        }

        .class-table-wrap table {
            width: 100%;
            margin: 0;
        }

        .class-table-wrap td,
        .class-table-wrap th {
            vertical-align: middle;
            white-space: nowrap;
        }

        .class-table-wrap select,
        .class-table-wrap input {
            margin: 0;
            min-width: 150px;
        }

        .student-box {
            border: 1px solid #dce8c7;
            border-radius: 14px;
            background: #fff;
            padding: 10px;
        }

        .student-tools {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto auto;
            gap: 8px;
            margin-bottom: 10px;
        }

        .student-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
            max-height: 320px;
            overflow: auto;
            padding-right: 4px;
        }

        .student-item {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: 8px;
            align-items: center;
            border: 1px solid #dde9ca;
            border-radius: 12px;
            padding: 10px;
            background: #fbfff7;
        }

        .student-item input[type="checkbox"] {
            margin: 0;
            width: 18px;
            height: 18px;
        }

        .student-meta {
            font-size: 11px;
            color: #5f6f52;
            margin-top: 4px;
        }

        .school-hint {
            margin-top: 8px;
            padding: 8px 10px;
            border: 1px dashed #d8e6bd;
            border-radius: 10px;
            font-size: 12px;
            color: #5f6f52;
            background: #f7ffe8;
        }

        @media (max-width: 980px) {
            .class-basic-grid,
            .student-tools,
            .student-list {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <h1 class="title">{{ $mode === 'create' ? 'Create Class' : 'Edit Class' }}</h1>
    <p class="subtitle">Setup class name/grade/room, then assign teachers, subjects, students, and Monday-Sunday routine in one form.</p>

    <div class="nav">
        <a href="{{ route('panel.classes.index') }}">Back to list</a>
    </div>

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ $mode === 'create' ? route('panel.classes.store') : route('panel.classes.update', $item['id']) }}" class="panel panel-form">
        @csrf
        @if($mode === 'edit')
            @method('PUT')
        @endif

        <div class="class-form-grid">
            <section class="class-form-card">
                <h3>1) Basic Class Information</h3>
                <p class="class-form-muted">បង្កើតឈ្មោះថ្នាក់ + កម្រិតថ្នាក់ + បន្ទប់។</p>

                <div class="class-basic-grid">
                    @if($userRole === 'super-admin')
                        <div>
                            <label>School</label>
                            <select name="school_id" id="school_id" required>
                                <option value="">Select school</option>
                                @foreach(($schoolOptions ?? []) as $school)
                                    <option value="{{ $school['id'] }}" {{ (string) $school['id'] === $selectedSchoolId ? 'selected' : '' }}>
                                        {{ $school['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div>
                        <label>Class Name (Example: 12A or ១២ក)</label>
                        <input type="text" name="name" value="{{ $classNameValue }}" required>
                    </div>

                    <div>
                        <label>Grade Level</label>
                        <select name="grade_level">
                            <option value="">Select grade</option>
                            @foreach(($gradeOptions ?? []) as $grade)
                                <option value="{{ $grade }}" {{ $selectedGrade === (string) $grade ? 'selected' : '' }}>
                                    Grade {{ $grade }} / ថ្នាក់ទី{{ $grade }}
                                </option>
                            @endforeach
                            @if($selectedGrade !== '' && ! in_array($selectedGrade, ($gradeOptions ?? []), true))
                                <option value="{{ $selectedGrade }}" selected>{{ $selectedGrade }}</option>
                            @endif
                        </select>
                    </div>

                    <div>
                        <label>Room</label>
                        <input type="text" name="room" value="{{ $roomValue }}" placeholder="Example: A-01">
                    </div>
                </div>

                <div class="class-basic-grid" style="margin-top:12px;">
                    <div>
                        <label>Study Days (Monday to Sunday)</label>
                        <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:6px;margin-top:6px;">
                            @foreach(($weekdayOptions ?? ['monday','tuesday','wednesday','thursday','friday','saturday','sunday']) as $day)
                                <label style="display:flex;align-items:center;gap:6px;font-size:12px;">
                                    <input type="checkbox" name="study_days[]" value="{{ $day }}" {{ in_array($day, $studyDaysSelected, true) ? 'checked' : '' }}>
                                    <span>{{ ucfirst($day) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label>Study Hours (Optional)</label>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:6px;">
                            <input type="time" name="study_time_start" value="{{ $studyTimeStartValue }}" placeholder="Start">
                            <input type="time" name="study_time_end" value="{{ $studyTimeEndValue }}" placeholder="End">
                        </div>
                    </div>
                </div>
            </section>

            <section class="class-form-card">
                <h3>2) Teacher + Subject Assignment</h3>
                <p class="class-form-muted">ជ្រើសរើសគ្រូ និងមុខវិជ្ជា ដែលបង្រៀនក្នុងថ្នាក់នេះ។</p>

                <div class="class-inline-actions">
                    <button type="button" id="add-assignment-row">+ Add teacher assignment</button>
                </div>

                <div class="class-table-wrap" style="margin-top:10px;">
                    <table>
                        <thead>
                            <tr>
                                <th>Teacher</th>
                                <th>Subject</th>
                                <th>Remove</th>
                            </tr>
                        </thead>
                        <tbody id="assignment-body">
                            @foreach($assignmentRows as $index => $row)
                                <tr data-assignment-row>
                                    <td>
                                        <select name="teacher_assignments[{{ $index }}][teacher_id]" data-school-select>
                                            <option value="">Select teacher</option>
                                            @foreach($teacherOptions as $teacher)
                                                <option
                                                    value="{{ $teacher['id'] }}"
                                                    data-school-id="{{ $teacher['school_id'] ?? '' }}"
                                                    {{ (string) ($teacher['id'] ?? '') === (string) ($row['teacher_id'] ?? '') ? 'selected' : '' }}
                                                >
                                                    {{ $teacher['label'] ?? ('Teacher #'.($teacher['id'] ?? '')) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="teacher_assignments[{{ $index }}][subject_id]" data-school-select>
                                            <option value="">Select subject</option>
                                            @foreach($subjectOptions as $subject)
                                                <option
                                                    value="{{ $subject['id'] }}"
                                                    data-school-id="{{ $subject['school_id'] ?? '' }}"
                                                    {{ (string) ($subject['id'] ?? '') === (string) ($row['subject_id'] ?? '') ? 'selected' : '' }}
                                                >
                                                    {{ $subject['label'] ?? ('Subject #'.($subject['id'] ?? '')) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <button type="button" class="remove-assignment-row">Remove</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="class-form-card">
                <h3>3) Assign Students</h3>
                <p class="class-form-muted">ជ្រើសសិស្សដែលបានបង្កើតរួច ដើម្បីដាក់ចូលថ្នាក់នេះ.</p>

                <div class="student-box">
                    <div class="student-tools">
                        <input type="text" id="student-search" placeholder="Search student by name / Khmer name / code / phone">
                        <button type="button" id="select-visible-students">Select visible</button>
                        <button type="button" id="clear-visible-students">Clear visible</button>
                    </div>

                    <div class="student-list" id="student-list">
                        @forelse($studentOptions as $student)
                            @php
                                $sid = (int) ($student['id'] ?? 0);
                                $className = (string) ($student['class_name'] ?? '');
                                $searchIndex = strtolower(trim(implode(' ', [
                                    (string) ($student['name'] ?? ''),
                                    (string) ($student['khmer_name'] ?? ''),
                                    (string) ($student['student_code'] ?? ''),
                                    (string) ($student['phone'] ?? ''),
                                    (string) ($student['email'] ?? ''),
                                    $className,
                                ])));
                            @endphp
                            <label class="student-item" data-student-item data-search="{{ $searchIndex }}" data-school-id="{{ $student['school_id'] ?? '' }}">
                                <input type="checkbox" name="student_ids[]" value="{{ $sid }}" {{ in_array($sid, $selectedStudentIds, true) ? 'checked' : '' }}>
                                <div>
                                    <strong>{{ $student['name'] ?? ('Student #'.$sid) }}</strong>
                                    @if(!empty($student['khmer_name']))
                                        <div class="student-meta">{{ $student['khmer_name'] }}</div>
                                    @endif
                                    <div class="student-meta">
                                        @if(!empty($student['student_code'])) ID: {{ $student['student_code'] }} @endif
                                        @if(!empty($student['grade'])) | Grade {{ $student['grade'] }} @endif
                                        @if($className !== '') | Current: {{ $className }} @endif
                                    </div>
                                </div>
                            </label>
                        @empty
                            <div class="student-meta">No students available in selected school.</div>
                        @endforelse
                    </div>

                    @if($userRole === 'super-admin')
                        <div class="school-hint" id="school-scope-hint">
                            Please select school first. Teacher/Subject/Student options will be limited to that school.
                        </div>
                    @endif
                </div>
            </section>

            <section class="class-form-card">
                <h3>4) Weekly Routine (Monday to Sunday)</h3>
                <p class="class-form-muted">បញ្ចូលម៉ោងរៀនប្រចាំសប្តាហ៍ (រួមទាំងអាទិត្យបើសាលាមានរៀន)។</p>

                <div class="class-inline-actions">
                    <button type="button" id="add-routine-row">+ Add routine row</button>
                </div>

                <div class="class-table-wrap" style="margin-top:10px;">
                    <table>
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Subject</th>
                                <th>Teacher</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Remove</th>
                            </tr>
                        </thead>
                        <tbody id="routine-body">
                            @foreach($timetableRows as $index => $row)
                                <tr data-routine-row>
                                    <td>
                                        <select name="timetable_rows[{{ $index }}][day_of_week]">
                                            <option value="">Select day</option>
                                            @foreach(($weekdayOptions ?? ['monday','tuesday','wednesday','thursday','friday','saturday','sunday']) as $day)
                                                <option value="{{ $day }}" {{ (string) $row['day_of_week'] === (string) $day ? 'selected' : '' }}>{{ ucfirst($day) }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="timetable_rows[{{ $index }}][subject_id]" data-school-select>
                                            <option value="">Select subject</option>
                                            @foreach($subjectOptions as $subject)
                                                <option
                                                    value="{{ $subject['id'] }}"
                                                    data-school-id="{{ $subject['school_id'] ?? '' }}"
                                                    {{ (string) ($subject['id'] ?? '') === (string) ($row['subject_id'] ?? '') ? 'selected' : '' }}
                                                >
                                                    {{ $subject['label'] ?? ('Subject #'.($subject['id'] ?? '')) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="timetable_rows[{{ $index }}][teacher_id]" data-school-select>
                                            <option value="">Select teacher</option>
                                            @foreach($teacherOptions as $teacher)
                                                <option
                                                    value="{{ $teacher['id'] }}"
                                                    data-school-id="{{ $teacher['school_id'] ?? '' }}"
                                                    {{ (string) ($teacher['id'] ?? '') === (string) ($row['teacher_id'] ?? '') ? 'selected' : '' }}
                                                >
                                                    {{ $teacher['label'] ?? ('Teacher #'.($teacher['id'] ?? '')) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="time" name="timetable_rows[{{ $index }}][time_start]" value="{{ $row['time_start'] }}">
                                    </td>
                                    <td>
                                        <input type="time" name="timetable_rows[{{ $index }}][time_end]" value="{{ $row['time_end'] }}">
                                    </td>
                                    <td>
                                        <button type="button" class="remove-routine-row">Remove</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <button type="submit" class="btn-space-top">{{ $mode === 'create' ? 'Create Class & Save Setup' : 'Update Class & Save Setup' }}</button>
    </form>

    <template id="assignment-row-template">
        <tr data-assignment-row>
            <td>
                <select data-name="teacher_assignments[__INDEX__][teacher_id]" data-school-select>
                    <option value="">Select teacher</option>
                    @foreach($teacherOptions as $teacher)
                        <option value="{{ $teacher['id'] }}" data-school-id="{{ $teacher['school_id'] ?? '' }}">
                            {{ $teacher['label'] ?? ('Teacher #'.($teacher['id'] ?? '')) }}
                        </option>
                    @endforeach
                </select>
            </td>
            <td>
                <select data-name="teacher_assignments[__INDEX__][subject_id]" data-school-select>
                    <option value="">Select subject</option>
                    @foreach($subjectOptions as $subject)
                        <option value="{{ $subject['id'] }}" data-school-id="{{ $subject['school_id'] ?? '' }}">
                            {{ $subject['label'] ?? ('Subject #'.($subject['id'] ?? '')) }}
                        </option>
                    @endforeach
                </select>
            </td>
            <td>
                <button type="button" class="remove-assignment-row">Remove</button>
            </td>
        </tr>
    </template>

    <template id="routine-row-template">
        <tr data-routine-row>
            <td>
                <select data-name="timetable_rows[__INDEX__][day_of_week]">
                    <option value="">Select day</option>
                    @foreach(($weekdayOptions ?? ['monday','tuesday','wednesday','thursday','friday','saturday','sunday']) as $day)
                        <option value="{{ $day }}">{{ ucfirst($day) }}</option>
                    @endforeach
                </select>
            </td>
            <td>
                <select data-name="timetable_rows[__INDEX__][subject_id]" data-school-select>
                    <option value="">Select subject</option>
                    @foreach($subjectOptions as $subject)
                        <option value="{{ $subject['id'] }}" data-school-id="{{ $subject['school_id'] ?? '' }}">
                            {{ $subject['label'] ?? ('Subject #'.($subject['id'] ?? '')) }}
                        </option>
                    @endforeach
                </select>
            </td>
            <td>
                <select data-name="timetable_rows[__INDEX__][teacher_id]" data-school-select>
                    <option value="">Select teacher</option>
                    @foreach($teacherOptions as $teacher)
                        <option value="{{ $teacher['id'] }}" data-school-id="{{ $teacher['school_id'] ?? '' }}">
                            {{ $teacher['label'] ?? ('Teacher #'.($teacher['id'] ?? '')) }}
                        </option>
                    @endforeach
                </select>
            </td>
            <td><input type="time" data-name="timetable_rows[__INDEX__][time_start]"></td>
            <td><input type="time" data-name="timetable_rows[__INDEX__][time_end]"></td>
            <td><button type="button" class="remove-routine-row">Remove</button></td>
        </tr>
    </template>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var schoolSelect = document.getElementById('school_id');
            var studentSearch = document.getElementById('student-search');
            var studentItems = Array.prototype.slice.call(document.querySelectorAll('[data-student-item]'));
            var assignmentBody = document.getElementById('assignment-body');
            var routineBody = document.getElementById('routine-body');
            var assignmentTemplate = document.getElementById('assignment-row-template');
            var routineTemplate = document.getElementById('routine-row-template');
            var schoolHint = document.getElementById('school-scope-hint');

            var bindNames = function (row, index) {
                var fields = row.querySelectorAll('[data-name]');
                fields.forEach(function (field) {
                    field.setAttribute('name', field.getAttribute('data-name').replace('__INDEX__', String(index)));
                });
            };

            var refreshRowIndexes = function (body, rowSelector) {
                var rows = body ? body.querySelectorAll(rowSelector) : [];
                rows.forEach(function (row, index) {
                    var fields = row.querySelectorAll('[name], [data-name]');
                    fields.forEach(function (field) {
                        var name = field.getAttribute('name') || field.getAttribute('data-name') || '';
                        if (name.indexOf('[') === -1) {
                            return;
                        }
                        var updated = name.replace(/\[(\d+)\]/, '[' + index + ']');
                        field.setAttribute('name', updated);
                    });
                });
            };

            var addRowFromTemplate = function (template, body, rowSelector) {
                if (!template || !body) {
                    return;
                }
                var fragment = template.content.cloneNode(true);
                var row = fragment.querySelector('tr');
                if (!row) {
                    return;
                }
                bindNames(row, body.querySelectorAll(rowSelector).length);
                body.appendChild(fragment);
                applySchoolScope();
            };

            document.getElementById('add-assignment-row')?.addEventListener('click', function () {
                addRowFromTemplate(assignmentTemplate, assignmentBody, '[data-assignment-row]');
            });

            document.getElementById('add-routine-row')?.addEventListener('click', function () {
                addRowFromTemplate(routineTemplate, routineBody, '[data-routine-row]');
            });

            document.addEventListener('click', function (event) {
                if (event.target.classList.contains('remove-assignment-row')) {
                    var row = event.target.closest('[data-assignment-row]');
                    if (row && assignmentBody) {
                        row.remove();
                        if (assignmentBody.querySelectorAll('[data-assignment-row]').length === 0) {
                            addRowFromTemplate(assignmentTemplate, assignmentBody, '[data-assignment-row]');
                        }
                        refreshRowIndexes(assignmentBody, '[data-assignment-row]');
                    }
                }

                if (event.target.classList.contains('remove-routine-row')) {
                    var row2 = event.target.closest('[data-routine-row]');
                    if (row2 && routineBody) {
                        row2.remove();
                        if (routineBody.querySelectorAll('[data-routine-row]').length === 0) {
                            addRowFromTemplate(routineTemplate, routineBody, '[data-routine-row]');
                        }
                        refreshRowIndexes(routineBody, '[data-routine-row]');
                    }
                }
            });

            var applyStudentSearch = function () {
                var keyword = (studentSearch?.value || '').trim().toLowerCase();
                studentItems.forEach(function (item) {
                    var searchIndex = item.getAttribute('data-search') || '';
                    var match = keyword === '' || searchIndex.indexOf(keyword) !== -1;
                    if (match && item.getAttribute('data-school-hidden') !== '1') {
                        item.style.display = 'grid';
                    } else if (item.getAttribute('data-school-hidden') !== '1') {
                        item.style.display = 'none';
                    }
                });
            };

            studentSearch?.addEventListener('input', applyStudentSearch);

            document.getElementById('select-visible-students')?.addEventListener('click', function () {
                studentItems.forEach(function (item) {
                    if (item.style.display === 'none') {
                        return;
                    }
                    var checkbox = item.querySelector('input[type="checkbox"]');
                    if (checkbox) {
                        checkbox.checked = true;
                    }
                });
            });

            document.getElementById('clear-visible-students')?.addEventListener('click', function () {
                studentItems.forEach(function (item) {
                    if (item.style.display === 'none') {
                        return;
                    }
                    var checkbox = item.querySelector('input[type="checkbox"]');
                    if (checkbox) {
                        checkbox.checked = false;
                    }
                });
            });

            var applySchoolScope = function () {
                var selectedSchool = schoolSelect ? String(schoolSelect.value || '') : '';
                var allScopedSelects = document.querySelectorAll('[data-school-select]');

                allScopedSelects.forEach(function (select) {
                    var options = select.querySelectorAll('option[data-school-id]');
                    options.forEach(function (option) {
                        var optionSchool = String(option.getAttribute('data-school-id') || '');
                        var allowed = selectedSchool === '' || optionSchool === '' || optionSchool === selectedSchool;
                        option.disabled = !allowed;
                        option.hidden = !allowed;
                    });

                    var selected = select.options[select.selectedIndex];
                    if (selected && selected.disabled) {
                        select.value = '';
                    }
                });

                studentItems.forEach(function (item) {
                    var itemSchool = String(item.getAttribute('data-school-id') || '');
                    var allowed = selectedSchool === '' || itemSchool === '' || itemSchool === selectedSchool;
                    item.setAttribute('data-school-hidden', allowed ? '0' : '1');
                    if (!allowed) {
                        item.style.display = 'none';
                    }
                });

                if (schoolHint) {
                    if (selectedSchool === '') {
                        schoolHint.textContent = 'Please select school first. Teacher/Subject/Student options will be limited to that school.';
                    } else {
                        schoolHint.textContent = 'School scope active. Only data from selected school is shown.';
                    }
                }

                applyStudentSearch();
            };

            schoolSelect?.addEventListener('change', applySchoolScope);
            applySchoolScope();
            applyStudentSearch();
        });
    </script>
@endsection
