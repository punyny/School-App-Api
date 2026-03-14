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

    <h1 class="title">User Management (API)</h1>
    <p class="subtitle">Manage users by role and school scope via API.</p>

    <div class="nav">
        <a href="{{ route('dashboard') }}">Dashboard</a>
        @can('web-manage-users')
        <a href="{{ route('panel.users.create') }}" class="active">+ Create User</a>
        @endcan
        
    </div>

    @if (session('success'))
        <p class="flash-success">{{ session('success') }}</p>
    @endif

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('panel.users.import-csv') }}" class="panel panel-form panel-spaced" enctype="multipart/form-data">
        @csrf
        <div class="panel-head">Import Teacher/Student CSV</div>
        <div class="upload-shell">
            <div class="upload-head">
                <p class="upload-note">Required for <strong>student</strong> rows: <strong>name</strong>, <strong>khmer_name</strong>, <strong>class</strong>, <strong>student_id</strong>.</p>
                <span class="badge-soft">CSV</span>
            </div>
            <label class="upload-zone" data-upload-zone>
                <input id="users_csv_file" type="file" name="csv_file" accept=".csv,text/csv" required>
                <span class="upload-title">Drop CSV file here or click to browse</span>
                <span class="upload-meta" data-upload-meta>No file selected</span>
            </label>
            <div class="upload-hints">
                <span>UTF-8</span>
                <span>Teacher + Student</span>
                <span>role, class, student_id</span>
                <span><a href="{{ asset('templates/user_import_template.csv') }}" download>Download template</a></span>
            </div>
        </div>
        <div class="form-grid btn-space-top">
            <select name="role">
                <option value="teacher">Default Role: Teacher</option>
                <option value="student">Default Role: Student</option>
            </select>
            @if($userRole === 'super-admin')
                <input type="number" name="school_id" placeholder="Default school_id (optional)">
            @else
                <input type="text" value="School auto-detect by your admin account" disabled>
            @endif
        </div>
        <button type="submit" class="btn-space-top">Upload CSV</button>
    </form>

    <form method="GET" action="{{ route('panel.users.index') }}" class="panel panel-form panel-spaced">
        <div class="form-grid">
            @if($userRole === 'super-admin')
                <input type="number" name="school_id" placeholder="School ID" value="{{ $filters['school_id'] ?? '' }}">
            @endif
            <select name="role">
                <option value="">Role</option>
                @foreach (['super-admin', 'admin', 'teacher', 'student', 'parent'] as $role)
                    <option value="{{ $role }}" {{ ($filters['role'] ?? '') === $role ? 'selected' : '' }}>{{ $role }}</option>
                @endforeach
            </select>
            <select name="active">
                <option value="">Active?</option>
                <option value="1" {{ ($filters['active'] ?? '') === '1' ? 'selected' : '' }}>Yes</option>
                <option value="0" {{ ($filters['active'] ?? '') === '0' ? 'selected' : '' }}>No</option>
            </select>
            <input type="text" name="search" placeholder="Name/Email/Phone" value="{{ $filters['search'] ?? '' }}">
            <input type="number" name="per_page" placeholder="Per Page" value="{{ $filters['per_page'] ?? 20 }}">
        </div>
        <button type="submit" class="btn-space-top">Filter</button>
    </form>

    <section class="panel">
        <div class="panel-head">Users</div>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Code</th>
                <th>Photo</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>School</th>
                <th>Active</th>
                <th>Student Profile</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($items as $item)
                <tr>
                    <td>{{ $item['id'] }}</td>
                    <td>{{ $item['user_code'] ?? '-' }}</td>
                    <td>
                        @if(!empty($item['image_url']))
                            <img src="{{ $resolveImage($item['image_url']) }}" alt="User image" class="avatar-list">
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $item['name'] }}</td>
                    <td>{{ $item['email'] }}</td>
                    <td>{{ $item['role'] }}</td>
                    <td>{{ $item['school']['name'] ?? ($item['school_id'] ?? '-') }}</td>
                    <td>{{ !empty($item['active']) ? 'Yes' : 'No' }}</td>
                    <td>{{ !empty($item['student_profile']) ? 'Yes' : 'No' }}</td>
                    <td>
                        @can('web-manage-users')
                        <a href="{{ route('panel.users.show', $item['id']) }}">View</a>
                        
                        <a href="{{ route('panel.users.edit', $item['id']) }}">Edit</a>
                        
                        <form action="{{ route('panel.users.destroy', $item['id']) }}" method="POST" class="inline-form" onsubmit="return confirm('Delete this user?')">
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
