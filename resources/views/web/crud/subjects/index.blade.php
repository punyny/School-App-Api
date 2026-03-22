@extends('web.layouts.app')

@section('content')
    <h1 class="title">Subject Management</h1>
    <p class="subtitle">Create, update, and organize subjects by school.</p>

    <div class="nav">
        <a href="{{ route('dashboard') }}">Dashboard</a>
        @can('web-manage-subjects')
        <a href="{{ route('panel.subjects.create') }}" class="active">+ Create Subject</a>
        @endcan
        
    </div>

    @if (session('success'))
        <p class="flash-success">{{ session('success') }}</p>
    @endif

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('panel.subjects.install-khmer-core') }}" class="panel panel-form panel-spaced">
        @csrf
        <div class="panel-head">Install Khmer Core Subjects</div>
        <p class="subtitle">ចុចម្តងដើម្បីបញ្ចូល subject មូលដ្ឋាន: កម្មវិធីចំណេះដឹងទូទៅខ្មែរ, គណិតវិទ្យា, រូបវិទ្យា, គីមីវិទ្យា, ជីវវិទ្យា, ផែនដីវិទ្យា, ភាសាខ្មែរ...</p>
        <div class="form-grid">
            @if($userRole === 'super-admin')
                <input type="number" name="school_id" placeholder="School ID (required for super-admin)">
            @endif
            <textarea name="extra_subjects_text" rows="3" placeholder="Extra subjects (comma or new line separated)">{{ old('extra_subjects_text') }}</textarea>
        </div>
        <button type="submit" class="btn-space-top">Install Core Subjects</button>
    </form>

    <form method="GET" action="{{ route('panel.subjects.index') }}" class="panel panel-form panel-spaced">
        <div class="form-grid">
            @if($userRole === 'super-admin')
                <input type="number" name="school_id" placeholder="School ID" value="{{ $filters['school_id'] ?? '' }}">
            @endif
            <input type="text" name="name" placeholder="Subject Name" value="{{ $filters['name'] ?? '' }}">
            <input type="number" name="per_page" placeholder="Per Page" value="{{ $filters['per_page'] ?? 20 }}">
        </div>
        <button type="submit" class="btn-space-top">Filter</button>
    </form>

    <section class="panel">
        <div class="panel-head">Subjects</div>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Full Score</th>
                <th>School</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($items as $item)
                <tr>
                    <td>{{ $item['id'] }}</td>
                    <td>{{ $item['name'] }}</td>
                    <td>{{ $item['full_score'] ?? '100' }}</td>
                    <td>{{ $item['school']['name'] ?? ($item['school_id'] ?? '-') }}</td>
                    <td>
                        @can('web-manage-subjects')
                        <a href="{{ route('panel.subjects.edit', $item['id']) }}">Edit</a>
                        
                        <form action="{{ route('panel.subjects.destroy', $item['id']) }}" method="POST" class="inline-form" onsubmit="return confirm('Delete this subject?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit">Delete</button>
                        </form>
                        @endcan
                        
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">No data.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
