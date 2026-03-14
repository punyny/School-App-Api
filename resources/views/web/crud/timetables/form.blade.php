@extends('web.layouts.app')

@section('content')
    <h1 class="title">{{ $mode === 'create' ? 'Create Timetable' : 'Edit Timetable' }}</h1>
    <p class="subtitle">Create timetable after class subjects and teacher assignments are ready.</p>

    <div class="nav">
        <a href="{{ route('panel.timetables.index') }}">Back to list</a>
    </div>

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ $mode === 'create' ? route('panel.timetables.store') : route('panel.timetables.update', $item['id']) }}" class="panel panel-form">
        @csrf
        @if($mode === 'edit')
            @method('PUT')
        @endif

        @php
            $selectedClassId = (string) old('class_id', $item['class_id'] ?? '');
            $selectedSubjectId = (string) old('subject_id', $item['subject_id'] ?? '');
            $classSelectOptions = collect($classOptions ?? []);
            $subjectSelectOptions = collect($subjectOptions ?? []);

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

        <label>Subject</label>
        <div class="searchable-select-wrap">
            <input type="text" class="searchable-select-search" placeholder="Search subject..." data-select-search-for="subject_id">
            <select id="subject_id" name="subject_id" required>
                <option value="">Select subject</option>
                @foreach($subjectSelectOptions as $option)
                    <option value="{{ $option['id'] }}" {{ $selectedSubjectId === (string) $option['id'] ? 'selected' : '' }}>
                        {{ $option['label'] }}
                    </option>
                @endforeach
            </select>
        </div>

        @if($userRole !== 'teacher')
            <label>Teacher ID</label>
            <input type="number" name="teacher_id" value="{{ old('teacher_id', $item['teacher_id'] ?? '') }}" required>
        @else
            <p class="subtitle subtitle-tight">Teacher role will auto-assign your own user ID as teacher_id.</p>
        @endif

        <label>Day of Week</label>
        @php $day = old('day_of_week', $item['day_of_week'] ?? 'monday'); @endphp
        <select name="day_of_week" required>
            @foreach (['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $option)
                <option value="{{ $option }}" {{ $day === $option ? 'selected' : '' }}>{{ ucfirst($option) }}</option>
            @endforeach
        </select>

        <label>Start Time</label>
        <input type="time" name="time_start" value="{{ old('time_start', isset($item['time_start']) ? substr((string) $item['time_start'], 0, 5) : '') }}" required>

        <label>End Time</label>
        <input type="time" name="time_end" value="{{ old('time_end', isset($item['time_end']) ? substr((string) $item['time_end'], 0, 5) : '') }}" required>

        <button type="submit" class="btn-space-top">{{ $mode === 'create' ? 'Create' : 'Update' }}</button>
    </form>
@endsection
