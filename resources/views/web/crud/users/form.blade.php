@extends('web.layouts.app')

@section('content')
    <h1 class="title">{{ $mode === 'create' ? __('ui.user_form.title_create') : __('ui.user_form.title_edit') }}</h1>
    <p class="subtitle">{{ __('ui.user_form.subtitle') }}</p>

    <div class="nav">
        <a href="{{ route('panel.users.index') }}">{{ __('ui.common.back_to_list') }}</a>
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

        $schoolSelectOptions = collect($schoolOptions ?? []);
        $selectedSchoolId = (string) old('school_id', $item['school_id'] ?? request()->query('school_id', ''));

        if ($selectedSchoolId !== '' && ! $schoolSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedSchoolId)) {
            $schoolSelectOptions = $schoolSelectOptions->prepend([
                'id' => (int) $selectedSchoolId,
                'label' => 'School ID: '.$selectedSchoolId,
            ]);
        }

        $selectedClassId = (string) old('class_id', $item['student_profile']['class_id'] ?? '');
        $classSelectOptions = collect($classOptions ?? []);

        if ($selectedClassId !== '' && ! $classSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedClassId)) {
            $classSelectOptions = $classSelectOptions->prepend([
                'id' => (int) $selectedClassId,
                'label' => 'Class ID: '.$selectedClassId,
                'school_id' => $selectedSchoolId !== '' ? (int) $selectedSchoolId : null,
            ]);
        }

        $currentRole = old('role', $item['role'] ?? ($defaultRole ?? 'teacher'));
        $currentImage = old('image_url', $item['image_url'] ?? '');
        $selectedChildIds = collect(old('child_ids', isset($item['children']) ? collect($item['children'])->pluck('id')->all() : []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();
        $studentSelectOptions = collect($studentOptions ?? []);
    @endphp

    <form method="POST" action="{{ $mode === 'create' ? route('panel.users.store') : route('panel.users.update', $item['id']) }}" class="panel panel-form" enctype="multipart/form-data" id="user-form">
        @csrf
        @if($mode === 'edit')
            @method('PUT')
        @endif

        @if($userRole === 'super-admin')
            <div class="form-grid-wide" id="school-selection-grid">
                <div>
                    <label>{{ __('ui.user_form.select_existing_school') }}</label>
                    <div class="searchable-select-wrap">
                        <input type="text" class="searchable-select-search" placeholder="{{ __('ui.user_form.search_school_placeholder') }}" data-select-search-for="school_id">
                        <select name="school_id" id="school_id_select">
                            <option value="">{{ __('ui.user_form.school_unassigned_option') }}</option>
                            @foreach($schoolSelectOptions as $option)
                                <option value="{{ $option['id'] }}" {{ $selectedSchoolId === (string) $option['id'] ? 'selected' : '' }}>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <p class="text-muted" style="margin:8px 0 0;">
                        {{ __('ui.user_form.school_dropdown_hint') }}
                    </p>
                </div>
            </div>
        @endif

        <label>{{ __('ui.user_form.role') }}</label>
        <select name="role" id="role-select" required>
            @foreach (($roleOptions ?? ['teacher', 'student', 'parent']) as $role)
                @php
                    $roleLabel = match ($role) {
                        'super-admin' => __('ui.role.super_admin'),
                        'admin' => __('ui.role.admin'),
                        'teacher' => __('ui.role.teacher'),
                        'student' => __('ui.role.student'),
                        'parent' => __('ui.role.parent'),
                        default => $role,
                    };
                @endphp
                <option value="{{ $role }}" {{ $currentRole === $role ? 'selected' : '' }}>{{ $roleLabel }}</option>
            @endforeach
        </select>

        <section class="panel panel-form panel-spaced" data-role-block="admin" style="margin-top:12px;">
            <div class="panel-head">{{ __('ui.user_form.quick_create_admin') }}</div>
            <div class="form-grid-wide">
                <div>
                    <label>{{ __('ui.user_form.admin_name') }}</label>
                    <input type="text" name="admin_name" id="admin_name" value="{{ old('admin_name', $item['name'] ?? '') }}" placeholder="EX: School Admin">
                </div>
                @if($mode === 'edit')
                    <div>
                        <label>{{ __('ui.user_form.admin_id') }}</label>
                        <input type="text" name="admin_id" id="admin_id" value="{{ old('admin_id', $item['user_code'] ?? '') }}" placeholder="EX: ADM-0001">
                    </div>
                @endif
            </div>
            <p class="text-muted" style="margin:8px 0 0;">{{ __('ui.user_form.admin_hint') }}</p>
        </section>

        <section data-role-block="non-admin">
            <div class="panel-head" style="margin:12px 0 8px;">{{ __('ui.user_form.basic_information') }}</div>
            @if($mode === 'edit')
                <label>{{ __('ui.user_form.user_id_code') }}</label>
                <input type="text" name="user_code" value="{{ old('user_code', $item['user_code'] ?? '') }}" placeholder="EX: TCH-0001 / STD-0001">
            @else
                <p class="text-muted" style="margin:0 0 8px;">ID will be generated automatically.</p>
            @endif

            <label>{{ __('ui.user_form.first_name') }}</label>
            <input type="text" name="first_name" value="{{ old('first_name', $item['first_name'] ?? '') }}">

            <label>{{ __('ui.user_form.last_name') }}</label>
            <input type="text" name="last_name" value="{{ old('last_name', $item['last_name'] ?? '') }}">

            <label>{{ __('ui.user_form.khmer_name') }}</label>
            <input type="text" name="khmer_name" value="{{ old('khmer_name', $item['khmer_name'] ?? '') }}">

            @if($mode === 'edit')
                <label>{{ __('ui.user_form.name') }}</label>
                <input type="text" name="name" id="name-field" value="{{ old('name', $item['name'] ?? '') }}">
            @endif
        </section>

        <label>{{ __('ui.user_form.email') }}</label>
        <input type="email" name="email" value="{{ old('email', $item['email'] ?? '') }}" required>
        <p class="text-muted" style="margin:8px 0 12px;">A login link will be sent to this email.</p>

        <label>{{ __('ui.user_form.phone') }}</label>
        <input type="text" name="phone" id="phone-field" value="{{ old('phone', $item['phone'] ?? '') }}">

        <section data-role-block="non-admin">
            <label>{{ __('ui.user_form.gender') }}</label>
            @php $gender = old('gender', $item['gender'] ?? ''); @endphp
            <select name="gender">
                <option value="">{{ __('ui.common.select_gender') }}</option>
                <option value="male" {{ $gender === 'male' ? 'selected' : '' }}>{{ __('ui.common.male') }}</option>
                <option value="female" {{ $gender === 'female' ? 'selected' : '' }}>{{ __('ui.common.female') }}</option>
                <option value="other" {{ $gender === 'other' ? 'selected' : '' }}>{{ __('ui.common.other') }}</option>
            </select>

            <label>{{ __('ui.user_form.dob') }}</label>
            <input type="date" name="dob" value="{{ old('dob', isset($item['dob']) ? \Illuminate\Support\Carbon::parse($item['dob'])->toDateString() : '') }}">

            <label>{{ __('ui.user_form.address') }}</label>
            <input type="text" name="address" value="{{ old('address', $item['address'] ?? '') }}">

            <label>{{ __('ui.user_form.bio') }}</label>
            <textarea name="bio" rows="3">{{ old('bio', $item['bio'] ?? '') }}</textarea>

            <label>{{ __('ui.user_form.profile_image_upload') }}</label>
            <input type="file" name="image" accept="{{ \App\Support\ProfileImageStorage::acceptAttribute() }}">
            <p class="text-muted">{{ __('ui.common.supported_image_hint') }}</p>
            @if($currentImage)
                <img src="{{ $resolveImage($currentImage) }}" alt="Current profile image" class="avatar-preview">
                <label class="inline-check">
                    <input type="checkbox" name="remove_image" value="1" {{ old('remove_image') ? 'checked' : '' }}>
                    {{ __('ui.common.remove_current_image') }}
                </label>
            @endif

            <label>{{ __('ui.common.image_url_optional') }}</label>
            <input type="text" name="image_url" value="{{ $currentImage }}" placeholder="/storage/profiles/example.jpg or https://...">
        </section>

        <label>{{ __('ui.user_form.active') }}</label>
        @php $active = old('active', isset($item['active']) ? (int) $item['active'] : 1); @endphp
        <select name="active">
            <option value="1" {{ (string) $active === '1' ? 'selected' : '' }}>{{ __('ui.common.yes') }}</option>
            <option value="0" {{ (string) $active === '0' ? 'selected' : '' }}>{{ __('ui.common.no') }}</option>
        </select>

        <section data-role-block="student" style="margin-top:12px;">
            <div class="panel panel-form panel-spaced">
                <div class="panel-head">{{ __('ui.user_form.student_profile') }}</div>

                @if($mode === 'edit')
                    <label>{{ __('ui.user_form.student_class_optional') }}</label>
                    <div class="searchable-select-wrap">
                        <input type="text" class="searchable-select-search" placeholder="{{ __('ui.common.search_class') }}" data-select-search-for="class_id">
                        <select id="class_id" name="class_id">
                            <option value="">{{ __('ui.common.assign_later') }}</option>
                            @foreach($classSelectOptions as $option)
                                <option value="{{ $option['id'] }}" data-school-id="{{ $option['school_id'] ?? '' }}" {{ $selectedClassId === (string) $option['id'] ? 'selected' : '' }}>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <p class="text-muted" style="margin:0 0 8px;">{{ __('ui.user_form.student_assign_in_class') }}</p>
                @endif

                <label>{{ __('ui.user_form.student_id') }}</label>
                @if($mode === 'edit')
                    <input type="text" name="student_id" value="{{ old('student_id', $item['student_profile']['student_code'] ?? '') }}" placeholder="EX: STU-0001">
                @else
                    <p class="text-muted" style="margin:0 0 8px;">Student ID will be generated automatically.</p>
                @endif

                <label>{{ __('ui.user_form.student_grade') }}</label>
                <input type="text" name="grade" value="{{ old('grade', $item['student_profile']['grade'] ?? '') }}">

                <label>{{ __('ui.user_form.parent_name') }}</label>
                <input type="text" name="parent_name" value="{{ old('parent_name', $item['student_profile']['parent_name'] ?? '') }}">

                <label>{{ __('ui.user_form.parent_ids_comma') }}</label>
                <input type="text" name="parent_ids" value="{{ old('parent_ids', isset($item['student_profile']['parents']) ? collect($item['student_profile']['parents'])->pluck('id')->implode(',') : '') }}">
            </div>
        </section>

        <section data-role-block="parent" style="margin-top:12px;">
            <div class="panel panel-form panel-spaced">
                <div class="panel-head">{{ __('ui.user_form.parent_profile') }}</div>
                <p class="text-muted" style="margin:0 0 12px;">
                    {{ __('ui.user_form.parent_hint') }}
                </p>

                <label>{{ __('ui.user_form.linked_children') }}</label>
                <div class="searchable-select-wrap">
                    <input type="text" class="searchable-select-search" placeholder="{{ __('ui.common.search_child') }}" data-select-search-for="child_ids">
                    <select id="child_ids" name="child_ids[]" multiple size="8">
                        @foreach($studentSelectOptions as $option)
                            <option value="{{ $option['id'] }}" data-school-id="{{ $option['school_id'] ?? '' }}" {{ in_array((int) $option['id'], $selectedChildIds, true) ? 'selected' : '' }}>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <p class="text-muted" style="margin:8px 0 0;">{{ __('ui.user_form.linked_children_hint') }}</p>
            </div>
        </section>

        <section data-role-block="teacher" style="margin-top:12px;">
            <div class="panel panel-form panel-spaced">
                <div class="panel-head">{{ __('ui.user_form.teacher_profile') }}</div>
                <p class="text-muted" style="margin:0;">
                    {{ __('ui.user_form.teacher_hint') }}
                </p>
            </div>
        </section>

        <button type="submit" class="btn-space-top">{{ $mode === 'create' ? __('ui.common.create') : __('ui.common.update') }}</button>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var roleSelect = document.getElementById('role-select');
            var form = document.getElementById('user-form');
            if (!roleSelect || !form) {
                return;
            }

            var adminBlocks = form.querySelectorAll('[data-role-block="admin"]');
            var nonAdminBlocks = form.querySelectorAll('[data-role-block="non-admin"]');
            var studentBlocks = form.querySelectorAll('[data-role-block="student"]');
            var parentBlocks = form.querySelectorAll('[data-role-block="parent"]');
            var teacherBlocks = form.querySelectorAll('[data-role-block="teacher"]');

            var adminName = document.getElementById('admin_name');
            var adminId = document.getElementById('admin_id');
            var phoneField = document.getElementById('phone-field');
            var nameField = document.getElementById('name-field');
            var classField = document.getElementById('class_id');
            var childField = document.getElementById('child_ids');
            var schoolSelect = document.getElementById('school_id_select');

            var applySchoolScope = function () {
                var schoolId = schoolSelect ? schoolSelect.value : '';
                var mustFilterBySchool = !!schoolSelect && roleSelect.value !== 'super-admin';
                [classField, childField].forEach(function (selectElement) {
                    if (!selectElement) {
                        return;
                    }

                    var isMultiple = selectElement.multiple === true;
                    var options = Array.prototype.slice.call(selectElement.options);

                    options.forEach(function (option) {
                        if (option.value === '') {
                            option.hidden = false;
                            option.disabled = false;
                            return;
                        }

                        var optionSchoolId = (option.dataset.schoolId || '').trim();
                        var visible = true;
                        if (mustFilterBySchool) {
                            visible = schoolId !== '' && optionSchoolId !== '' && optionSchoolId === schoolId;
                        }
                        option.hidden = !visible;
                        option.disabled = !visible;
                    });

                    if (isMultiple) {
                        options.forEach(function (option) {
                            if (option.disabled && option.selected) {
                                option.selected = false;
                            }
                        });
                        return;
                    }

                    if (selectElement.selectedOptions.length > 0 && selectElement.selectedOptions[0].disabled) {
                        selectElement.value = '';
                    }
                });
            };

            var toggleRoleSections = function () {
                var role = roleSelect.value;
                var isAdminRole = role === 'admin';
                var isStudentRole = role === 'student';
                var isParentRole = role === 'parent';
                var isTeacherRole = role === 'teacher';
                var isSuperAdminRole = role === 'super-admin';

                adminBlocks.forEach(function (node) {
                    node.style.display = isAdminRole ? '' : 'none';
                });

                nonAdminBlocks.forEach(function (node) {
                    node.style.display = isAdminRole ? 'none' : '';
                });

                studentBlocks.forEach(function (node) {
                    node.style.display = isStudentRole ? '' : 'none';
                });

                parentBlocks.forEach(function (node) {
                    node.style.display = isParentRole ? '' : 'none';
                });

                teacherBlocks.forEach(function (node) {
                    node.style.display = isTeacherRole ? '' : 'none';
                });

                if (adminName) {
                    adminName.required = isAdminRole;
                }
                if (adminId) {
                    adminId.required = isAdminRole;
                }
                if (phoneField) {
                    phoneField.required = isAdminRole;
                }
                if (nameField) {
                    nameField.required = !isAdminRole;
                }
                if (classField) {
                    classField.required = false;
                }
                if (childField) {
                    childField.required = false;
                }

                if (schoolSelect) {
                    schoolSelect.required = !isSuperAdminRole;
                    schoolSelect.disabled = isSuperAdminRole;
                    var schoolWrap = schoolSelect.closest('.searchable-select-wrap');
                    if (schoolWrap) {
                        schoolWrap.style.opacity = isSuperAdminRole ? '0.65' : '1';
                    }
                    if (isSuperAdminRole) {
                        schoolSelect.value = '';
                    }
                    applySchoolScope();
                }
            };

            roleSelect.addEventListener('change', toggleRoleSections);
            if (schoolSelect) {
                schoolSelect.addEventListener('change', applySchoolScope);
            }

            toggleRoleSections();
            applySchoolScope();
        });
    </script>
@endpush
