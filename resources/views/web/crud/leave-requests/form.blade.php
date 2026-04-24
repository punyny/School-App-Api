@extends('web.layouts.app')

@section('content')
    <style>
        .leave-request-shell {
            display: grid;
            gap: 14px;
        }

        .leave-guide {
            background: linear-gradient(145deg, #ffffff 0%, #f5fbf8 100%);
        }

        .leave-guide-head {
            font-size: 14px;
            font-weight: 800;
            color: #134e4a;
            margin-bottom: 10px;
        }

        .leave-guide-grid {
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        }

        .leave-guide-item {
            border: 1px solid #d6e8e2;
            border-radius: 12px;
            background: #ffffff;
            padding: 12px;
            min-height: 100px;
        }

        .leave-guide-step {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 999px;
            background: #0f766e;
            color: #ffffff;
            font-size: 12px;
            font-weight: 800;
            margin-bottom: 7px;
        }

        .leave-guide-item h3 {
            margin: 0 0 4px;
            font-size: 13px;
            color: #11423f;
        }

        .leave-guide-item p {
            margin: 0;
            font-size: 12px;
            color: #5b6b65;
            line-height: 1.55;
        }

        .leave-form {
            padding: 0;
            overflow: hidden;
        }

        .leave-form-section {
            padding: 16px;
            border-bottom: 1px solid #e7f0ec;
        }

        .leave-form-section:last-child {
            border-bottom: none;
        }

        .leave-form-section-head {
            margin: 0 0 10px;
            font-size: 15px;
            font-weight: 800;
            color: #143f3a;
        }

        .leave-form-section-note {
            margin: -3px 0 12px;
            font-size: 12px;
            color: #687a73;
        }

        .leave-field-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }

        .leave-field-wide {
            grid-column: 1 / -1;
        }

        .leave-helper {
            margin: 6px 2px 0;
            font-size: 11px;
            color: #6b7e76;
            line-height: 1.45;
        }

        .leave-type-picker {
            display: grid;
            gap: 8px;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            margin-top: 6px;
        }

        .leave-type-option {
            border: 1px solid #d5e4df;
            border-radius: 12px;
            background: #ffffff;
            padding: 10px 11px;
            cursor: pointer;
            transition: .18s ease;
            display: block;
        }

        .leave-type-option:hover {
            border-color: #8bc1b8;
            box-shadow: 0 6px 14px rgba(15, 118, 110, 0.12);
        }

        .leave-type-option.is-active {
            border-color: #0f766e;
            background: linear-gradient(145deg, #f0fbf8 0%, #ffffff 100%);
            box-shadow: 0 8px 18px rgba(15, 118, 110, 0.14);
        }

        .leave-type-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .leave-type-title {
            display: block;
            margin-bottom: 3px;
            font-size: 13px;
            font-weight: 700;
            color: #12443f;
        }

        .leave-type-desc {
            display: block;
            font-size: 11px;
            color: #60736c;
            line-height: 1.4;
        }

        .leave-datetime-wrap {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }

        .leave-section-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: 1.15fr .85fr;
            align-items: start;
        }

        .leave-block {
            border: 1px solid #d9ebe5;
            border-radius: 14px;
            background: linear-gradient(145deg, #ffffff 0%, #f8fcfb 100%);
            padding: 12px;
        }

        .leave-block-head {
            margin: 0 0 8px;
            font-size: 13px;
            font-weight: 800;
            color: #13433f;
        }

        .leave-chip-list {
            margin-top: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .leave-chip {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            border: 1px solid #cde3dc;
            background: #f4fbf8;
            color: #14524c;
            padding: 4px 9px;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.2;
        }

        .leave-chip-empty {
            border-style: dashed;
            background: #fafefe;
            color: #6a7f79;
            font-weight: 600;
        }

        .leave-inline-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin: 8px 0 10px;
        }

        .leave-inline-actions button {
            min-height: 34px;
            border: 1px solid #c9ddd7;
            background: #ffffff;
            color: #2e5752;
            border-radius: 10px;
            padding: 7px 10px;
            font-size: 11px;
            font-weight: 700;
            box-shadow: none;
        }

        .leave-inline-actions button:hover {
            transform: none;
            border-color: #0f766e;
            color: #0f766e;
            background: #f0fbf8;
            box-shadow: 0 4px 12px rgba(15, 118, 110, 0.15);
        }

        .leave-summary {
            margin-top: 10px;
            border: 1px solid #d7e9e2;
            border-radius: 12px;
            background: #ffffff;
            padding: 10px;
        }

        .leave-summary-title {
            margin: 0 0 4px;
            font-size: 11px;
            font-weight: 800;
            color: #295d57;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .leave-summary-text {
            margin: 0;
            font-size: 12px;
            color: #516762;
            line-height: 1.5;
            font-weight: 600;
        }

        .leave-form textarea[name="reason"] {
            min-height: 130px;
        }

        .leave-submit-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }

        .leave-submit-note {
            margin: 0;
            font-size: 12px;
            color: #64756f;
        }

        @media (max-width: 720px) {
            .leave-form-section {
                padding: 14px;
            }

            .leave-guide-grid {
                grid-template-columns: 1fr;
            }

            .leave-section-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

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
        $subjectLabelsById = $subjectSelectOptions
            ->keyBy('id')
            ->map(fn ($option) => preg_replace('/\s*-\s*ID:\s*\d+\s*$/', '', (string) ($option['label'] ?? '')))
            ->all();
        $selectedSubjectLabel = collect($selectedSubjectIds)
            ->map(fn (int $id) => (string) ($subjectLabelsById[$id] ?? ('Subject ID: '.$id)))
            ->implode(', ');

        $requestType = old('request_type', $item['request_type'] ?? 'hourly');
        $startDateValue = old('start_date', $item['start_date'] ?? '');
        $startTimeValue = old('start_time', isset($item['start_time']) ? substr((string) $item['start_time'], 0, 5) : '');
        $endTimeValue = old('end_time', isset($item['end_time']) ? substr((string) $item['end_time'], 0, 5) : '');
        $endDateValue = old('end_date', $item['end_date'] ?? '');
        $returnDateValue = old('return_date', $item['return_date'] ?? '');
        $totalDaysValue = old('total_days', $item['total_days'] ?? '');
        $initialScheduleSummary = $requestType === 'multi_day'
            ? ('Multi Day: '.($startDateValue ?: '-').' to '.($endDateValue ?: '-').' | Return: '.($returnDateValue ?: '-').' | Days: '.($totalDaysValue ?: '-'))
            : ('Hourly: '.($startDateValue ?: '-').' | '.($startTimeValue ?: '--:--').' - '.($endTimeValue ?: '--:--'));
        $subjectOptionsByStudent = $subjectOptionsByStudent ?? [];
        $subjectOptionsByStudentJson = json_encode($subjectOptionsByStudent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $selectedStudent = $studentSelectOptions->firstWhere('id', (int) $selectedStudentId);
        $selectedStudentLabel = is_array($selectedStudent) ? (string) ($selectedStudent['label'] ?? '') : '';
    @endphp

    @if(!$isEdit)
        <section class="panel panel-form panel-spaced leave-guide">
            <div class="leave-guide-head">ស្នើសុំច្បាប់ 3 ជំហាន ងាយៗ</div>
            <div class="leave-guide-grid">
                <article class="leave-guide-item">
                    <span class="leave-guide-step">1</span>
                    <h3>ជ្រើសប្រភេទស្នើ</h3>
                    <p>ជ្រើស Hourly សម្រាប់សុំចេញមួយថ្ងៃមានម៉ោង ឬ Multi Day សម្រាប់ឈប់ច្រើនថ្ងៃ។</p>
                </article>
                <article class="leave-guide-item">
                    <span class="leave-guide-step">2</span>
                    <h3>បញ្ចូលថ្ងៃ/ម៉ោង</h3>
                    <p>បំពេញតាមប្រភេទដែលបានជ្រើស។ ប្រព័ន្ធនឹងបង្ហាញតែ field ដែលត្រូវការ។</p>
                </article>
                <article class="leave-guide-item">
                    <span class="leave-guide-step">3</span>
                    <h3>សរសេរមូលហេតុឲ្យច្បាស់</h3>
                    <p>សរសេរឲ្យខ្លី តែច្បាស់ ដើម្បីឲ្យគ្រូ/Admin ពិនិត្យអនុម័តបានលឿន។</p>
                </article>
            </div>
        </section>
    @endif

    <div class="leave-request-shell">
    <form method="POST" action="{{ $isEdit ? route('panel.leave-requests.update', $item['id']) : route('panel.leave-requests.store') }}" class="panel panel-form leave-form">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <section class="leave-form-section">
            <h2 class="leave-form-section-head">1) Basic Information</h2>
            <p class="leave-form-section-note">ជ្រើសសិស្ស និងប្រភេទស្នើសុំឲ្យត្រឹមត្រូវ មុនបំពេញថ្ងៃ/ម៉ោង។</p>

            <div class="leave-field-grid">
                <div>
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
                </div>

                <div class="leave-field-wide">
                    <label>Request Type</label>
                    @if($readOnlyDetails)
                        <input type="text" value="{{ ($item['request_type'] ?? '') === 'multi_day' ? 'Multi Day' : 'Hourly' }}" disabled>
                    @else
                        <div class="leave-type-picker" id="request_type_picker">
                            <label class="leave-type-option {{ $requestType === 'hourly' ? 'is-active' : '' }}">
                                <input type="radio" name="request_type" value="hourly" {{ $requestType === 'hourly' ? 'checked' : '' }} required>
                                <span class="leave-type-title">Hourly (One Day)</span>
                                <span class="leave-type-desc">សម្រាប់សុំចេញក្នុងថ្ងៃតែមួយ ដោយបញ្ជាក់ម៉ោងចេញ/ចូលវិញ។</span>
                            </label>
                            <label class="leave-type-option {{ $requestType === 'multi_day' ? 'is-active' : '' }}">
                                <input type="radio" name="request_type" value="multi_day" {{ $requestType === 'multi_day' ? 'checked' : '' }} required>
                                <span class="leave-type-title">Multi Day</span>
                                <span class="leave-type-desc">សម្រាប់សុំឈប់ច្រើនថ្ងៃ ដោយបញ្ជាក់ថ្ងៃចាប់ផ្តើម/បញ្ចប់ និងថ្ងៃត្រលប់មកវិញ។</span>
                            </label>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section class="leave-form-section">
            <h2 class="leave-form-section-head">2) Subjects and Schedule</h2>
            <p class="leave-form-section-note">ជ្រើសមុខវិជ្ជា បន្ទាប់មកបំពេញថ្ងៃ/ម៉ោងតាមប្រភេទដែលបានជ្រើសខាងលើ។</p>

            <div class="leave-section-grid">
                <div class="leave-block">
                    <h3 class="leave-block-head">Subjects</h3>
                    <label>Subjects (Select one or more)</label>
                    @if($readOnlyDetails)
                        <input type="text" value="{{ $selectedSubjectLabel !== '' ? $selectedSubjectLabel : '-' }}" disabled>
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
                        <p class="leave-helper">Tip: ចុច `Ctrl` (Windows) ឬ `Command` (Mac) ដើម្បីជ្រើសមុខវិជ្ជាច្រើន។</p>
                    @endif

                    <div id="subject_selection_preview" class="leave-chip-list" aria-live="polite">
                        @if($selectedSubjectIds !== [])
                            @foreach($selectedSubjectIds as $subjectId)
                                <span class="leave-chip">{{ $subjectLabelsById[$subjectId] ?? ('Subject ID: '.$subjectId) }}</span>
                            @endforeach
                        @else
                            <span class="leave-chip leave-chip-empty">No subject selected yet</span>
                        @endif
                    </div>
                </div>

                <div class="leave-block">
                    <h3 class="leave-block-head">Schedule</h3>
                    <label>Start Date</label>
                    @if($readOnlyDetails)
                        <input type="text" value="{{ $item['start_date'] ?? '' }}" disabled>
                    @else
                        <div class="leave-inline-actions">
                            <button type="button" data-fill-start-date="today">Today</button>
                            <button type="button" data-fill-start-date="tomorrow">Tomorrow</button>
                        </div>
                        <input type="date" name="start_date" value="{{ $startDateValue }}" required>
                    @endif

                    <div id="hourly_fields" class="leave-datetime-wrap">
                        <div>
                            <label>Start Time</label>
                            @if($readOnlyDetails)
                                <input type="text" value="{{ isset($item['start_time']) ? substr((string) $item['start_time'], 0, 5) : '' }}" disabled>
                            @else
                                <input type="time" name="start_time" value="{{ $startTimeValue }}">
                            @endif
                        </div>
                        <div>
                            <label>End Time</label>
                            @if($readOnlyDetails)
                                <input type="text" value="{{ isset($item['end_time']) ? substr((string) $item['end_time'], 0, 5) : '' }}" disabled>
                            @else
                                <input type="time" name="end_time" value="{{ $endTimeValue }}">
                            @endif
                        </div>
                    </div>

                    <div id="multi_day_fields" class="leave-datetime-wrap">
                        <div>
                            <label>End Date</label>
                            @if($readOnlyDetails)
                                <input type="text" value="{{ $item['end_date'] ?? '' }}" disabled>
                            @else
                                <input type="date" name="end_date" value="{{ $endDateValue }}">
                            @endif
                        </div>
                        <div>
                            <label>Total Leave Days (Auto)</label>
                            @if($readOnlyDetails)
                                <input type="text" data-total-days-display value="{{ $item['total_days'] ?? '-' }}" disabled>
                            @else
                                <input type="text" data-total-days-display value="Auto-calculated from Start Date and End Date" disabled>
                            @endif
                            <p class="leave-helper">ប្រព័ន្ធនឹងគណនាស្វ័យប្រវត្តិ បន្ទាប់ពីជ្រើស Start Date និង End Date។</p>
                        </div>
                        <div>
                            <label>Return Date</label>
                            @if($readOnlyDetails)
                                <input type="text" value="{{ $item['return_date'] ?? '' }}" disabled>
                            @else
                                <input type="date" name="return_date" value="{{ $returnDateValue }}">
                            @endif
                        </div>
                    </div>

                    <div class="leave-summary">
                        <p class="leave-summary-title">Schedule Summary</p>
                        <p id="schedule_summary_text" class="leave-summary-text">{{ $initialScheduleSummary }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="leave-form-section">
            <h2 class="leave-form-section-head">3) Reason and Submit</h2>
            <p class="leave-form-section-note">សូមបញ្ចូលមូលហេតុឲ្យច្បាស់ ដើម្បីឲ្យគ្រូ/Admin សម្រេចបានលឿន។</p>

            <label>Reason</label>
            @if($readOnlyDetails)
                <textarea rows="5" disabled>{{ old('reason', $item['reason'] ?? '') }}</textarea>
            @else
                <textarea name="reason" rows="5" placeholder="ឧ. សូមសុំច្បាប់ដោយសារទៅពិនិត្យសុខភាព..." required>{{ old('reason', $item['reason'] ?? '') }}</textarea>
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

            <div class="leave-submit-row btn-space-top">
                <p class="leave-submit-note">
                    {{ $isEdit ? 'Update information and save changes.' : 'Please review once before submit.' }}
                </p>
                <button type="submit">{{ $isEdit ? 'Update' : 'Submit Leave Request' }}</button>
            </div>
        </section>
    </form>
    </div>

    @push('scripts')
    <script>
        (function () {
            const requestType = document.getElementById('request_type');
            const requestTypeDefault = @json($requestType);
            const requestTypeRadios = Array.from(document.querySelectorAll('input[name="request_type"]'));
            const requestTypeCards = Array.from(document.querySelectorAll('.leave-type-option'));
            const hourly = document.getElementById('hourly_fields');
            const multi = document.getElementById('multi_day_fields');
            const startTime = document.querySelector('input[name="start_time"]');
            const endTime = document.querySelector('input[name="end_time"]');
            const startDate = document.querySelector('input[name="start_date"]');
            const endDate = document.querySelector('input[name="end_date"]');
            const totalDaysDisplay = document.querySelector('[data-total-days-display]');
            const returnDate = document.querySelector('input[name="return_date"]');
            const studentSelect = document.getElementById('student_id');
            const subjectSelect = document.getElementById('subject_ids');
            const subjectPreview = document.getElementById('subject_selection_preview');
            const scheduleSummary = document.getElementById('schedule_summary_text');
            const fillStartDateButtons = Array.from(document.querySelectorAll('[data-fill-start-date]'));
            const map = {!! $subjectOptionsByStudentJson ?: '{}' !!};
            const getRequestTypeValue = () => {
                if (requestType) {
                    return requestType.value;
                }

                const checked = requestTypeRadios.find((input) => input.checked);
                if (checked) {
                    return checked.value;
                }

                return requestTypeDefault === 'multi_day' ? 'multi_day' : 'hourly';
            };

            const formatDateText = (value) => {
                if (!value) {
                    return '-';
                }

                const parts = String(value).split('-');
                if (parts.length !== 3) {
                    return String(value);
                }

                return parts[2] + '/' + parts[1] + '/' + parts[0];
            };

            const updateSubjectPreview = () => {
                if (!subjectPreview || !subjectSelect) {
                    return;
                }

                const selected = Array.from(subjectSelect.selectedOptions).map((option) => option.textContent.trim()).filter(Boolean);
                subjectPreview.innerHTML = '';

                if (selected.length === 0) {
                    const empty = document.createElement('span');
                    empty.className = 'leave-chip leave-chip-empty';
                    empty.textContent = 'No subject selected yet';
                    subjectPreview.appendChild(empty);

                    return;
                }

                selected.forEach((label) => {
                    const chip = document.createElement('span');
                    chip.className = 'leave-chip';
                    chip.textContent = label;
                    subjectPreview.appendChild(chip);
                });
            };

            const updateScheduleSummary = () => {
                if (!scheduleSummary) {
                    return;
                }
                if (!startDate && !startTime && !endDate && !returnDate) {
                    return;
                }

                const isMulti = getRequestTypeValue() === 'multi_day';
                const sDate = startDate ? startDate.value : '';
                const sTime = startTime ? startTime.value : '';
                const eTime = endTime ? endTime.value : '';
                const eDate = endDate ? endDate.value : '';
                const rDate = returnDate ? returnDate.value : '';
                const days = calculateTotalDays();

                if (isMulti) {
                    scheduleSummary.textContent = 'Multi Day: ' + formatDateText(sDate) + ' to ' + formatDateText(eDate)
                        + ' | Return: ' + formatDateText(rDate) + ' | Days: ' + (days !== null ? String(days) : '-');

                    return;
                }

                scheduleSummary.textContent = 'Hourly: ' + formatDateText(sDate) + ' | '
                    + (sTime || '--:--') + ' - ' + (eTime || '--:--');
            };

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

                updateSubjectPreview();
            };

            const syncDateLimits = () => {
                if (startDate && endDate) {
                    endDate.min = startDate.value || '';
                }
                if (endDate && returnDate) {
                    returnDate.min = endDate.value || '';
                }
            };

            const calculateTotalDays = () => {
                if (!startDate || !endDate || !startDate.value || !endDate.value) {
                    return null;
                }

                const start = new Date(startDate.value);
                const end = new Date(endDate.value);
                if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
                    return null;
                }

                const diff = Math.floor((end.getTime() - start.getTime()) / (24 * 60 * 60 * 1000)) + 1;

                return diff > 0 ? diff : null;
            };

            const syncTotalDaysDisplay = () => {
                if (!totalDaysDisplay) {
                    return;
                }
                if (!startDate || !endDate) {
                    return;
                }

                const diff = calculateTotalDays();
                if (diff === null) {
                    totalDaysDisplay.value = 'Auto-calculated from Start Date and End Date';

                    return;
                }

                totalDaysDisplay.value = String(diff);
            };

            const syncTypeCardState = () => {
                if (requestTypeCards.length === 0) {
                    return;
                }

                requestTypeCards.forEach((card) => {
                    const input = card.querySelector('input[type="radio"]');
                    card.classList.toggle('is-active', !!input && input.checked);
                });
            };

            const toggle = () => {
                const isMulti = getRequestTypeValue() === 'multi_day';
                hourly.style.display = isMulti ? 'none' : 'block';
                multi.style.display = isMulti ? 'block' : 'none';
                syncTypeCardState();

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
                if (returnDate) {
                    returnDate.required = isMulti;
                    returnDate.disabled = !isMulti;
                }

                syncDateLimits();
                syncTotalDaysDisplay();
                updateScheduleSummary();
            };

            if (requestType) {
                requestType.addEventListener('change', toggle);
            }

            if (requestTypeRadios.length > 0) {
                requestTypeRadios.forEach((input) => input.addEventListener('change', toggle));
            }

            if (studentSelect) {
                studentSelect.addEventListener('change', fillSubjects);
                fillSubjects();
            }
            if (subjectSelect) {
                subjectSelect.addEventListener('change', updateSubjectPreview);
                updateSubjectPreview();
            }

            if (fillStartDateButtons.length > 0 && startDate) {
                fillStartDateButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        const today = new Date();
                        if (button.getAttribute('data-fill-start-date') === 'tomorrow') {
                            today.setDate(today.getDate() + 1);
                        }
                        const yyyy = today.getFullYear();
                        const mm = String(today.getMonth() + 1).padStart(2, '0');
                        const dd = String(today.getDate()).padStart(2, '0');
                        startDate.value = yyyy + '-' + mm + '-' + dd;
                        syncDateLimits();
                        syncTotalDaysDisplay();
                        updateScheduleSummary();
                    });
                });
            }

            if (startDate) {
                startDate.addEventListener('change', () => {
                    syncDateLimits();
                    syncTotalDaysDisplay();
                    updateScheduleSummary();
                });
            }
            if (endDate) {
                endDate.addEventListener('change', () => {
                    syncDateLimits();
                    syncTotalDaysDisplay();
                    updateScheduleSummary();
                });
            }
            if (startTime) {
                startTime.addEventListener('change', updateScheduleSummary);
            }
            if (endTime) {
                endTime.addEventListener('change', updateScheduleSummary);
            }
            if (returnDate) {
                returnDate.addEventListener('change', updateScheduleSummary);
            }

            toggle();
            syncTotalDaysDisplay();
            updateScheduleSummary();
        })();
    </script>
    @endpush
@endsection
