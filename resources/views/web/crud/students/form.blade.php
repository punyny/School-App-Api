@extends('web.layouts.app')

@section('content')
    <h1 class="title">{{ $mode === 'create' ? 'Create Student' : 'Edit Student' }}</h1>
    <p class="subtitle">Fill in the form below to save student information.</p>

    <div class="nav">
        <a href="{{ route('panel.students.index') }}">Back to list</a>
    </div>

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    @php
        $resolveImage = static function (?string $value): string {
            $url = trim((string) $value);
            if ($url === '') {
                return '';
            }

            if (\Illuminate\Support\Str::startsWith($url, ['http://', 'https://', '/', 'data:'])) {
                return $url;
            }

            return asset($url);
        };
    @endphp

    <form method="POST" action="{{ $mode === 'create' ? route('panel.students.store') : route('panel.students.update', $item['id']) }}" class="panel panel-form" enctype="multipart/form-data">
        @csrf
        @if($mode === 'edit')
            @method('PUT')
        @endif

        @if($mode === 'create')
            @if($userRole === 'super-admin')
                <label>School ID</label>
                <input type="number" name="school_id" value="{{ old('school_id', data_get($item, 'user.school_id', '')) }}" required>
            @endif

            @php
                $firstName = (string) old('first_name', data_get($item, 'user.first_name', ''));
                $lastName = (string) old('last_name', data_get($item, 'user.last_name', ''));
                $rawEnglishName = trim((string) old('name', data_get($item, 'user.name', '')));
                if ($rawEnglishName === '' && ($firstName !== '' || $lastName !== '')) {
                    $rawEnglishName = trim($firstName.' '.$lastName);
                }
                $rawKhmerName = trim((string) old('khmer_name', data_get($item, 'user.khmer_name', '')));
                $khmerParts = preg_split('/\\s+/', $rawKhmerName, 2, PREG_SPLIT_NO_EMPTY);
                $khmerFirst = (string) old('khmer_first_name', $khmerParts[0] ?? '');
                $khmerLast = (string) old('khmer_last_name', $khmerParts[1] ?? '');
                if ($rawKhmerName === '' && ($khmerFirst !== '' || $khmerLast !== '')) {
                    $rawKhmerName = trim($khmerFirst.' '.$khmerLast);
                }
            @endphp

            <label>English First Name</label>
            <input type="text" name="first_name" value="{{ $firstName }}" required>

            <label>English Last Name</label>
            <input type="text" name="last_name" value="{{ $lastName }}" required>

            <label>Khmer First Name</label>
            <input type="text" name="khmer_first_name" value="{{ $khmerFirst }}" required>

            <label>Khmer Last Name</label>
            <input type="text" name="khmer_last_name" value="{{ $khmerLast }}" required>

            <input type="hidden" name="name" id="english_full_name" value="{{ $rawEnglishName }}">
            <input type="hidden" name="khmer_name" id="khmer_full_name" value="{{ $rawKhmerName }}">

            <p class="text-muted">{{ __('ui.user_form.student_assign_in_class') }}</p>

            <p class="text-muted">Student ID will be generated automatically.</p>

            <label>Phone</label>
            <input type="text" name="phone" value="{{ old('phone', data_get($item, 'user.phone', '')) }}">

            <label>Email</label>
            <input type="email" name="email" value="{{ old('email', data_get($item, 'user.email', '')) }}" required>
            <p class="text-muted">A login link will be sent to this email.</p>

            <label>Address</label>
            <input type="text" name="address" value="{{ old('address', data_get($item, 'user.address', '')) }}">

            <label>Profile Image Upload</label>
            <input type="file" name="image" accept="{{ \App\Support\ProfileImageStorage::acceptAttribute() }}">
            <p class="text-muted">Supported: JPG, PNG, WEBP, AVIF, HEIC, HEIF. Max 10MB.</p>

            <button type="submit" class="btn-space-top">Create</button>

            <script>
                (function () {
                    var firstName = document.querySelector('[name=\"first_name\"]');
                    var lastName = document.querySelector('[name=\"last_name\"]');
                    var khmerFirst = document.querySelector('[name=\"khmer_first_name\"]');
                    var khmerLast = document.querySelector('[name=\"khmer_last_name\"]');
                    var fullName = document.getElementById('english_full_name');
                    var khmerFull = document.getElementById('khmer_full_name');

                    var buildName = function (leftInput, rightInput) {
                        var left = leftInput && leftInput.value ? leftInput.value.trim() : '';
                        var right = rightInput && rightInput.value ? rightInput.value.trim() : '';
                        return [left, right].filter(Boolean).join(' ').trim();
                    };

                    var sync = function () {
                        if (fullName) {
                            fullName.value = buildName(firstName, lastName);
                        }
                        if (khmerFull) {
                            khmerFull.value = buildName(khmerFirst, khmerLast);
                        }
                    };

                    [firstName, lastName, khmerFirst, khmerLast].forEach(function (el) {
                        if (el) {
                            el.addEventListener('input', sync);
                        }
                    });

                    sync();
                })();
            </script>
        @else
            @if($userRole === 'super-admin')
                <label>School ID</label>
                <input type="number" name="school_id" value="{{ old('school_id', $item['user']['school_id'] ?? '') }}" {{ $mode === 'edit' ? '' : 'required' }}>
            @endif

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

            <label>Class (optional during registration)</label>
            @if($classSelectOptions->isEmpty())
                <p class="flash-error">No class options found for your account yet. You can leave this empty and assign class later.</p>
                <input type="number" name="class_id" value="{{ old('class_id', $item['class_id'] ?? '') }}" placeholder="Enter class ID (optional)">
            @else
                <div class="searchable-select-wrap">
                    <input type="text" class="searchable-select-search" placeholder="Search class..." data-select-search-for="class_id">
                    <select id="class_id" name="class_id">
                        <option value="">Assign later</option>
                        @foreach($classSelectOptions as $option)
                            <option value="{{ $option['id'] }}" {{ $selectedClassId === (string) $option['id'] ? 'selected' : '' }}>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <label>Name</label>
            <input type="text" name="name" value="{{ old('name', $item['user']['name'] ?? '') }}" required>

            <label>Student ID</label>
            <input type="text" name="student_id" value="{{ old('student_id', $item['student_code'] ?? '') }}" required>

            <label>Khmer Name</label>
            <input type="text" name="khmer_name" value="{{ old('khmer_name', $item['user']['khmer_name'] ?? '') }}" required>

            <label>First Name</label>
            <input type="text" name="first_name" value="{{ old('first_name', $item['user']['first_name'] ?? '') }}">

            <label>Last Name</label>
            <input type="text" name="last_name" value="{{ old('last_name', $item['user']['last_name'] ?? '') }}">

            <label>Email</label>
            <input type="email" name="email" value="{{ old('email', $item['user']['email'] ?? '') }}" required>
            <p class="text-muted">A login link will be sent to this email.</p>

            <label>Grade</label>
            <input type="text" name="grade" value="{{ old('grade', $item['grade'] ?? '') }}">

            <label>Phone</label>
            <input type="text" name="phone" value="{{ old('phone', $item['user']['phone'] ?? '') }}">

            <label>Gender</label>
            @php $gender = old('gender', $item['user']['gender'] ?? ''); @endphp
            <select name="gender">
                <option value="">Select gender</option>
                <option value="male" {{ $gender === 'male' ? 'selected' : '' }}>Male</option>
                <option value="female" {{ $gender === 'female' ? 'selected' : '' }}>Female</option>
                <option value="other" {{ $gender === 'other' ? 'selected' : '' }}>Other</option>
            </select>

            <label>Date of Birth</label>
            <input type="date" name="dob" value="{{ old('dob', !empty($item['user']['dob']) ? \Illuminate\Support\Carbon::parse($item['user']['dob'])->toDateString() : '') }}">

            <label>Address</label>
            <input type="text" name="address" value="{{ old('address', $item['user']['address'] ?? '') }}">

            <label>Bio</label>
            <textarea name="bio" rows="3">{{ old('bio', $item['user']['bio'] ?? '') }}</textarea>

            @php
                $currentImage = old('image_url', $item['user']['image_url'] ?? '');
            @endphp

            <label>Profile Image Upload</label>
            <input type="file" name="image" accept="{{ \App\Support\ProfileImageStorage::acceptAttribute() }}">
            <p class="text-muted">Supported: JPG, PNG, WEBP, AVIF, HEIC, HEIF. Max 10MB.</p>
            @if($currentImage)
                <img src="{{ $resolveImage($currentImage) }}" alt="Current student image" class="avatar-preview">
                <label class="inline-check">
                    <input type="checkbox" name="remove_image" value="1" {{ old('remove_image') ? 'checked' : '' }}>
                    Remove current image
                </label>
            @endif

            <label>Image URL (optional)</label>
            <input type="text" name="image_url" value="{{ $currentImage }}" placeholder="/storage/profiles/example.jpg or https://...">

            <label>Parent Name</label>
            <input type="text" name="parent_name" value="{{ old('parent_name', $item['parent_name'] ?? '') }}">

            <label>Parent IDs (comma separated, e.g. 5,8)</label>
            <input type="text" name="parent_ids" value="{{ old('parent_ids', isset($item['parents']) ? collect($item['parents'])->pluck('id')->implode(',') : '') }}">

            <button type="submit" class="btn-space-top">Update</button>
        @endif
    </form>
@endsection
