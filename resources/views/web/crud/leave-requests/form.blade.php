@extends('web.layouts.app')

@section('content')
    <h1 class="title">{{ $mode === 'create' ? 'Create Leave Request' : 'Edit Leave Request' }}</h1>
    <p class="subtitle">សិស្ស/អាណាព្យាបាលស្នើច្បាប់, គ្រូ និង admin ទទួលសំណើ ហើយសម្រេចអនុម័ត ឬបដិសេធ។</p>

    <div class="nav">
        <a href="{{ route('panel.leave-requests.index') }}">Back to list</a>
    </div>

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    @php
        $isEdit = $mode === 'edit';
        $isApprover = (bool) ($canApproveLeaveRequest ?? false);
        $readOnlyDetails = $isEdit && $isApprover;
        $isStudentSubmitter = !$isEdit && ($userRole ?? '') === 'student';
        $isParentSubmitter = !$isEdit && ($userRole ?? '') === 'parent';

        $selectedStudentId = (string) old('student_id', $item['student_id'] ?? ($defaultStudentId ?? ''));
        $studentSelectOptions = collect($studentOptions ?? []);
        if ($selectedStudentId !== '' && ! $studentSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedStudentId)) {
            $studentSelectOptions = $studentSelectOptions->prepend([
                'id' => (int) $selectedStudentId,
                'label' => 'Student ID: '.$selectedStudentId,
            ]);
        }

        $selectedSubjectIds = collect(old('subject_ids', $item['subject_ids'] ?? (($item['subject_id'] ?? null) ? [$item['subject_id']] : [])))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();
        $subjectSelectOptions = collect($subjectOptions ?? []);
        foreach ($selectedSubjectIds as $subjectId) {
            if (! $subjectSelectOptions->contains(fn ($option) => (int) ($option['id'] ?? 0) === $subjectId)) {
                $subjectSelectOptions = $subjectSelectOptions->prepend([
                    'id' => $subjectId,
                    'label' => 'Subject ID: '.$subjectId,
                ]);
            }
        }

        $requestType = old('request_type', $item['request_type'] ?? 'hourly');
        $subjectOptionsByStudent = $subjectOptionsByStudent ?? [];
        $subjectOptionsByStudentJson = json_encode($subjectOptionsByStudent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $selectedStudent = $studentSelectOptions->firstWhere('id', (int) $selectedStudentId);
        $selectedStudentLabel = is_array($selectedStudent) ? (string) ($selectedStudent['label'] ?? '') : '';
    @endphp

    @if(!$isEdit)
        <section class="panel panel-form panel-spaced">
            <div class="panel-head">How To Input Score / Create Leave Request</div>
            <p>1. ជ្រើសប្រភេទសុំច្បាប់: មួយថ្ងៃមានម៉ោង ឬលើសពីមួយថ្ងៃ។</p>
            <p>2. បើមួយថ្ងៃ: ជ្រើសពីម៉ោងណា ដល់ម៉ោងណា។</p>
            <p>3. បើលើសពីមួយថ្ងៃ: បញ្ចូលថ្ងៃចាប់ផ្តើម, ថ្ងៃបញ្ចប់, ចំនួនថ្ងៃឈប់ និងថ្ងៃចូលរៀនវិញ (មិនចាំបាច់ជ្រើសម៉ោង)។</p>
            <p>4. ជ្រើសមុខវិជ្ជាដែលស្នើសុំឈប់។</p>
            <p>5. បញ្ចូលមូលហេតុសុំច្បាប់។</p>
        </section>
    @endif

    <form method="POST" action="{{ $isEdit ? route('panel.leave-requests.update', $item['id']) : route('panel.leave-requests.store') }}" class="panel panel-form">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        @if($isStudentSubmitter)
            <label>Student</label>
            <input type="text" value="{{ preg_replace('/\s*-\s*ID:\s*\d+\s*$/', '', $selectedStudentLabel) ?: 'Myself' }}" disabled>
            <input type="hidden" name="student_id" value="{{ $selectedStudentId }}">
        @elseif($isParentSubmitter)
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
        @else
            <label>Student</label>
            <input type="text" value="{{ $item['student']['user']['name'] ?? $item['student_id'] }}" disabled>
        @endif

        <label>Request Type</label>
        @if($readOnlyDetails)
            <input type="text" value="{{ ($item['request_type'] ?? '') === 'multi_day' ? 'Multi Day' : 'Hourly' }}" disabled>
        @else
            <select name="request_type" id="request_type" required>
                <option value="hourly" {{ $requestType === 'hourly' ? 'selected' : '' }}>Hourly (One Day)</option>
                <option value="multi_day" {{ $requestType === 'multi_day' ? 'selected' : '' }}>Multi Day</option>
            </select>
        @endif

        <label>Subjects (Select one or more)</label>
        @if($readOnlyDetails)
            <input type="text" value="{{ collect($selectedSubjectIds)->implode(', ') }}" disabled>
        @else
            <div class="searchable-select-wrap">
                <input type="text" class="searchable-select-search" placeholder="Search subject..." data-select-search-for="subject_ids">
                <select id="subject_ids" name="subject_ids[]" multiple size="8" required>
                    @foreach($subjectSelectOptions as $option)
                        <option value="{{ $option['id'] }}" {{ in_array((int) $option['id'], $selectedSubjectIds, true) ? 'selected' : '' }}>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

        <label>Start Date</label>
        @if($readOnlyDetails)
            <input type="text" value="{{ $item['start_date'] ?? '' }}" disabled>
        @else
            <input type="date" name="start_date" value="{{ old('start_date', $item['start_date'] ?? '') }}" required>
        @endif

        <div id="hourly_fields">
            <label>Start Time</label>
            @if($readOnlyDetails)
                <input type="text" value="{{ isset($item['start_time']) ? substr((string) $item['start_time'], 0, 5) : '' }}" disabled>
            @else
                <input type="time" name="start_time" value="{{ old('start_time', isset($item['start_time']) ? substr((string) $item['start_time'], 0, 5) : '') }}">
            @endif

            <label>End Time</label>
            @if($readOnlyDetails)
                <input type="text" value="{{ isset($item['end_time']) ? substr((string) $item['end_time'], 0, 5) : '' }}" disabled>
            @else
                <input type="time" name="end_time" value="{{ old('end_time', isset($item['end_time']) ? substr((string) $item['end_time'], 0, 5) : '') }}">
            @endif
        </div>

        <div id="multi_day_fields">
            <label>End Date</label>
            @if($readOnlyDetails)
                <input type="text" value="{{ $item['end_date'] ?? '' }}" disabled>
            @else
                <input type="date" name="end_date" value="{{ old('end_date', $item['end_date'] ?? '') }}">
            @endif

            <label>Total Leave Days</label>
            @if($readOnlyDetails)
                <input type="text" value="{{ $item['total_days'] ?? '' }}" disabled>
            @else
                <input type="number" min="1" name="total_days" value="{{ old('total_days', $item['total_days'] ?? '') }}">
            @endif

            <label>Return Date</label>
            @if($readOnlyDetails)
                <input type="text" value="{{ $item['return_date'] ?? '' }}" disabled>
            @else
                <input type="date" name="return_date" value="{{ old('return_date', $item['return_date'] ?? '') }}">
            @endif
        </div>

        <label>Reason</label>
        @if($readOnlyDetails)
            <textarea rows="4" disabled>{{ old('reason', $item['reason'] ?? '') }}</textarea>
        @else
            <textarea name="reason" rows="4" required>{{ old('reason', $item['reason'] ?? '') }}</textarea>
        @endif

        @if($isEdit && $isApprover)
            <label>Status (Approval)</label>
            @php $status = old('status', $item['status'] ?? 'pending'); @endphp
            <select name="status" required>
                @foreach (['pending','approved','rejected'] as $option)
                    <option value="{{ $option }}" {{ $status === $option ? 'selected' : '' }}>{{ ucfirst($option) }}</option>
                @endforeach
            </select>
        @endif

        <button type="submit" class="btn-space-top">{{ $isEdit ? 'Update' : 'Submit Leave Request' }}</button>
    </form>

            <script>
        (function () {
            const requestType = document.getElementById('request_type');
            const hourly = document.getElementById('hourly_fields');
            const multi = document.getElementById('multi_day_fields');
            const startTime = document.querySelector('input[name="start_time"]');
            const endTime = document.querySelector('input[name="end_time"]');
            const endDate = document.querySelector('input[name="end_date"]');
            const totalDays = document.querySelector('input[name="total_days"]');
            const returnDate = document.querySelector('input[name="return_date"]');
            const studentSelect = document.getElementById('student_id');
            const subjectSelect = document.getElementById('subject_ids');
            const map = {!! $subjectOptionsByStudentJson ?: '{}' !!};

            const fillSubjects = () => {
                if (!studentSelect || !subjectSelect) {
                    return;
                }

                const studentId = String(studentSelect.value || '');
                const selectedValues = Array.from(subjectSelect.selectedOptions).map((option) => String(option.value));
                const options = Array.isArray(map[studentId]) ? map[studentId] : [];

                subjectSelect.innerHTML = '';
                options.forEach((item) => {
                    const option = document.createElement('option');
                    option.value = String(item.id);
                    option.textContent = item.label;
                    if (selectedValues.includes(option.value)) {
                        option.selected = true;
                    }
                    subjectSelect.appendChild(option);
                });
            };

            const toggle = () => {
                if (!requestType) {
                    return;
                }

                const isMulti = requestType.value === 'multi_day';
                hourly.style.display = isMulti ? 'none' : 'block';
                multi.style.display = isMulti ? 'block' : 'none';

                if (startTime) {
                    startTime.required = !isMulti;
                    startTime.disabled = isMulti;
                    if (isMulti) startTime.value = '';
                }
                if (endTime) {
                    endTime.required = !isMulti;
                    endTime.disabled = isMulti;
                    if (isMulti) endTime.value = '';
                }
                if (endDate) {
                    endDate.required = isMulti;
                    endDate.disabled = !isMulti;
                }
                if (totalDays) {
                    totalDays.required = isMulti;
                    totalDays.disabled = !isMulti;
                }
                if (returnDate) {
                    returnDate.required = isMulti;
                    returnDate.disabled = !isMulti;
                }
            };

            if (requestType) {
                requestType.addEventListener('change', toggle);
                toggle();
            }

            if (studentSelect) {
                studentSelect.addEventListener('change', fillSubjects);
                fillSubjects();
            }
        })();
    </script>
@endsection
