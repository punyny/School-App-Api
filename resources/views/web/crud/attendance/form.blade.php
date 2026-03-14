@extends('web.layouts.app')

@section('content')
    <h1 class="title">{{ $mode === 'create' ? 'Create Attendance' : 'Edit Attendance' }}</h1>
    <p class="subtitle">Data will be submitted to real API endpoint.</p>

    <div class="nav">
        <a href="{{ route('panel.attendance.index') }}">Back to list</a>
    </div>

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ $mode === 'create' ? route('panel.attendance.store') : route('panel.attendance.update', $item['id']) }}" class="panel panel-form">
        @csrf
        @if($mode === 'edit')
            @method('PUT')
        @endif

        @php
            $selectedStudentId = (string) old('student_id', $item['student_id'] ?? '');
            $selectedClassId = (string) old('class_id', $item['class_id'] ?? '');
            $studentSelectOptions = collect($studentOptions ?? []);
            $classSelectOptions = collect($classOptions ?? []);

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
        @endphp

        <label>Student</label>
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

        <label>Class</label>
        <div class="searchable-select-wrap">
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

        <label>Date</label>
        <input type="date" name="date" value="{{ old('date', $item['date'] ?? '') }}" required>

        <label>Start Time (HH:MM)</label>
        <input type="time" name="time_start" value="{{ old('time_start', isset($item['time_start']) ? substr($item['time_start'], 0, 5) : '') }}" required>

        <label>End Time (HH:MM)</label>
        <input type="time" name="time_end" value="{{ old('time_end', isset($item['time_end']) && $item['time_end'] ? substr($item['time_end'], 0, 5) : '') }}">

        <label>Status</label>
        <select name="status" required>
            <option value="P" {{ old('status', $item['status'] ?? '') === 'P' ? 'selected' : '' }}>P</option>
            <option value="A" {{ old('status', $item['status'] ?? '') === 'A' ? 'selected' : '' }}>A</option>
            <option value="L" {{ old('status', $item['status'] ?? '') === 'L' ? 'selected' : '' }}>L</option>
        </select>

        <button type="submit" class="btn-space-top">{{ $mode === 'create' ? 'Create' : 'Update' }}</button>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var classSelect = document.getElementById('class_id');
            var studentSelect = document.getElementById('student_id');

            if (!classSelect || !studentSelect) {
                return;
            }

            var filterStudents = function () {
                var selectedClass = classSelect.value;
                var options = Array.prototype.slice.call(studentSelect.options);

                options.forEach(function (option) {
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

            classSelect.addEventListener('change', filterStudents);
            filterStudents();
        });
    </script>
@endpush
