@extends('web.layouts.app')

@section('content')
    <h1 class="title">School Management (API)</h1>
    <p class="subtitle">Super-admin can create/update/delete schools and top-level configuration.</p>

    <div class="nav">
        <a href="{{ route('dashboard') }}">Dashboard</a>
        @can('web-manage-schools')
        <a href="{{ route('panel.schools.create') }}" class="active">+ Create School</a>
        @endcan
        
    </div>

    @if (session('success'))
        <p class="flash-success">{{ session('success') }}</p>
    @endif

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    <form method="GET" action="{{ route('panel.schools.index') }}" class="panel panel-form panel-spaced">
        <div class="form-grid">
            <input type="text" name="search" placeholder="Search name/code/location" value="{{ $filters['search'] ?? '' }}">
            <input type="number" name="per_page" placeholder="Per Page" value="{{ $filters['per_page'] ?? 20 }}">
        </div>
        <button type="submit" class="btn-space-top">Filter</button>
    </form>

    <section class="panel">
        <div class="panel-head">Schools</div>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Code</th>
                <th>Name</th>
                <th>Location</th>
                <th>Users</th>
                <th>Classes</th>
                <th>Subjects</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($items as $item)
                <tr>
                    <td>{{ $item['id'] }}</td>
                    <td>{{ $item['school_code'] ?? '-' }}</td>
                    <td>{{ $item['name'] }}</td>
                    <td>{{ $item['location'] ?? '-' }}</td>
                    <td>{{ $item['users_count'] ?? '-' }}</td>
                    <td>{{ $item['classes_count'] ?? '-' }}</td>
                    <td>{{ $item['subjects_count'] ?? '-' }}</td>
                    <td>
                        @can('web-manage-schools')
                        <a href="{{ route('super-admin.schools.manage', $item['id']) }}">Manage</a>
                        
                        <a href="{{ route('panel.schools.edit', $item['id']) }}">Edit</a>
                        
                        <form action="{{ route('panel.schools.destroy', $item['id']) }}" method="POST" class="inline-form" onsubmit="return confirm('Delete this school?')">
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
