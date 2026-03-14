@extends('web.layouts.app')

@section('content')
    <h1 class="title">Attendance Management (API)</h1>
    <p class="subtitle">Super-admin/Admin/Teacher can manage attendance via API.</p>

    <div class="nav">
        <a href="{{ route('dashboard') }}">Dashboard</a>
        @can('web-manage-attendance')
        <a href="{{ route('panel.attendance.create') }}" class="active">+ Create Attendance</a>
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
        $selectedStudentId = (string) ($filters['student_id'] ?? '');
        $classSelectOptions = collect($classOptions ?? []);
        $studentSelectOptions = collect($studentOptions ?? []);

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
    @endphp

    <form method="GET" action="{{ route('panel.attendance.index') }}" class="panel panel-form panel-spaced">
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
                <input type="text" class="searchable-select-search" placeholder="Search student..." data-select-search-for="filter_student_id">
                <select id="filter_student_id" name="student_id">
                    <option value="">Student</option>
                    @foreach($studentSelectOptions as $option)
                        <option value="{{ $option['id'] }}" {{ $selectedStudentId === (string) $option['id'] ? 'selected' : '' }}>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <select name="status">
                <option value="">Status</option>
                <option value="P" {{ ($filters['status'] ?? '') === 'P' ? 'selected' : '' }}>P</option>
                <option value="A" {{ ($filters['status'] ?? '') === 'A' ? 'selected' : '' }}>A</option>
                <option value="L" {{ ($filters['status'] ?? '') === 'L' ? 'selected' : '' }}>L</option>
            </select>
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            <input type="number" name="per_page" placeholder="Per Page" value="{{ $filters['per_page'] ?? 20 }}">
        </div>
        <button type="submit" class="btn-space-top">Filter</button>
    </form>

    <section class="panel">
        <div class="panel-head">Attendance Records</div>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Date</th>
                <th>Class</th>
                <th>Student</th>
                <th>Time</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($items as $item)
                <tr>
                    <td>{{ $item['id'] }}</td>
                    <td>{{ $item['date'] }}</td>
                    <td>{{ $item['class_id'] }}</td>
                    <td>{{ $item['student_id'] }}</td>
                    <td>{{ $item['time_start'] }} - {{ $item['time_end'] ?? '-' }}</td>
                    <td>{{ $item['status'] }}</td>
                    <td>
                        @can('web-manage-attendance')
                        <a href="{{ route('panel.attendance.edit', $item['id']) }}">Edit</a>
                        
                        <form action="{{ route('panel.attendance.destroy', $item['id']) }}" method="POST" class="inline-form" onsubmit="return confirm('Delete this attendance?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit">Delete</button>
                        </form>
                        @endcan
                        
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">No data.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
