@extends('web.layouts.app')

@section('content')
    @php
        $resolveImage = static function (?string $value): string {
            $url = trim((string) $value);
            if ($url === '') {
                return '';
            }

            if (\Illuminate\Support\Str::startsWith($url, ['http://', 'https://', '/', 'data:'])) {
                return $url;
            }

            return asset($url);
        };
    @endphp

    <h1 class="title">Student Management</h1>
    <p class="subtitle">Manage student records in your school scope.</p>

    <div class="nav">
        <a href="{{ route('dashboard') }}">Dashboard</a>
        @can('web-manage-students')
        <a href="{{ route('panel.students.create') }}" class="active">+ Create Student</a>
        @endcan
        
    </div>

    @if (session('success'))
        <p class="flash-success">{{ session('success') }}</p>
    @endif

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('panel.students.import-csv') }}" class="panel panel-form panel-spaced" enctype="multipart/form-data">
        @csrf
        <div class="panel-head">Import Students CSV</div>
        <div class="upload-shell">
            <div class="upload-head">
                <p class="upload-note">Use the new student CSV format. Required columns: <strong>first_name</strong>, <strong>last_name</strong>, <strong>khmer_name</strong>, <strong>phone</strong>, <strong>email</strong>. Class and student code can be added later.</p>
                <span class="badge-soft">CSV</span>
            </div>
            <label class="upload-zone" data-upload-zone>
                <input id="students_csv_file" type="file" name="csv_file" accept=".csv,text/csv" required>
                <span class="upload-title">Drop student CSV file here or click to browse</span>
                <span class="upload-meta" data-upload-meta>No file selected</span>
            </label>
            <div class="upload-hints">
                <span>class / class_id optional</span>
                <span>student_id optional</span>
                <span>UTF-8</span>
                <span><a href="{{ asset('templates/student_import_template.csv') }}" download>Download template</a></span>
            </div>
        </div>
        <div class="form-grid btn-space-top">
            @if($userRole === 'super-admin')
                <input type="number" name="school_id" placeholder="Default school_id">
            @else
                <input type="text" value="School auto-detect by your admin account" disabled>
            @endif
        </div>
        <button type="submit" class="btn-space-top">Upload Student CSV</button>
    </form>

    @php
        $selectedClassId = (string) ($filters['class_id'] ?? '');
        $classSelectOptions = collect($classOptions ?? []);
        $totalStudents = (int) ($meta['total'] ?? count($items ?? []));
        $visibleStudents = count($items ?? []);
        $perPageMode = (string) ($filters['per_page'] ?? '20');
        $studentSummary = $perPageMode === 'all'
            ? "Showing all {$visibleStudents} students"
            : "Showing {$visibleStudents} of {$totalStudents} students";

        if ($selectedClassId !== '' && ! $classSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedClassId)) {
            $classSelectOptions = $classSelectOptions->prepend([
                'id' => (int) $selectedClassId,
                'label' => 'Class ID: '.$selectedClassId,
            ]);
        }
    @endphp

    <form method="GET" action="{{ route('panel.students.index') }}" class="panel panel-form panel-spaced">
        <div class="form-grid">
            @if($userRole === 'super-admin')
                <input type="number" name="school_id" placeholder="School ID" value="{{ $filters['school_id'] ?? '' }}">
            @endif
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
            <input type="text" name="search" placeholder="Name/Email" value="{{ $filters['search'] ?? '' }}">
            <select name="active">
                <option value="">Active?</option>
                <option value="1" {{ ($filters['active'] ?? '') === '1' ? 'selected' : '' }}>Yes</option>
                <option value="0" {{ ($filters['active'] ?? '') === '0' ? 'selected' : '' }}>No</option>
            </select>
            @php
                $perPageValue = (string) ($filters['per_page'] ?? '20');
            @endphp
            <select name="per_page">
                <option value="20" {{ $perPageValue === '20' ? 'selected' : '' }}>មើលម្ដង 20</option>
                <option value="all" {{ $perPageValue === 'all' ? 'selected' : '' }}>View All</option>
            </select>
        </div>
        <div class="btn-space-top" style="display:flex; gap:0.75rem; flex-wrap:wrap;">
            <button type="submit">Filter</button>
            <a href="{{ route('panel.students.index') }}">Clear</a>
            @if($perPageMode !== 'all')
                <a href="{{ route('panel.students.index', array_merge(request()->except('page'), ['per_page' => 'all'])) }}">View All Students</a>
            @endif
        </div>
    </form>

    <section class="panel">
        <div class="panel-head">Students</div>
        <p class="text-muted" style="margin:10px 0 16px;">{{ $studentSummary }}</p>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Student ID</th>
                <th>Photo</th>
                <th>Name</th>
                <th>Khmer Name</th>
                <th>Email</th>
                <th>Class</th>
                <th>Grade</th>
                <th>Parents</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($items as $item)
                <tr>
                    <td>{{ $item['id'] }}</td>
                    <td>{{ $item['student_code'] ?? '-' }}</td>
                    <td>
                        @if(!empty($item['user']['image_url']))
                            <img src="{{ $resolveImage($item['user']['image_url']) }}" alt="Student image" class="avatar-list">
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $item['user']['name'] ?? '-' }}</td>
                    <td>{{ $item['user']['khmer_name'] ?? '-' }}</td>
                    <td>{{ $item['user']['email'] ?? '-' }}</td>
                    <td>{{ $item['class']['name'] ?? ($item['class_id'] ?? '-') }}</td>
                    <td>{{ $item['grade'] ?? '-' }}</td>
                    <td>{{ count($item['parents'] ?? []) }}</td>
                    <td>
                        @can('web-manage-students')
                        <a href="{{ route('panel.students.show', $item['id']) }}">View</a>
                        
                        <a href="{{ route('panel.students.edit', $item['id']) }}">Edit</a>
                        
                        <form action="{{ route('panel.students.destroy', $item['id']) }}" method="POST" class="inline-form" onsubmit="return confirm('Delete this student?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit">Delete</button>
                        </form>
                        @endcan
                        
                    </td>
                </tr>
            @empty
                <tr><td colspan="10">No data.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
