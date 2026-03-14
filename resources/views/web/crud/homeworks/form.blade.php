@extends('web.layouts.app')

@section('content')
    <h1 class="title">{{ $mode === 'create' ? 'Create Homework' : 'Edit Homework' }}</h1>
    <p class="subtitle">Only teacher can assign homework for their own class and subject assignment.</p>

    <div class="nav">
        <a href="{{ route('panel.homeworks.index') }}">Back to list</a>
    </div>

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ $mode === 'create' ? route('panel.homeworks.store') : route('panel.homeworks.update', $item['id']) }}" class="panel panel-form" enctype="multipart/form-data">
        @csrf
        @if($mode === 'edit')
            @method('PUT')
        @endif

        @php
            $selectedClassId = (string) old('class_id', $item['class_id'] ?? '');
            $selectedSubjectId = (string) old('subject_id', $item['subject_id'] ?? '');
            $selectedDueTime = (string) old(
                'due_time',
                isset($item['due_time']) && is_string($item['due_time']) ? substr($item['due_time'], 0, 5) : ''
            );
            $classSelectOptions = collect($classOptions ?? []);
            $subjectSelectOptions = collect($subjectOptions ?? []);

            if ($selectedClassId !== '' && ! $classSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedClassId)) {
                $classSelectOptions = $classSelectOptions->prepend([
                    'id' => (int) $selectedClassId,
                    'label' => 'Class ID: '.$selectedClassId,
                ]);
            }

            if ($selectedSubjectId !== '' && ! $subjectSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedSubjectId)) {
                $subjectSelectOptions = $subjectSelectOptions->prepend([
                    'id' => (int) $selectedSubjectId,
                    'label' => 'Subject ID: '.$selectedSubjectId,
                ]);
            }
        @endphp

        <label>Class</label>
        <div class="searchable-select-wrap">
            <input type="text" class="searchable-select-search" placeholder="Search class..." data-select-search-for="class_id">
            <select id="class_id" name="class_id" required>
                <option value="">Select class</option>
                @foreach($classSelectOptions as $option)
                    <option value="{{ $option['id'] }}" {{ $selectedClassId === (string) $option['id'] ? 'selected' : '' }}>
                        {{ $option['label'] }}
                    </option>
                @endforeach
            </select>
        </div>

        <label>Subject</label>
        <div class="searchable-select-wrap">
            <input type="text" class="searchable-select-search" placeholder="Search subject..." data-select-search-for="subject_id">
            <select id="subject_id" name="subject_id" required>
                <option value="">Select subject</option>
                @foreach($subjectSelectOptions as $option)
                    <option value="{{ $option['id'] }}" {{ $selectedSubjectId === (string) $option['id'] ? 'selected' : '' }}>
                        {{ $option['label'] }}
                    </option>
                @endforeach
            </select>
        </div>

        <label>Title</label>
        <input type="text" name="title" value="{{ old('title', $item['title'] ?? '') }}" required>

        <label>Question</label>
        <textarea name="question" rows="4">{{ old('question', $item['question'] ?? '') }}</textarea>

        <label>Due Date</label>
        <input type="date" name="due_date" value="{{ old('due_date', $item['due_date'] ?? '') }}">

        <label>Due Time</label>
        <input type="time" name="due_time" step="60" value="{{ $selectedDueTime }}">

        <label>File Attachments (comma separated URLs)</label>
        <input type="text" name="file_attachments" value="{{ old('file_attachments', isset($item['file_attachments']) ? collect($item['file_attachments'])->implode(',') : '') }}">

        <label>Upload Files</label>
        <input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx">

        @php
            $uploadedMedia = collect($item['media'] ?? [])->filter(fn ($media) => ($media['category'] ?? '') === 'attachment');
        @endphp
        @if($uploadedMedia->isNotEmpty())
            <div class="upload-hints">
                @foreach($uploadedMedia as $media)
                    <a href="{{ $media['url'] ?? '#' }}" target="_blank" rel="noreferrer">{{ $media['original_name'] ?? 'Attachment' }}</a>
                @endforeach
            </div>
        @endif

        <button type="submit" class="btn-space-top">{{ $mode === 'create' ? 'Create' : 'Update' }}</button>
    </form>
@endsection
