@extends('web.layouts.app')

@section('content')
    @php
        $school = is_array($item['school'] ?? null) ? $item['school'] : [];
        $subjects = is_array($item['subjects'] ?? null) ? $item['subjects'] : [];
        $teachers = is_array($item['teachers'] ?? null) ? $item['teachers'] : [];
        $timetables = is_array($item['timetables'] ?? null) ? $item['timetables'] : [];
        $assignedStudents = is_array($item['students'] ?? null) ? $item['students'] : [];
        $classStudents = collect($assignedStudents)
            ->filter(fn ($student) => is_array($student))
            ->map(function (array $student): array {
                return [
                    'id' => (int) ($student['id'] ?? 0),
                    'name' => (string) data_get($student, 'user.name', 'Student'),
                    'khmer_name' => (string) data_get($student, 'user.khmer_name', ''),
                    'student_code' => (string) ($student['student_code'] ?? ''),
                    'grade' => (string) ($student['grade'] ?? ''),
                    'email' => (string) data_get($student, 'user.email', ''),
                    'phone' => (string) data_get($student, 'user.phone', ''),
                ];
            })
            ->filter(fn (array $student) => $student['id'] > 0)
            ->values();

        $weekOrder = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $orderedTimetables = collect($timetables)
            ->filter(fn ($row) => is_array($row))
            ->sortBy(function (array $row) use ($weekOrder): string {
                $day = strtolower((string) ($row['day_of_week'] ?? ''));
                $dayIndex = array_search($day, $weekOrder, true);
                if ($dayIndex === false) {
                    $dayIndex = 99;
                }

                return sprintf('%02d-%s', $dayIndex, (string) ($row['time_start'] ?? '00:00:00'));
            })
            ->values();

        $selectedLookup = collect($selectedIds ?? [])->mapWithKeys(fn ($id) => [(int) $id => true]);
        $subjectNames = collect($subjects)->mapWithKeys(fn ($subject) => [(int) ($subject['id'] ?? 0) => (string) ($subject['name'] ?? 'Subject')]);

        $assignedCount = count($assignedStudents);
        $schoolStudentCount = count($students);
        $availableCount = max(0, $schoolStudentCount - $assignedCount);
    @endphp

    <style>
        .student-picker-shell {
            border: 1px solid #dce8c7;
            border-radius: 14px;
            background: #fbfff7;
            padding: 12px;
        }

        .student-picker-tools {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto auto;
            gap: 8px;
            margin-bottom: 10px;
        }

        .student-picker-list {
            display: grid;
            gap: 8px;
            max-height: 480px;
            overflow: auto;
            padding-right: 4px;
        }

        .student-option {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: 10px;
            align-items: center;
            padding: 10px;
            border-radius: 12px;
            border: 1px solid #dce8c7;
            background: #fff;
        }

        .student-option input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin: 0;
        }

        .student-meta {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 4px;
        }

        .student-meta span {
            border: 1px solid #dde9ca;
            border-radius: 999px;
            background: #fbfff7;
            padding: 3px 8px;
            font-size: 10px;
            font-weight: 700;
            color: #657159;
        }

        .assignment-grid {
            display: grid;
            grid-template-columns: 1.45fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
        }

        @media (max-width: 980px) {
            .student-picker-tools,
            .assignment-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="topbar">
        <div>
            <h1 class="title">{{ __('ui.class_show.title') }}: {{ $item['name'] ?? 'Class' }}</h1>
            <p class="subtitle">
                {{ __('ui.class_show.grade') }}: {{ $item['grade_level'] ?? '-' }} |
                {{ __('ui.class_show.room') }}: {{ $item['room'] ?? '-' }} |
                {{ __('ui.class_show.school') }}: {{ $school['name'] ?? ($item['school_id'] ?? '-') }}
            </p>
        </div>
        <div class="mini-actions">
            <a href="{{ route('panel.classes.index', $userRole === 'super-admin' && !empty($item['school_id']) ? ['school_id' => $item['school_id']] : []) }}">{{ __('ui.class_show.back_to_classes') }}</a>
            @if($userRole === 'super-admin' && !empty($item['school_id']))
                <a href="{{ route('super-admin.schools.manage', $item['school_id']) }}">{{ __('ui.class_show.school_scope') }}</a>
            @endif
            <a href="{{ route('panel.classes.edit', $item['id']) }}">{{ __('ui.class_show.edit_class') }}</a>
        </div>
    </div>

    @if (session('success'))
        <p class="flash-success">{{ session('success') }}</p>
    @endif

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    <section class="metric-grid">
        <article class="metric-card metric-card-purple">
            <p class="metric-number">{{ $assignedCount }}</p>
            <p class="metric-label">{{ __('ui.class_show.assigned_students') }}</p>
        </article>
        <article class="metric-card metric-card-blue">
            <p class="metric-number">{{ $schoolStudentCount }}</p>
            <p class="metric-label">{{ __('ui.class_show.students_in_school') }}</p>
        </article>
        <article class="metric-card metric-card-orange">
            <p class="metric-number">{{ count($subjects) }}</p>
            <p class="metric-label">{{ __('ui.class_show.subjects') }}</p>
        </article>
        <article class="metric-card metric-card-green">
            <p class="metric-number">{{ count($timetables) }}</p>
            <p class="metric-label">{{ __('ui.class_show.timetable_rows') }}</p>
        </article>
    </section>

    <section class="assignment-grid">
        <article class="panel panel-form">
            <div class="panel-head">{{ __('ui.class_show.assign_students') }}</div>
            <p class="text-muted" style="margin:0 0 10px;">
                {{ __('ui.class_show.assign_students_hint') }}
            </p>

            <form method="POST" action="{{ route('panel.classes.sync-students', $item['id']) }}">
                @csrf

                <div class="student-picker-shell">
                    <div class="student-picker-tools">
                        <input type="text" id="student-filter-input" placeholder="{{ __('ui.class_show.search_student') }}">
                        <button type="button" id="select-visible-btn">{{ __('ui.class_show.select_visible') }}</button>
                        <button type="button" id="clear-visible-btn">{{ __('ui.class_show.clear_visible') }}</button>
                    </div>

                    <div class="student-picker-list" id="student-picker-list">
                        @forelse($students as $student)
                            @php
                                $currentClassName = $student['class_name'] !== '' ? $student['class_name'] : __('ui.class_show.unassigned');
                                $isCurrentClass = (int) ($student['class_id'] ?? 0) === (int) ($item['id'] ?? 0);
                                $searchText = strtolower(implode(' ', [
                                    $student['name'],
                                    $student['khmer_name'],
                                    $student['student_code'],
                                    $student['email'],
                                    $student['phone'],
                                    $currentClassName,
                                ]));
                            @endphp
                            <label class="student-option" data-student-option data-search="{{ $searchText }}">
                                <input
                                    type="checkbox"
                                    name="student_ids[]"
                                    value="{{ $student['id'] }}"
                                    {{ $selectedLookup->has((int) $student['id']) ? 'checked' : '' }}
                                >
                                <div>
                                    <strong>{{ $student['name'] }}</strong>
                                    <div class="student-meta">
                                        @if($student['khmer_name'] !== '')
                                            <span>{{ $student['khmer_name'] }}</span>
                                        @endif
                                        @if($student['student_code'] !== '')
                                            <span>ID: {{ $student['student_code'] }}</span>
                                        @endif
                                        @if($student['grade'] !== '')
                                            <span>Grade {{ $student['grade'] }}</span>
                                        @endif
                                        <span>{{ $isCurrentClass ? __('ui.class_show.already_in_class') : __('ui.class_show.current').': '.$currentClassName }}</span>
                                    </div>
                                </div>
                                <div class="text-muted" style="text-align:right;">
                                    {{ $student['email'] !== '' ? $student['email'] : $student['phone'] }}
                                </div>
                            </label>
                        @empty
                            <div class="empty">{{ __('ui.class_show.no_students') }}</div>
                        @endforelse
                    </div>
                </div>

                <button type="submit" class="btn-space-top">{{ __('ui.class_show.save_assignment') }}</button>
            </form>
        </article>

        <article class="panel panel-form">
            <div class="panel-head">{{ __('ui.class_show.class_setup') }}</div>
            <div class="cards">
                <div class="card">
                    <div class="label">{{ __('ui.class_show.school') }}</div>
                    <div class="value">{{ $school['name'] ?? 'N/A' }}</div>
                </div>
                <div class="card">
                    <div class="label">{{ __('ui.class_show.available') }}</div>
                    <div class="value">{{ $availableCount }}</div>
                </div>
            </div>

            <div class="top-list" style="margin-top:12px;">
                <div class="top-item">
                    <span class="rank-badge">S</span>
                    <div>
                        <strong>{{ __('ui.class_show.subjects') }}</strong>
                        <p class="text-muted">{{ count($subjects) > 0 ? collect($subjects)->pluck('name')->implode(', ') : __('ui.class_show.no_subjects') }}</p>
                    </div>
                </div>
                <div class="top-item">
                    <span class="rank-badge">T</span>
                    <div>
                        <strong>{{ __('ui.class_show.teacher_assignment') }}</strong>
                        <p class="text-muted">
                            @if(count($teachers) === 0)
                                {{ __('ui.class_show.no_teacher') }}
                            @else
                                {{ collect($teachers)->map(function ($teacher) use ($subjectNames) {
                                    $subjectId = (int) data_get($teacher, 'pivot.subject_id', 0);
                                    $subjectName = $subjectNames[$subjectId] ?? 'Subject';
                                    return ($teacher['name'] ?? 'Teacher').' - '.$subjectName;
                                })->implode(', ') }}
                            @endif
                        </p>
                    </div>
                </div>
                <div class="top-item">
                    <span class="rank-badge">R</span>
                    <div>
                        <strong>{{ __('ui.class_show.routine_rows') }}</strong>
                        <p class="text-muted">{{ count($timetables) }} {{ __('ui.class_show.timetable_entries') }}</p>
                    </div>
                    <a href="{{ route('panel.timetables.index', ['class_id' => $item['id']]) }}">{{ __('ui.class_show.open') }}</a>
                </div>
            </div>
        </article>
    </section>

    <section class="panel panel-spaced">
        <div class="panel-head">Class Full Table View</div>
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Name / Khmer Name</th>
                    <th>Subject / Grade</th>
                    <th>Code / Email</th>
                    <th>Phone / Time</th>
                </tr>
            </thead>
            <tbody>
                @forelse($teachers as $teacher)
                    @php
                        $subjectId = (int) data_get($teacher, 'pivot.subject_id', 0);
                    @endphp
                    <tr>
                        <td><strong>Teacher</strong></td>
                        <td>{{ $teacher['name'] ?? 'Teacher' }}</td>
                        <td>{{ $subjectNames[$subjectId] ?? 'Subject' }}</td>
                        <td>{{ $teacher['email'] ?? '-' }}</td>
                        <td>{{ $teacher['phone'] ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td><strong>Teacher</strong></td>
                        <td colspan="4">No teacher assignment yet.</td>
                    </tr>
                @endforelse

                @forelse($classStudents as $student)
                    <tr>
                        <td><strong>Student</strong></td>
                        <td>
                            {{ $student['name'] }}
                            @if($student['khmer_name'] !== '')
                                <div class="text-muted">{{ $student['khmer_name'] }}</div>
                            @endif
                        </td>
                        <td>{{ $student['grade'] !== '' ? 'Grade '.$student['grade'] : '-' }}</td>
                        <td>
                            {{ $student['student_code'] !== '' ? 'ID: '.$student['student_code'] : '-' }}
                            @if($student['email'] !== '')
                                <div class="text-muted">{{ $student['email'] }}</div>
                            @endif
                        </td>
                        <td>{{ $student['phone'] !== '' ? $student['phone'] : '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td><strong>Student</strong></td>
                        <td colspan="4">No students assigned yet.</td>
                    </tr>
                @endforelse

                @forelse($orderedTimetables as $row)
                    <tr>
                        <td><strong>Routine</strong></td>
                        <td>{{ ucfirst((string) ($row['day_of_week'] ?? '-')) }}</td>
                        <td>{{ $row['subject']['name'] ?? ($row['subject_id'] ?? '-') }}</td>
                        <td>{{ $row['teacher']['name'] ?? ($row['teacher_id'] ?? '-') }}</td>
                        <td>{{ substr((string) ($row['time_start'] ?? '-'), 0, 5) }} - {{ substr((string) ($row['time_end'] ?? '-'), 0, 5) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td><strong>Routine</strong></td>
                        <td colspan="4">No timetable rows yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="panel panel-spaced">
        <div class="panel-head">Student Roster Table</div>
        <table>
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Khmer Name</th>
                    <th>Grade</th>
                    <th>Email</th>
                    <th>Phone</th>
                </tr>
            </thead>
            <tbody>
                @forelse($classStudents as $student)
                    <tr>
                        <td>{{ $student['student_code'] !== '' ? $student['student_code'] : '#'.$student['id'] }}</td>
                        <td>{{ $student['name'] }}</td>
                        <td>{{ $student['khmer_name'] !== '' ? $student['khmer_name'] : '-' }}</td>
                        <td>{{ $student['grade'] !== '' ? $student['grade'] : '-' }}</td>
                        <td>{{ $student['email'] !== '' ? $student['email'] : '-' }}</td>
                        <td>{{ $student['phone'] !== '' ? $student['phone'] : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No students assigned in this class.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="admin-grid-bottom">
        <article class="panel">
            <div class="panel-head">{{ __('ui.class_show.subject_teacher_setup') }}</div>
            <table>
                <thead>
                    <tr>
                        <th>{{ __('ui.class_show.teacher') }}</th>
                        <th>{{ __('ui.class_show.subject') }}</th>
                        <th>{{ __('ui.class_show.email') }}</th>
                        <th>Phone</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($teachers as $teacher)
                        @php
                            $subjectId = (int) data_get($teacher, 'pivot.subject_id', 0);
                        @endphp
                        <tr>
                            <td><strong>{{ $teacher['name'] ?? 'Teacher' }}</strong></td>
                            <td>{{ $subjectNames[$subjectId] ?? 'Subject' }}</td>
                            <td>{{ $teacher['email'] ?? '-' }}</td>
                            <td>{{ $teacher['phone'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No teacher assignment yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </article>

        <article class="panel">
            <div class="panel-head">Timetable</div>
            <table>
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Time</th>
                        <th>Subject</th>
                        <th>Teacher</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orderedTimetables as $row)
                        <tr>
                            <td>{{ $row['day_of_week'] ?? '-' }}</td>
                            <td>{{ ($row['time_start'] ?? '-') }} - {{ ($row['time_end'] ?? '-') }}</td>
                            <td>{{ $row['subject']['name'] ?? ($row['subject_id'] ?? '-') }}</td>
                            <td>{{ $row['teacher']['name'] ?? ($row['teacher_id'] ?? '-') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No timetable rows yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </article>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var filterInput = document.getElementById('student-filter-input');
            var options = Array.prototype.slice.call(document.querySelectorAll('[data-student-option]'));
            var selectBtn = document.getElementById('select-visible-btn');
            var clearBtn = document.getElementById('clear-visible-btn');

            var applyFilter = function () {
                var keyword = (filterInput && filterInput.value ? filterInput.value : '').trim().toLowerCase();

                options.forEach(function (option) {
                    var haystack = option.getAttribute('data-search') || '';
                    var visible = keyword === '' || haystack.indexOf(keyword) !== -1;
                    option.style.display = visible ? 'grid' : 'none';
                });
            };

            if (filterInput) {
                filterInput.addEventListener('input', applyFilter);
            }

            if (selectBtn) {
                selectBtn.addEventListener('click', function () {
                    options.forEach(function (option) {
                        if (option.style.display === 'none') {
                            return;
                        }

                        var checkbox = option.querySelector('input[type="checkbox"]');
                        if (checkbox) {
                            checkbox.checked = true;
                        }
                    });
                });
            }

            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    options.forEach(function (option) {
                        if (option.style.display === 'none') {
                            return;
                        }

                        var checkbox = option.querySelector('input[type="checkbox"]');
                        if (checkbox) {
                            checkbox.checked = false;
                        }
                    });
                });
            }
        });
    </script>
@endsection
