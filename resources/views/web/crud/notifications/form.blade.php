@extends('web.layouts.app')

@section('content')
    <h1 class="title">{{ $mode === 'create' ? 'Create Notification' : 'Edit Notification' }}</h1>
    <p class="subtitle">Data will be submitted to real API endpoint.</p>

    <div class="nav">
        <a href="{{ route('panel.notifications.index') }}">Back to list</a>
    </div>

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ $mode === 'create' ? route('panel.notifications.store') : route('panel.notifications.update', $item['id']) }}" class="panel panel-form">
        @csrf
        @if($mode === 'edit')
            @method('PUT')
        @endif

        <label>User ID</label>
        <input type="number" name="user_id" value="{{ old('user_id', $item['user_id'] ?? '') }}" required>

        <label>Title</label>
        <input type="text" name="title" value="{{ old('title', $item['title'] ?? '') }}" required>

        <label>Content</label>
        <textarea name="content" rows="5" required>{{ old('content', $item['content'] ?? '') }}</textarea>

        <label>Date/Time (optional)</label>
        <input type="datetime-local" name="date" value="{{ old('date', isset($item['date']) ? str_replace(' ', 'T', substr((string) $item['date'], 0, 16)) : '') }}">

        <label>Read Status</label>
        @php $readStatus = old('read_status', isset($item['read_status']) ? (int) $item['read_status'] : 0); @endphp
        <select name="read_status">
            <option value="0" {{ (string) $readStatus === '0' ? 'selected' : '' }}>Unread</option>
            <option value="1" {{ (string) $readStatus === '1' ? 'selected' : '' }}>Read</option>
        </select>

        <button type="submit" class="btn-space-top">{{ $mode === 'create' ? 'Create' : 'Update' }}</button>
    </form>
@endsection
