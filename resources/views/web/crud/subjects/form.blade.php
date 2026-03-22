@extends('web.layouts.app')

@section('content')
    <h1 class="title">{{ $mode === 'create' ? 'Create Subject' : 'Edit Subject' }}</h1>
    <p class="subtitle">Fill in the form below to save subject information.</p>

    <div class="nav">
        <a href="{{ route('panel.subjects.index') }}">Back to list</a>
    </div>

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ $mode === 'create' ? route('panel.subjects.store') : route('panel.subjects.update', $item['id']) }}" class="panel panel-form">
        @csrf
        @if($mode === 'edit')
            @method('PUT')
        @endif

        @if($userRole === 'super-admin')
            <label>School ID</label>
            <input type="number" name="school_id" value="{{ old('school_id', $item['school_id'] ?? '') }}" required>
        @endif

        <label>Name</label>
        <input type="text" name="name" value="{{ old('name', $item['name'] ?? '') }}" required>

        <label>Full Score (ពិន្ទុពេញ)</label>
        <input type="number" name="full_score" step="0.01" min="1" max="1000" value="{{ old('full_score', $item['full_score'] ?? 100) }}" required>

        <button type="submit" class="btn-space-top">{{ $mode === 'create' ? 'Create' : 'Update' }}</button>
    </form>
@endsection
