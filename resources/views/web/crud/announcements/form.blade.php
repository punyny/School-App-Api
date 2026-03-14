@extends('web.layouts.app')

@section('content')
    <h1 class="title">{{ $mode === 'create' ? 'Create Announcement' : 'Edit Announcement' }}</h1>
    <p class="subtitle">Broadcast style: all school, by role, by class, or direct user (Telegram-style one-to-many / one-to-one).</p>

    <div class="nav">
        <a href="{{ route('panel.announcements.index') }}">Back to list</a>
    </div>

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ $mode === 'create' ? route('panel.announcements.store') : route('panel.announcements.update', $item['id']) }}" class="panel panel-form" enctype="multipart/form-data">
        @csrf
        @if($mode === 'edit')
            @method('PUT')
        @endif

        @if($userRole === 'super-admin')
            <label>School ID</label>
            <input type="number" name="school_id" value="{{ old('school_id', $item['school_id'] ?? '') }}">
        @endif

        @php
            $selectedTargetRole = (string) old('target_role', $item['target_role'] ?? '');
            $selectedTargetUserId = (string) old('target_user_id', $item['target_user_id'] ?? '');
            $targetUserSelectOptions = collect($targetUserOptions ?? []);

            if ($selectedTargetUserId !== '' && ! $targetUserSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedTargetUserId)) {
                $targetUserSelectOptions = $targetUserSelectOptions->prepend([
                    'id' => (int) $selectedTargetUserId,
                    'label' => 'User ID: '.$selectedTargetUserId,
                ]);
            }
        @endphp

        <label>Target Role (optional)</label>
        <select name="target_role" id="target_role">
            <option value="">All roles (if no class / no user)</option>
            <option value="teacher" {{ $selectedTargetRole === 'teacher' ? 'selected' : '' }}>Teacher</option>
            <option value="student" {{ $selectedTargetRole === 'student' ? 'selected' : '' }}>Student</option>
            <option value="parent" {{ $selectedTargetRole === 'parent' ? 'selected' : '' }}>Parent</option>
        </select>

        <label>Target User (optional)</label>
        <div class="searchable-select-wrap">
            <input type="text" class="searchable-select-search" placeholder="Search target user..." data-select-search-for="target_user_id">
            <select id="target_user_id" name="target_user_id">
                <option value="">No specific user</option>
                @foreach($targetUserSelectOptions as $option)
                    <option value="{{ $option['id'] }}" {{ $selectedTargetUserId === (string) $option['id'] ? 'selected' : '' }}>
                        {{ $option['label'] }}
                    </option>
                @endforeach
            </select>
        </div>
        <p class="text-muted">Use one target mode only: Target Role OR Target User OR Class. Leave all empty for whole school.</p>

        @php
            $selectedClassId = (string) old('class_id', $item['class_id'] ?? '');
            $classSelectOptions = collect($classOptions ?? []);

            if ($selectedClassId !== '' && ! $classSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedClassId)) {
                $classSelectOptions = $classSelectOptions->prepend([
                    'id' => (int) $selectedClassId,
                    'label' => 'Class ID: '.$selectedClassId,
                ]);
            }
        @endphp

        <label>Class (optional)</label>
        <div class="searchable-select-wrap">
            <input type="text" class="searchable-select-search" placeholder="Search class..." data-select-search-for="class_id">
            <select id="class_id" name="class_id">
                <option value="">All classes / general announcement</option>
                @foreach($classSelectOptions as $option)
                    <option value="{{ $option['id'] }}" {{ $selectedClassId === (string) $option['id'] ? 'selected' : '' }}>
                        {{ $option['label'] }}
                    </option>
                @endforeach
            </select>
        </div>

        <label>Title</label>
        <input type="text" name="title" value="{{ old('title', $item['title'] ?? '') }}" required>

        <label>Content</label>
        <textarea name="content" rows="5" required>{{ old('content', $item['content'] ?? '') }}</textarea>

        <label>Date</label>
        <input type="date" name="date" value="{{ old('date', $item['date'] ?? '') }}">

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

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var classSelect = document.getElementById('class_id');
            var roleSelect = document.getElementById('target_role');
            var userSelect = document.getElementById('target_user_id');

            if (!classSelect || !roleSelect || !userSelect) {
                return;
            }

            roleSelect.addEventListener('change', function () {
                if ((roleSelect.value || '') !== '') {
                    classSelect.value = '';
                    userSelect.value = '';
                }
            });

            userSelect.addEventListener('change', function () {
                if ((userSelect.value || '') !== '') {
                    classSelect.value = '';
                    roleSelect.value = '';
                }
            });

            classSelect.addEventListener('change', function () {
                if ((classSelect.value || '') !== '') {
                    roleSelect.value = '';
                    userSelect.value = '';
                }
            });
        });
    </script>
@endpush
