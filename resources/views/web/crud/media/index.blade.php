@extends('web.layouts.app')

@section('content')
    <h1 class="title">Media Library</h1>
    <p class="subtitle">Uploaded files from profiles, homework, and announcements.</p>

    <div class="nav">
        <a href="{{ route('dashboard') }}">Dashboard</a>
    </div>

    @if (session('success'))
        <p class="flash-success">{{ session('success') }}</p>
    @endif

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    <form method="GET" action="{{ route('panel.media.index') }}" class="panel panel-form panel-spaced">
        <div class="form-grid">
            @if($userRole === 'super-admin')
                <input type="number" name="school_id" placeholder="School ID" value="{{ $filters['school_id'] ?? '' }}">
            @endif
            <input type="text" name="category" placeholder="Category (profile, attachment)" value="{{ $filters['category'] ?? '' }}">
            <input type="text" name="mediable_type" placeholder="Model class" value="{{ $filters['mediable_type'] ?? '' }}">
            <input type="number" name="mediable_id" placeholder="Model ID" value="{{ $filters['mediable_id'] ?? '' }}">
            <input type="number" name="per_page" placeholder="Per Page" value="{{ $filters['per_page'] ?? 20 }}">
        </div>
        <button type="submit" class="btn-space-top">Filter</button>
    </form>

    <section class="panel">
        <div class="panel-head">Media Items</div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>File</th>
                    <th>Category</th>
                    <th>Model</th>
                    <th>School</th>
                    <th>Uploaded By</th>
                    <th>Size</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr>
                        <td>{{ $item['id'] }}</td>
                        <td>
                            <a href="{{ $item['url'] }}" target="_blank" rel="noreferrer">{{ $item['original_name'] }}</a>
                            <div class="text-muted">{{ $item['mime_type'] ?? '-' }}</div>
                        </td>
                        <td>{{ $item['category'] }}</td>
                        <td>
                            <div>{{ class_basename($item['mediable_type'] ?? '-') }}</div>
                            <div class="text-muted">#{{ $item['mediable_id'] ?? '-' }}</div>
                        </td>
                        <td>{{ $item['school']['name'] ?? ($item['school_id'] ?? '-') }}</td>
                        <td>{{ $item['uploaded_by']['name'] ?? ($item['uploaded_by_user_id'] ?? '-') }}</td>
                        <td>{{ isset($item['size_bytes']) ? number_format(((int) $item['size_bytes']) / 1024, 1).' KB' : '-' }}</td>
                        <td>
                            <a href="{{ $item['url'] }}" target="_blank" rel="noreferrer">Open</a>
                            <form action="{{ route('panel.media.destroy', $item['id']) }}" method="POST" class="inline-form" onsubmit="return confirm('Delete this media file?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8">No media found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection
