@extends('web.layouts.app')

@section('content')
    <h1 class="title">Timetable Management (API)</h1>
    <p class="subtitle">Manage class schedule rows.</p>

    <div class="nav">
        <a href="{{ route('dashboard') }}">Dashboard</a>
        @can('web-manage-timetables')
        <a href="{{ route('panel.timetables.create') }}" class="active">+ Create Timetable</a>
        @endcan
        
    </div>

    @if (session('success'))
        <p class="flash-success">{{ session('success') }}</p>
    @endif

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    @php
        $selectedClassId = (string) ($filters['class_id'] ?? '');
        $selectedSubjectId = (string) ($filters['subject_id'] ?? '');
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

    <form method="GET" action="{{ route('panel.timetables.index') }}" class="panel panel-form panel-spaced">
        <div class="form-grid">
            <div>
                <input type="text" class="searchable-select-search" placeholder="Search class..." data-select-search-for="filter_class_id">
                <select id="filter_class_id" name="class_id">
                    <option value="">Class</option>
                    @foreach($classSelectOptions as $option)
                        <option value="{{ $option['id'] }}" {{ $selectedClassId === (string) $option['id'] ? 'selected' : '' }}>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <input type="text" class="searchable-select-search" placeholder="Search subject..." data-select-search-for="filter_subject_id">
                <select id="filter_subject_id" name="subject_id">
                    <option value="">Subject</option>
                    @foreach($subjectSelectOptions as $option)
                        <option value="{{ $option['id'] }}" {{ $selectedSubjectId === (string) $option['id'] ? 'selected' : '' }}>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <input type="number" name="teacher_id" placeholder="Teacher ID" value="{{ $filters['teacher_id'] ?? '' }}">
            <select name="day_of_week">
                <option value="">Day of Week</option>
                @foreach (['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $day)
                    <option value="{{ $day }}" {{ ($filters['day_of_week'] ?? '') === $day ? 'selected' : '' }}>{{ ucfirst($day) }}</option>
                @endforeach
            </select>
            <input type="number" name="per_page" placeholder="Per Page" value="{{ $filters['per_page'] ?? 20 }}">
        </div>
        <button type="submit" class="btn-space-top">Filter</button>
    </form>

    <section class="panel">
        <div class="panel-head">Timetables</div>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Class</th>
                <th>Subject</th>
                <th>Teacher</th>
                <th>Day</th>
                <th>Start</th>
                <th>End</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($items as $item)
                <tr>
                    <td>{{ $item['id'] }}</td>
                    <td>{{ $item['class']['name'] ?? $item['class_id'] }}</td>
                    <td>{{ $item['subject']['name'] ?? $item['subject_id'] }}</td>
                    <td>{{ $item['teacher']['name'] ?? $item['teacher_id'] }}</td>
                    <td>{{ ucfirst($item['day_of_week']) }}</td>
                    <td>{{ substr((string) $item['time_start'], 0, 5) }}</td>
                    <td>{{ substr((string) $item['time_end'], 0, 5) }}</td>
                    <td>
                        @can('web-manage-timetables')
                        <a href="{{ route('panel.timetables.edit', $item['id']) }}">Edit</a>
                        
                        <form action="{{ route('panel.timetables.destroy', $item['id']) }}" method="POST" class="inline-form" onsubmit="return confirm('Delete this timetable row?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit">Delete</button>
                        </form>
                        @endcan
                        
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">No data.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
