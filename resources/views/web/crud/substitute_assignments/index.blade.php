@extends('web.layouts.app')

@section('content')
    @php
        $selectedClassId = (string) ($filters['class_id'] ?? '');
        $selectedSubstituteId = (string) ($filters['substitute_teacher_id'] ?? '');
        $selectedDate = (string) ($filters['date'] ?? now()->toDateString());
        $selectedPerPage = (string) ($filters['per_page'] ?? 20);

        $assignClassId = (string) old('class_id', '');
        $assignSubjectId = (string) old('subject_id', '');
        $assignSubstituteTeacherId = (string) old('substitute_teacher_id', '');
        $assignDate = (string) old('date', now()->toDateString());
        $assignTimeStart = (string) old('time_start', '');
        $assignTimeEnd = (string) old('time_end', '');
        $assignNotes = (string) old('notes', '');

        $classSelectOptions = collect($classOptions ?? []);
        $teacherSelectOptions = collect($teacherOptions ?? []);
        $subjectOptionsByClass = $subjectOptionsByClass ?? [];

        if ($assignClassId !== '' && ! $classSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $assignClassId)) {
            $classSelectOptions = $classSelectOptions->prepend([
                'id' => (int) $assignClassId,
                'label' => 'Class ID: '.$assignClassId,
            ]);
        }

        if ($selectedClassId !== '' && ! $classSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedClassId)) {
            $classSelectOptions = $classSelectOptions->prepend([
                'id' => (int) $selectedClassId,
                'label' => 'Class ID: '.$selectedClassId,
            ]);
        }

        if ($assignSubstituteTeacherId !== '' && ! $teacherSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $assignSubstituteTeacherId)) {
            $teacherSelectOptions = $teacherSelectOptions->prepend([
                'id' => (int) $assignSubstituteTeacherId,
                'label' => 'Teacher ID: '.$assignSubstituteTeacherId,
            ]);
        }

        if ($selectedSubstituteId !== '' && ! $teacherSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedSubstituteId)) {
            $teacherSelectOptions = $teacherSelectOptions->prepend([
                'id' => (int) $selectedSubstituteId,
                'label' => 'Teacher ID: '.$selectedSubstituteId,
            ]);
        }
    @endphp

    <h1 class="title">Substitute Teacher Assignment</h1>
    <p class="subtitle">Assign a substitute teacher for one real class session without editing the base timetable.</p>

    <div class="nav">
        <a href="{{ route('dashboard') }}">Dashboard</a>
        <a href="{{ route('panel.attendance.index') }}">Attendance List</a>
    </div>

    @if (session('success'))
        <p class="flash-success">{{ session('success') }}</p>
    @endif

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    <section class="panel">
        <div class="panel-head">Create Assignment</div>
        <form method="POST" action="{{ route('panel.substitute-assignments.store') }}" class="panel-form">
            @csrf

            <div class="form-grid">
                <div>
                    <label>Class</label>
                    <input type="text" class="searchable-select-search" placeholder="Search class..." data-select-search-for="assign_class_id">
                    <select id="assign_class_id" name="class_id" required>
                        <option value="">Select class</option>
                        @foreach($classSelectOptions as $option)
                            <option value="{{ $option['id'] }}" {{ $assignClassId === (string) $option['id'] ? 'selected' : '' }}>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label>Subject</label>
                    <input type="text" class="searchable-select-search" placeholder="Search subject..." data-select-search-for="assign_subject_id">
                    <select id="assign_subject_id" name="subject_id" required>
                        <option value="">Select subject</option>
                    </select>
                </div>

                <div>
                    <label>Substitute Teacher</label>
                    <input type="text" class="searchable-select-search" placeholder="Search teacher..." data-select-search-for="assign_substitute_teacher_id">
                    <select id="assign_substitute_teacher_id" name="substitute_teacher_id" required>
                        <option value="">Select substitute teacher</option>
                        @foreach($teacherSelectOptions as $option)
                            <option value="{{ $option['id'] }}" {{ $assignSubstituteTeacherId === (string) $option['id'] ? 'selected' : '' }}>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label>Date</label>
                    <input type="date" name="date" value="{{ $assignDate }}" required>
                </div>

                <div>
                    <label>Time Start</label>
                    <input type="time" name="time_start" value="{{ $assignTimeStart }}" required>
                </div>

                <div>
                    <label>Time End</label>
                    <input type="time" name="time_end" value="{{ $assignTimeEnd }}" required>
                </div>
            </div>

            <label>Notes (optional)</label>
            <input type="text" name="notes" value="{{ $assignNotes }}" maxlength="255" placeholder="Reason or context for substitute assignment">

            <button type="submit" class="btn-space-top">Assign Substitute</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-head">Substitute Assignment List</div>

        <form method="GET" action="{{ route('panel.substitute-assignments.index') }}" class="panel-form panel-spaced">
            <div class="form-grid">
                <div>
                    <input type="text" class="searchable-select-search" placeholder="Search class..." data-select-search-for="filter_class_id">
                    <select id="filter_class_id" name="class_id">
                        <option value="">All classes</option>
                        @foreach($classSelectOptions as $option)
                            <option value="{{ $option['id'] }}" {{ $selectedClassId === (string) $option['id'] ? 'selected' : '' }}>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <input type="text" class="searchable-select-search" placeholder="Search teacher..." data-select-search-for="filter_substitute_teacher_id">
                    <select id="filter_substitute_teacher_id" name="substitute_teacher_id">
                        <option value="">All substitute teachers</option>
                        @foreach($teacherSelectOptions as $option)
                            <option value="{{ $option['id'] }}" {{ $selectedSubstituteId === (string) $option['id'] ? 'selected' : '' }}>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label>Date</label>
                    <input type="date" name="date" value="{{ $selectedDate }}">
                </div>

                <div>
                    <label>Per Page</label>
                    <input type="number" min="1" max="100" name="per_page" value="{{ $selectedPerPage }}">
                </div>
            </div>

            <button type="submit" class="btn-space-top">Filter</button>
        </form>

        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Date</th>
                <th>Class</th>
                <th>Subject</th>
                <th>Time</th>
                <th>Original Teacher</th>
                <th>Substitute Teacher</th>
                <th>Assigned By</th>
                <th>Notes</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($items as $item)
                <tr>
                    <td>{{ $item['id'] }}</td>
                    <td>{{ $item['date'] }}</td>
                    <td>{{ $item['class']['name'] ?? $item['class_id'] }}</td>
                    <td>{{ $item['subject']['name'] ?? $item['subject_id'] }}</td>
                    <td>{{ substr((string) ($item['time_start'] ?? ''), 0, 5) }} - {{ substr((string) ($item['time_end'] ?? ''), 0, 5) }}</td>
                    <td>{{ $item['original_teacher']['name'] ?? $item['original_teacher_id'] }}</td>
                    <td>{{ $item['substitute_teacher']['name'] ?? $item['substitute_teacher_id'] }}</td>
                    <td>{{ $item['assigned_by']['name'] ?? $item['assigned_by_user_id'] ?? '-' }}</td>
                    <td>{{ $item['notes'] ?? '-' }}</td>
                    <td>
                        <form action="{{ route('panel.substitute-assignments.destroy', $item['id']) }}" method="POST" class="inline-form" onsubmit="return confirm('Remove this substitute assignment?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit">Remove</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">No substitute assignments found.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <script>
        (function () {
            var subjectOptionsByClass = @json($subjectOptionsByClass ?? []);
            var classSelect = document.getElementById('assign_class_id');
            var subjectSelect = document.getElementById('assign_subject_id');
            if (!classSelect || !subjectSelect) {
                return;
            }

            var selectedSubject = @json($assignSubjectId);

            var renderSubjectOptions = function (classId, selectedId) {
                var options = subjectOptionsByClass[String(classId || '')] || [];
                subjectSelect.innerHTML = '';

                var placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = options.length > 0 ? 'Select subject' : 'No subject for selected class';
                subjectSelect.appendChild(placeholder);

                options.forEach(function (option) {
                    var node = document.createElement('option');
                    node.value = String(option.id || '');
                    node.textContent = String(option.label || ('Subject ' + String(option.id || '')));
                    if (selectedId && node.value === String(selectedId)) {
                        node.selected = true;
                    }
                    subjectSelect.appendChild(node);
                });
            };

            renderSubjectOptions(classSelect.value, selectedSubject);

            classSelect.addEventListener('change', function () {
                renderSubjectOptions(classSelect.value, '');
            });
        })();
    </script>
@endsection
