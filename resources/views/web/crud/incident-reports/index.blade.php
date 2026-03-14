@extends('web.layouts.app')

@section('content')
    <h1 class="title">Incident Report Management (API)</h1>
    <p class="subtitle">Track and resolve student incidents.</p>

    <div class="nav">
        <a href="{{ route('dashboard') }}">Dashboard</a>
        @can('web-manage-incident-reports')
        <a href="{{ route('panel.incident-reports.create') }}" class="active">+ Create Incident</a>
        @endcan
        
    </div>

    @if (session('success'))
        <p class="flash-success">{{ session('success') }}</p>
    @endif

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    @php
        $selectedStudentId = (string) ($filters['student_id'] ?? '');
        $studentSelectOptions = collect($studentOptions ?? []);

        if ($selectedStudentId !== '' && ! $studentSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedStudentId)) {
            $studentSelectOptions = $studentSelectOptions->prepend([
                'id' => (int) $selectedStudentId,
                'label' => 'Student ID: '.$selectedStudentId,
            ]);
        }
    @endphp

    <form method="GET" action="{{ route('panel.incident-reports.index') }}" class="panel panel-form panel-spaced">
        <div class="form-grid">
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
            <input type="text" name="type" placeholder="Type" value="{{ $filters['type'] ?? '' }}">
            <select name="acknowledged">
                <option value="">Acknowledged?</option>
                <option value="1" {{ ($filters['acknowledged'] ?? '') === '1' ? 'selected' : '' }}>Yes</option>
                <option value="0" {{ ($filters['acknowledged'] ?? '') === '0' ? 'selected' : '' }}>No</option>
            </select>
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            <input type="number" name="per_page" placeholder="Per Page" value="{{ $filters['per_page'] ?? 20 }}">
        </div>
        <button type="submit" class="btn-space-top">Filter</button>
    </form>

    <section class="panel">
        <div class="panel-head">Incident Reports</div>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Date</th>
                <th>Student</th>
                <th>Type</th>
                <th>Acknowledged</th>
                <th>Reporter</th>
                <th>Description</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($items as $item)
                <tr>
                    <td>{{ $item['id'] }}</td>
                    <td>{{ $item['date'] }}</td>
                    <td>{{ $item['student']['user']['name'] ?? $item['student_id'] }}</td>
                    <td>{{ $item['type'] ?? '-' }}</td>
                    <td>{{ !empty($item['acknowledged']) ? 'Yes' : 'No' }}</td>
                    <td>{{ $item['reporter']['name'] ?? ($item['reporter_id'] ?? '-') }}</td>
                    <td>{{ $item['description'] }}</td>
                    <td>
                        @can('web-manage-incident-reports')
                        <a href="{{ route('panel.incident-reports.edit', $item['id']) }}">Edit</a>
                        
                        <form action="{{ route('panel.incident-reports.destroy', $item['id']) }}" method="POST" class="inline-form" onsubmit="return confirm('Delete this incident report?')">
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
