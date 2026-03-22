@extends('web.layouts.app')

@section('content')
    @php
        $selectedStudentId = (string) old('student_id', $item['student_id'] ?? '');
        $selectedClassId = (string) old('class_id', $item['class_id'] ?? '');
        $selectedSubjectId = (string) old('subject_id', $item['subject_id'] ?? '');
        $studentSelectOptions = collect($studentOptions ?? []);
        $classSelectOptions = collect($classOptions ?? []);
        $subjectSelectOptions = collect($subjectOptions ?? []);
        $subjectOptionsByClass = $subjectOptionsByClass ?? [];

        if ($selectedStudentId !== '' && ! $studentSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedStudentId)) {
            $studentSelectOptions = $studentSelectOptions->prepend([
                'id' => (int) $selectedStudentId,
                'label' => 'Student ID: '.$selectedStudentId,
                'class_id' => $selectedClassId !== '' ? (int) $selectedClassId : null,
            ]);
        }

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

        $studentSheetOptions = $studentSelectOptions
            ->map(fn ($option) => [
                'id' => (int) ($option['id'] ?? 0),
                'label' => (string) ($option['label'] ?? 'Student'),
                'class_id' => (int) ($option['class_id'] ?? 0),
            ])
            ->filter(fn ($option) => $option['id'] > 0)
            ->values();

        $oldRecords = collect(old('records', []))
            ->filter(fn ($record) => is_array($record) && !empty($record['student_id']))
            ->mapWithKeys(fn ($record) => [
                (string) $record['student_id'] => [
                    'status' => (string) ($record['status'] ?? 'P'),
                    'remarks' => (string) ($record['remarks'] ?? ''),
                ],
            ])
            ->all();

        $defaultDate = old('date', $item['date'] ?? now()->toDateString());
        $defaultTimeStart = old('time_start', isset($item['time_start']) ? substr((string) $item['time_start'], 0, 5) : now()->format('H:i'));
        $defaultTimeEnd = old('time_end', isset($item['time_end']) && $item['time_end'] ? substr((string) $item['time_end'], 0, 5) : '');
    @endphp

    <style>
        .attendance-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(280px, 0.8fr);
            gap: 14px;
            margin-bottom: 16px;
        }

        .attendance-hero-main,
        .attendance-hero-side {
            border-radius: 22px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .attendance-hero-main {
            padding: 22px;
            background:
                radial-gradient(circle at top right, rgba(249, 115, 22, 0.12), transparent 36%),
                linear-gradient(135deg, rgba(15, 118, 110, 0.08), rgba(255, 255, 255, 0.98));
            border: 1px solid rgba(15, 118, 110, 0.12);
        }

        .attendance-hero-main h1 {
            margin: 0;
            font-size: clamp(28px, 3vw, 38px);
            line-height: 1.05;
        }

        .attendance-hero-main p {
            margin: 10px 0 0;
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.7;
            max-width: 760px;
        }

        .attendance-hero-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 16px;
        }

        .attendance-tag {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            border: 1px solid rgba(15, 118, 110, 0.14);
            background: rgba(255, 255, 255, 0.92);
            color: var(--primary-2);
            font-size: 11px;
            font-weight: 800;
        }

        .attendance-hero-side {
            padding: 18px;
            color: #fff;
            background: linear-gradient(145deg, #0f766e, #155e75 58%, #2563eb);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 16px;
        }

        .attendance-hero-side h3 {
            margin: 0;
            font-size: 16px;
        }

        .attendance-hero-side p {
            margin: 6px 0 0;
            color: rgba(255, 255, 255, 0.86);
            font-size: 13px;
            line-height: 1.6;
        }

        .attendance-quick-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .attendance-quick-card {
            padding: 14px 12px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.14);
        }

        .attendance-quick-card strong {
            display: block;
            font-size: 24px;
            line-height: 1;
        }

        .attendance-quick-card span {
            display: block;
            margin-top: 6px;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.82);
        }

        .attendance-panel {
            border: 1px solid var(--line);
            border-radius: 18px;
            background: #fff;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .attendance-panel-head {
            padding: 16px 18px;
            border-bottom: 1px solid var(--line);
            background: linear-gradient(180deg, #ffffff 0%, #f7fbfa 100%);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
        }

        .attendance-panel-head h3 {
            margin: 0;
            font-size: 16px;
        }

        .attendance-panel-head p {
            margin: 4px 0 0;
            color: var(--text-muted);
            font-size: 13px;
        }

        .attendance-badge {
            border-radius: 999px;
            padding: 8px 12px;
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .attendance-panel-body {
            padding: 18px;
        }

        .attendance-summary-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .attendance-summary-items {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .attendance-summary-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
        }

        .pill-present {
            background: #ecfdf5;
            border: 1px solid #bbf7d0;
            color: #15803d;
        }

        .pill-absent {
            background: #fff1f2;
            border: 1px solid #fecdd3;
            color: #be123c;
        }

        .pill-leave {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #c2410c;
        }

        .attendance-sheet-wrap {
            overflow: auto;
            border: 1px solid #e2ece8;
            border-radius: 16px;
        }

        .attendance-sheet {
            width: 100%;
            min-width: 880px;
            border-collapse: collapse;
        }

        .attendance-sheet th,
        .attendance-sheet td {
            padding: 12px 14px;
            border-bottom: 1px solid #edf4f1;
            vertical-align: top;
        }

        .attendance-sheet th {
            background: #f7fbfa;
            color: #64746f;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .45px;
        }

        .attendance-sheet tbody tr:nth-child(even) {
            background: #fbfefd;
        }

        .student-line {
            font-weight: 700;
            color: #16322c;
        }

        .student-meta {
            margin-top: 4px;
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.6;
        }

        .status-select {
            min-width: 132px;
        }

        .attendance-empty {
            padding: 34px 18px;
            text-align: center;
            color: var(--text-muted);
        }

        .attendance-empty strong {
            display: block;
            margin-bottom: 8px;
            color: #16322c;
            font-size: 16px;
        }

        .attendance-help {
            margin-top: 12px;
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.7;
        }

        @media (max-width: 1080px) {
            .attendance-hero {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 720px) {
            .attendance-quick-grid {
                grid-template-columns: 1fr;
            }

            .attendance-panel-head,
            .attendance-summary-bar {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>

    @if ($mode === 'create')
        <section class="attendance-hero">
            <div class="attendance-hero-main">
                <h1>ស្រង់វត្តមានប្រចាំថ្ងៃ</h1>
                <p>
                    មុនស្រង់វត្តមាន ត្រូវជ្រើសថ្នាក់សិន បន្ទាប់មកជ្រើសមុខវិជ្ជាដែលលោកគ្រូអ្នកគ្រូបានបង្រៀនក្នុងថ្នាក់នោះ។
                    ប្រព័ន្ធនឹងបង្ហាញសិស្សក្នុងថ្នាក់ និងយកម៉ោងបច្ចុប្បន្នជាលំនាំដើមសម្រាប់ session នេះ។
                </p>
                <div class="attendance-hero-tags">
                    <span class="attendance-tag">ជំហានទី ១: ជ្រើសថ្នាក់</span>
                    <span class="attendance-tag">ជំហានទី ២: ជ្រើសមុខវិជ្ជា</span>
                    <span class="attendance-tag">ម៉ោងលំនាំដើម = ម៉ោងបច្ចុប្បន្ន</span>
                </div>
            </div>
            <div class="attendance-hero-side">
                <div>
                    <h3>ការណែនាំរហ័ស</h3>
                    <p>ជ្រើសថ្នាក់ និងមុខវិជ្ជាជាមុនសិន។ បន្ទាប់មកសិស្សទាំងអស់ក្នុងថ្នាក់នោះនឹងបង្ហាញ ហើយអាចកែ status បានភ្លាមៗ។</p>
                </div>
                <div class="attendance-quick-grid">
                    <div class="attendance-quick-card">
                        <strong data-summary-total>0</strong>
                        <span>សិស្ស</span>
                    </div>
                    <div class="attendance-quick-card">
                        <strong data-summary-present>0</strong>
                        <span>វត្តមាន</span>
                    </div>
                    <div class="attendance-quick-card">
                        <strong data-summary-other>0</strong>
                        <span>អវត្តមាន / សុំច្បាប់</span>
                    </div>
                </div>
            </div>
        </section>

        <div class="nav">
            <a href="{{ route('panel.attendance.index') }}">ត្រឡប់ទៅបញ្ជី</a>
        </div>

        @if ($errors->any())
            <p class="flash-error">{{ $errors->first() }}</p>
        @endif

        <form method="POST" action="{{ route('panel.attendance.store') }}" class="attendance-panel">
            @csrf

            <div class="attendance-panel-head">
                <div>
                    <h3>កំណត់វត្តមាន</h3>
                    <p>ជ្រើសព័ត៌មានសម្រាប់សន្លឹកវត្តមានប្រចាំថ្ងៃ មុនពេលស្រង់វត្តមានសិស្ស។</p>
                </div>
                <span class="attendance-badge">សន្លឹកប្រចាំថ្ងៃ</span>
            </div>

            <div class="attendance-panel-body">
                <div class="form-grid">
                    <div>
                        <label>ថ្នាក់</label>
                        <div class="searchable-select-wrap">
                            <input type="text" class="searchable-select-search" placeholder="ស្វែងរកថ្នាក់..." data-select-search-for="class_id">
                            <select id="class_id" name="class_id" required>
                                <option value="">ជ្រើសថ្នាក់</option>
                                @foreach($classSelectOptions as $option)
                                    <option value="{{ $option['id'] }}" {{ $selectedClassId === (string) $option['id'] ? 'selected' : '' }}>
                                        {{ $option['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label>មុខវិជ្ជា</label>
                        <div class="searchable-select-wrap">
                            <input type="text" class="searchable-select-search" placeholder="ស្វែងរកមុខវិជ្ជា..." data-select-search-for="subject_id">
                            <select id="subject_id" name="subject_id" required {{ $selectedClassId === '' ? 'disabled' : '' }}>
                                <option value="">ជ្រើសមុខវិជ្ជា</option>
                                @foreach($subjectSelectOptions as $option)
                                    <option value="{{ $option['id'] }}" {{ $selectedSubjectId === (string) $option['id'] ? 'selected' : '' }}>
                                        {{ $option['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label>កាលបរិច្ឆេទ</label>
                        <input type="date" name="date" id="attendance_date" value="{{ $defaultDate }}" required>
                    </div>

                    <div>
                        <label>ម៉ោងចាប់ផ្តើម</label>
                        <input type="time" name="time_start" id="attendance_time_start" value="{{ $defaultTimeStart }}" required>
                    </div>

                    <div>
                        <label>ម៉ោងបញ្ចប់</label>
                        <input type="time" name="time_end" id="attendance_time_end" value="{{ $defaultTimeEnd }}">
                    </div>
                </div>

                <div class="attendance-summary-bar btn-space-top">
                    <div class="attendance-summary-items">
                        <span class="attendance-summary-pill pill-present">វត្តមាន <strong data-pill-present>0</strong></span>
                        <span class="attendance-summary-pill pill-absent">អវត្តមាន <strong data-pill-absent>0</strong></span>
                        <span class="attendance-summary-pill pill-leave">សុំច្បាប់ <strong data-pill-leave>0</strong></span>
                    </div>
                    <div class="text-muted" data-sheet-caption>សូមជ្រើសថ្នាក់ដើម្បីបង្ហាញបញ្ជីសិស្ស</div>
                </div>

                <div class="attendance-sheet-wrap">
                    <table class="attendance-sheet">
                        <thead>
                            <tr>
                                <th style="width: 60px;">ល.រ</th>
                                <th>សិស្ស</th>
                                <th style="width: 180px;">ស្ថានភាព</th>
                                <th>កំណត់ចំណាំ</th>
                            </tr>
                        </thead>
                        <tbody data-attendance-sheet>
                            <tr>
                                <td colspan="4" class="attendance-empty">
                                    <strong>មិនទាន់ជ្រើសថ្នាក់</strong>
                                    សូមជ្រើសថ្នាក់សិន ដើម្បីបង្ហាញបញ្ជីសិស្សសម្រាប់ស្រង់វត្តមានប្រចាំថ្ងៃ។
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="attendance-help">
                    Tip: ប្រសិនបើ submit ម្តងទៀតជាមួយ class, subject, date និង start time ដដែល វានឹង update record ចាស់វិញ។ សម្រាប់គ្រូដែលបង្រៀនច្រើនមុខវិជ្ជា ប្រព័ន្ធនឹងបង្ហាញតែមុខវិជ្ជាដែលបាន assign ក្នុងថ្នាក់ដែលបានជ្រើស។
                </p>

                <button type="submit" class="btn-space-top">រក្សាទុកវត្តមានប្រចាំថ្ងៃ</button>
            </div>
        </form>
    @else
        <h1 class="title">កែប្រែកំណត់ត្រាវត្តមាន</h1>
        <p class="subtitle">កែប្រែព័ត៌មានវត្តមានរបស់សិស្សម្នាក់។</p>

        <div class="nav">
            <a href="{{ route('panel.attendance.index') }}">ត្រឡប់ទៅបញ្ជី</a>
        </div>

        @if ($errors->any())
            <p class="flash-error">{{ $errors->first() }}</p>
        @endif

        <form method="POST" action="{{ route('panel.attendance.update', $item['id']) }}" class="panel panel-form">
            @csrf
            @method('PUT')

            <label>សិស្ស</label>
            <div class="searchable-select-wrap">
                <input type="text" class="searchable-select-search" placeholder="ស្វែងរកសិស្ស..." data-select-search-for="student_id">
                <select id="student_id" name="student_id" required>
                    <option value="">ជ្រើសសិស្ស</option>
                    @foreach($studentSelectOptions as $option)
                        <option
                            value="{{ $option['id'] }}"
                            data-class-id="{{ (int) ($option['class_id'] ?? 0) }}"
                            {{ $selectedStudentId === (string) $option['id'] ? 'selected' : '' }}
                        >
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <label>ថ្នាក់</label>
            <div class="searchable-select-wrap">
                <input type="text" class="searchable-select-search" placeholder="ស្វែងរកថ្នាក់..." data-select-search-for="edit_class_id">
                <select id="edit_class_id" name="class_id" required>
                    <option value="">ជ្រើសថ្នាក់</option>
                    @foreach($classSelectOptions as $option)
                        <option value="{{ $option['id'] }}" {{ $selectedClassId === (string) $option['id'] ? 'selected' : '' }}>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <label>មុខវិជ្ជា</label>
            <div class="searchable-select-wrap">
                <input type="text" class="searchable-select-search" placeholder="ស្វែងរកមុខវិជ្ជា..." data-select-search-for="edit_subject_id">
                <select id="edit_subject_id" name="subject_id" required>
                    <option value="">ជ្រើសមុខវិជ្ជា</option>
                    @foreach($subjectSelectOptions as $option)
                        <option value="{{ $option['id'] }}" {{ $selectedSubjectId === (string) $option['id'] ? 'selected' : '' }}>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <label>កាលបរិច្ឆេទ</label>
            <input type="date" name="date" value="{{ $defaultDate }}" required>

            <label>ម៉ោងចាប់ផ្តើម</label>
            <input type="time" name="time_start" value="{{ $defaultTimeStart }}" required>

            <label>ម៉ោងបញ្ចប់</label>
            <input type="time" name="time_end" value="{{ $defaultTimeEnd }}">

            <label>ស្ថានភាព</label>
            <select name="status" required>
                <option value="P" {{ old('status', $item['status'] ?? '') === 'P' ? 'selected' : '' }}>វត្តមាន</option>
                <option value="A" {{ old('status', $item['status'] ?? '') === 'A' ? 'selected' : '' }}>អវត្តមាន</option>
                <option value="L" {{ old('status', $item['status'] ?? '') === 'L' ? 'selected' : '' }}>សុំច្បាប់</option>
            </select>

            <label>កំណត់ចំណាំ</label>
            <input type="text" name="remarks" value="{{ old('remarks', $item['remarks'] ?? '') }}" placeholder="បញ្ចូលមូលហេតុ ឬកំណត់ចំណាំ">

            <button type="submit" class="btn-space-top">កែប្រែ</button>
        </form>
    @endif
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var createMode = @json($mode === 'create');

            if (createMode) {
                var classSelect = document.getElementById('class_id');
                var subjectSelect = document.getElementById('subject_id');
                var sheetBody = document.querySelector('[data-attendance-sheet]');
                var caption = document.querySelector('[data-sheet-caption]');
                var totalEl = document.querySelector('[data-summary-total]');
                var presentEl = document.querySelector('[data-summary-present]');
                var otherEl = document.querySelector('[data-summary-other]');
                var pillPresent = document.querySelector('[data-pill-present]');
                var pillAbsent = document.querySelector('[data-pill-absent]');
                var pillLeave = document.querySelector('[data-pill-leave]');
                var timeStartInput = document.getElementById('attendance_time_start');
                var students = @json($studentSheetOptions);
                var subjectOptionsByClass = @json($subjectOptionsByClass);
                var previousRecords = @json($oldRecords);
                var selectedSubjectId = @json($selectedSubjectId);

                if (!classSelect || !sheetBody || !subjectSelect) {
                    return;
                }

                var useCurrentTime = function () {
                    if (!timeStartInput || timeStartInput.dataset.manual === 'true') {
                        return;
                    }

                    var now = new Date();
                    var hours = String(now.getHours()).padStart(2, '0');
                    var minutes = String(now.getMinutes()).padStart(2, '0');
                    timeStartInput.value = hours + ':' + minutes;
                };

                if (timeStartInput) {
                    timeStartInput.addEventListener('input', function () {
                        timeStartInput.dataset.manual = 'true';
                    });
                    useCurrentTime();
                }

                var syncSubjectOptions = function () {
                    var selectedClass = classSelect.value;
                    var rows = selectedClass !== '' && subjectOptionsByClass[selectedClass] ? subjectOptionsByClass[selectedClass] : [];
                    var html = ['<option value="">ជ្រើសមុខវិជ្ជា</option>'];

                    rows.forEach(function (row) {
                        var selected = String(row.id) === String(selectedSubjectId) || String(row.id) === String(subjectSelect.value);
                        html.push('<option value="' + row.id + '"' + (selected ? ' selected' : '') + '>' + row.label + '</option>');
                    });

                    subjectSelect.innerHTML = html.join('');
                    subjectSelect.disabled = selectedClass === '';
                };

                var updateCounters = function () {
                    var statusInputs = Array.prototype.slice.call(sheetBody.querySelectorAll('select[data-status-select]'));
                    var present = 0;
                    var absent = 0;
                    var leave = 0;

                    statusInputs.forEach(function (input) {
                        if (input.value === 'P') {
                            present += 1;
                        } else if (input.value === 'A') {
                            absent += 1;
                        } else if (input.value === 'L') {
                            leave += 1;
                        }
                    });

                    var total = statusInputs.length;

                    if (totalEl) totalEl.textContent = String(total);
                    if (presentEl) presentEl.textContent = String(present);
                    if (otherEl) otherEl.textContent = String(absent + leave);
                    if (pillPresent) pillPresent.textContent = String(present);
                    if (pillAbsent) pillAbsent.textContent = String(absent);
                    if (pillLeave) pillLeave.textContent = String(leave);
                };

                var escapeHtml = function (value) {
                    return String(value)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                };

                var renderSheet = function () {
                    var selectedClass = classSelect.value;
                    var filtered = students.filter(function (student) {
                        return selectedClass !== '' && String(student.class_id) === String(selectedClass);
                    });

                    if (filtered.length === 0) {
                        sheetBody.innerHTML = ''
                            + '<tr><td colspan="4" class="attendance-empty">'
                            + '<strong>មិនមានបញ្ជីសិស្ស</strong>'
                            + (selectedClass === ''
                                ? 'សូមជ្រើសថ្នាក់សិន ដើម្បីបង្ហាញបញ្ជីសិស្សសម្រាប់ស្រង់វត្តមានប្រចាំថ្ងៃ។'
                                : 'មិនទាន់មានសិស្សដែលអាចមើលឃើញសម្រាប់ថ្នាក់នេះទេ។')
                            + '</td></tr>';

                        if (caption) {
                            caption.textContent = selectedClass === ''
                                ? 'សូមជ្រើសថ្នាក់ដើម្បីបង្ហាញបញ្ជីសិស្ស'
                                : 'មិនមានសិស្សសម្រាប់ថ្នាក់ដែលបានជ្រើសទេ';
                        }

                        updateCounters();
                        return;
                    }

                    var rows = filtered.map(function (student, index) {
                        var previous = previousRecords[String(student.id)] || {};
                        var status = previous.status || 'P';
                        var remarks = previous.remarks || '';

                        return ''
                            + '<tr>'
                            + '<td>' + (index + 1) + '<input type="hidden" name="records[' + index + '][student_id]" value="' + student.id + '"></td>'
                            + '<td><div class="student-line">' + escapeHtml(student.label) + '</div><div class="student-meta">លេខសិស្ស: ' + student.id + '</div></td>'
                            + '<td>'
                            + '<select name="records[' + index + '][status]" class="status-select" data-status-select>'
                            + '<option value="P"' + (status === 'P' ? ' selected' : '') + '>វត្តមាន</option>'
                            + '<option value="A"' + (status === 'A' ? ' selected' : '') + '>អវត្តមាន</option>'
                            + '<option value="L"' + (status === 'L' ? ' selected' : '') + '>សុំច្បាប់</option>'
                            + '</select>'
                            + '</td>'
                            + '<td><input type="text" name="records[' + index + '][remarks]" value="' + escapeHtml(remarks) + '" placeholder="បញ្ចូលមូលហេតុ ឬកំណត់ចំណាំ"></td>'
                            + '</tr>';
                    }).join('');

                    sheetBody.innerHTML = rows;

                    if (caption) {
                        caption.textContent = 'បានបង្ហាញសិស្សចំនួន ' + filtered.length + ' នាក់សម្រាប់ថ្នាក់នេះ';
                    }

                    Array.prototype.slice.call(sheetBody.querySelectorAll('select[data-status-select]')).forEach(function (input) {
                        input.addEventListener('change', updateCounters);
                    });

                    updateCounters();
                };

                classSelect.addEventListener('change', function () {
                    selectedSubjectId = '';
                    syncSubjectOptions();
                    renderSheet();
                });
                syncSubjectOptions();
                renderSheet();
                return;
            }

            var editClassSelect = document.getElementById('edit_class_id');
            var studentSelect = document.getElementById('student_id');
            var editSubjectSelect = document.getElementById('edit_subject_id');
            var editSubjectOptionsByClass = @json($subjectOptionsByClass);
            var editSelectedSubjectId = @json($selectedSubjectId);

            if (!editClassSelect || !studentSelect || !editSubjectSelect) {
                return;
            }

            var filterStudents = function () {
                var selectedClass = editClassSelect.value;
                var options = Array.prototype.slice.call(studentSelect.options);

                options.forEach(function (option) {
                    if (option.value === '') {
                        option.hidden = false;
                        return;
                    }

                    var optionClassId = option.getAttribute('data-class-id') || '';
                    option.hidden = selectedClass !== '' && optionClassId !== '' && optionClassId !== selectedClass;
                });

                if (studentSelect.selectedOptions.length > 0 && studentSelect.selectedOptions[0].hidden) {
                    studentSelect.value = '';
                }
            };

            var filterSubjects = function () {
                var selectedClass = editClassSelect.value;
                var rows = selectedClass !== '' && editSubjectOptionsByClass[selectedClass] ? editSubjectOptionsByClass[selectedClass] : [];
                var html = ['<option value="">ជ្រើសមុខវិជ្ជា</option>'];

                rows.forEach(function (row) {
                    var selected = String(row.id) === String(editSelectedSubjectId) || String(row.id) === String(editSubjectSelect.value);
                    html.push('<option value="' + row.id + '"' + (selected ? ' selected' : '') + '>' + row.label + '</option>');
                });

                editSubjectSelect.innerHTML = html.join('');
                editSubjectSelect.disabled = selectedClass === '';
            };

            editClassSelect.addEventListener('change', filterStudents);
            editClassSelect.addEventListener('change', function () {
                editSelectedSubjectId = '';
                filterSubjects();
            });
            filterStudents();
            filterSubjects();
        });
    </script>
@endpush
