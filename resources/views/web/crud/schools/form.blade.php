@extends('web.layouts.app')

@section('content')
    <h1 class="title">{{ $mode === 'create' ? 'Create School' : 'Edit School' }}</h1>
    <p class="subtitle">You can enter config details as JSON or plain text notes.</p>

    <div class="nav">
        <a href="{{ route('panel.schools.index') }}">Back to list</a>
    </div>

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ $mode === 'create' ? route('panel.schools.store') : route('panel.schools.update', $item['id']) }}" class="panel panel-form">
        @csrf
        @if($mode === 'edit')
            @method('PUT')
        @endif

        <label>Name</label>
        <input type="text" name="name" value="{{ old('name', $item['name'] ?? '') }}" required>

        <label>School Code</label>
        <input type="text" name="school_code" value="{{ old('school_code', $item['school_code'] ?? '') }}" placeholder="EX: DEMO-001">

        <label>Location</label>
        <input type="text" name="location" value="{{ old('location', $item['location'] ?? '') }}">

        <label>Config Details / Notes (optional)</label>
        <textarea name="config_details" rows="8" placeholder='Example JSON: {"timezone":"Asia/Phnom_Penh"}&#10;Or plain text: Dewey international school'>{{ old('config_details', isset($item['config_details']) ? json_encode($item['config_details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '') }}</textarea>

        <button type="submit" class="btn-space-top">{{ $mode === 'create' ? 'Create' : 'Update' }}</button>
    </form>
@endsection
