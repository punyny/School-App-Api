@extends('web.layouts.app')

@section('content')
    <h1 class="title">{{ $mode === 'create' ? 'Create Incident Report' : 'Edit Incident Report' }}</h1>
    <p class="subtitle">Data will be submitted to real API endpoint.</p>

    <div class="nav">
        <a href="{{ route('panel.incident-reports.index') }}">Back to list</a>
    </div>

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ $mode === 'create' ? route('panel.incident-reports.store') : route('panel.incident-reports.update', $item['id']) }}" class="panel panel-form">
        @csrf
        @if($mode === 'edit')
            @method('PUT')
        @endif

        @php
            $selectedStudentId = (string) old('student_id', $item['student_id'] ?? '');
            $studentSelectOptions = collect($studentOptions ?? []);

            if ($selectedStudentId !== '' && ! $studentSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedStudentId)) {
                $studentSelectOptions = $studentSelectOptions->prepend([
                    'id' => (int) $selectedStudentId,
                    'label' => 'Student ID: '.$selectedStudentId,
                ]);
            }
        @endphp

        <label>Student</label>
        <div class="searchable-select-wrap">
            <input type="text" class="searchable-select-search" placeholder="Search student..." data-select-search-for="student_id">
            <select id="student_id" name="student_id" required>
                <option value="">Select student</option>
                @foreach($studentSelectOptions as $option)
                    <option value="{{ $option['id'] }}" {{ $selectedStudentId === (string) $option['id'] ? 'selected' : '' }}>
                        {{ $option['label'] }}
                    </option>
                @endforeach
            </select>
        </div>

        <label>Description</label>
        <textarea name="description" rows="5" required>{{ old('description', $item['description'] ?? '') }}</textarea>

        <label>Date</label>
        <input type="date" name="date" value="{{ old('date', $item['date'] ?? '') }}">

        <label>Type</label>
        <input type="text" name="type" value="{{ old('type', $item['type'] ?? '') }}">

        <label>Acknowledged</label>
        @php $ack = old('acknowledged', isset($item['acknowledged']) ? (int) $item['acknowledged'] : 0); @endphp
        <select name="acknowledged">
            <option value="0" {{ (string) $ack === '0' ? 'selected' : '' }}>No</option>
            <option value="1" {{ (string) $ack === '1' ? 'selected' : '' }}>Yes</option>
        </select>

        <button type="submit" class="btn-space-top">{{ $mode === 'create' ? 'Create' : 'Update' }}</button>
    </form>
@endsection
