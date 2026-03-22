@extends('web.layouts.app')

@section('content')
    <h1 class="title">Announcement Management</h1>
    <p class="subtitle">Publish announcements for all users, roles, classes, or specific users.</p>

    <div class="nav">
        <a href="{{ route('dashboard') }}">Dashboard</a>
        @can('web-manage-announcements')
        <a href="{{ route('panel.announcements.create') }}" class="active">+ Create Announcement</a>
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
        $classSelectOptions = collect($classOptions ?? []);

        if ($selectedClassId !== '' && ! $classSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedClassId)) {
            $classSelectOptions = $classSelectOptions->prepend([
                'id' => (int) $selectedClassId,
                'label' => 'Class ID: '.$selectedClassId,
            ]);
        }
    @endphp

    <form method="GET" action="{{ route('panel.announcements.index') }}" class="panel panel-form panel-spaced">
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
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            <input type="number" name="per_page" placeholder="Per Page" value="{{ $filters['per_page'] ?? 20 }}">
        </div>
        <button type="submit" class="btn-space-top">Filter</button>
    </form>

    <section class="panel">
        <div class="panel-head">Announcements</div>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>School</th>
                <th>Class</th>
                <th>Date</th>
                <th>Files</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($items as $item)
                <tr>
                    <td>{{ $item['id'] }}</td>
                    <td>{{ $item['title'] }}</td>
                    <td>{{ $item['school_id'] }}</td>
                    <td>{{ $item['class_id'] ?? '-' }}</td>
                    <td>{{ $item['date'] }}</td>
                    <td>{{ count($item['media'] ?? []) + count($item['file_attachments'] ?? []) }}</td>
                    <td>
                        @can('web-manage-announcements')
                        <a href="{{ route('panel.announcements.edit', $item['id']) }}">Edit</a>
                        
                        @if(in_array($userRole, ['super-admin', 'admin', 'teacher'], true))
                            <form action="{{ route('panel.announcements.destroy', $item['id']) }}" method="POST" class="inline-form" onsubmit="return confirm('Delete this announcement?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit">Delete</button>
                            </form>
                        @endif
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
