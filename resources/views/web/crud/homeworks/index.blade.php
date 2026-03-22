@extends('web.layouts.app')

@section('content')
    <h1 class="title">Homework Management</h1>
    <p class="subtitle">Manage homework by class, subject, and teacher assignment.</p>

    <div class="nav">
        <a href="{{ route('dashboard') }}">Dashboard</a>
        @can('web-manage-homeworks')
        <a href="{{ route('panel.homeworks.create') }}" class="active">+ Create Homework</a>
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

    <form method="GET" action="{{ route('panel.homeworks.index') }}" class="panel panel-form panel-spaced">
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
            <input type="date" name="due_from" value="{{ $filters['due_from'] ?? '' }}">
            <input type="date" name="due_to" value="{{ $filters['due_to'] ?? '' }}">
            <input type="number" name="per_page" placeholder="Per Page" value="{{ $filters['per_page'] ?? 20 }}">
        </div>
        <button type="submit" class="btn-space-top">Filter</button>
    </form>

    <section class="panel">
        <div class="panel-head">Homeworks</div>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Class</th>
                <th>Subject</th>
                <th>Due Date</th>
                <th>Due Time</th>
                <th>Files</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($items as $item)
                <tr>
                    <td>{{ $item['id'] }}</td>
                    <td>{{ $item['title'] }}</td>
                    <td>{{ $item['class_id'] }}</td>
                    <td>{{ $item['subject_id'] }}</td>
                    <td>{{ $item['due_date'] ?? '-' }}</td>
                    <td>{{ isset($item['due_time']) && is_string($item['due_time']) ? substr($item['due_time'], 0, 5) : '-' }}</td>
                    <td>{{ count($item['media'] ?? []) + count($item['file_attachments'] ?? []) }}</td>
                    <td>
                        @can('web-manage-homeworks')
                        <a href="{{ route('panel.homeworks.edit', $item['id']) }}">Edit</a>
                        
                        <form action="{{ route('panel.homeworks.destroy', $item['id']) }}" method="POST" class="inline-form" onsubmit="return confirm('Delete this homework?')">
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
