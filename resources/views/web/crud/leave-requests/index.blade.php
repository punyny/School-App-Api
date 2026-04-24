@extends('web.layouts.app')

@section('content')
    <style>
        .leave-dashboard-title {
            margin-bottom: 4px;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .leave-dashboard-subtitle {
            margin: 0 0 14px;
            color: var(--text-muted);
        }

        .leave-dashboard-frame {
            margin-top: 18px;
            padding: 18px;
            border: 6px dotted #0f7048;
            border-radius: 24px;
            background: linear-gradient(180deg, rgba(255, 255, 255, .92), rgba(241, 249, 244, .9));
        }

        .leave-dashboard-summary {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            margin-bottom: 16px;
        }

        .leave-summary-item {
            border-radius: 14px;
            padding: 10px 12px;
            border: 1px solid #d8e7df;
            background: #fff;
        }

        .leave-summary-label {
            margin: 0;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #4a6257;
        }

        .leave-summary-value {
            margin: 6px 0 0;
            font-size: 24px;
            font-weight: 800;
            line-height: 1;
        }

        .leave-board-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            align-items: start;
        }

        .leave-column {
            border-radius: 18px;
            border: 3px solid #d7e7df;
            background: #fff;
            min-height: 420px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .leave-column-head {
            padding: 14px 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #0b6a4f;
            font-weight: 800;
            font-size: 20px;
            letter-spacing: .02em;
            text-transform: capitalize;
        }

        .leave-column-head .count {
            border-radius: 999px;
            background: rgba(10, 82, 61, .15);
            color: #0a523d;
            padding: 3px 10px;
            font-size: 12px;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .leave-column-pending {
            border-color: #f0df4f;
        }

        .leave-column-pending .leave-column-head {
            background: linear-gradient(90deg, #fff45f, #f8e81f);
        }

        .leave-column-approved {
            border-color: #f6a224;
        }

        .leave-column-approved .leave-column-head {
            background: linear-gradient(90deg, #ffbc5a, #ff9f0a);
        }

        .leave-column-rejected {
            border-color: #ef476f;
        }

        .leave-column-rejected .leave-column-head {
            background: linear-gradient(90deg, #ff6a73, #ff3347);
        }

        .leave-column-body {
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 68vh;
            overflow: auto;
        }

        .leave-empty {
            margin: 4px 0 0;
            border: 1px dashed #bfd0c7;
            border-radius: 12px;
            background: #f8fcf9;
            color: #60756b;
            text-align: center;
            padding: 22px 12px;
        }

        .leave-card {
            border: 1px solid #d8e5df;
            border-radius: 14px;
            background: #fff;
            padding: 12px;
            box-shadow: 0 8px 18px rgba(22, 57, 45, .08);
        }

        .leave-card-top {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .leave-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e6efe9;
            background: #f2f7f4;
        }

        .leave-avatar-fallback {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 2px solid #e6efe9;
            background: #eff7f2;
            color: #0c6a4e;
            font-weight: 800;
        }

        .leave-student-name {
            margin: 0;
            font-weight: 700;
            line-height: 1.25;
            color: #11362a;
        }

        .leave-class-name {
            margin: 2px 0 0;
            color: #5e7268;
            font-size: 12px;
        }

        .leave-meta {
            margin: 8px 0 0;
            display: grid;
            gap: 6px;
        }

        .leave-meta-row {
            display: grid;
            gap: 8px;
            grid-template-columns: 84px 1fr;
            align-items: start;
            font-size: 12px;
        }

        .leave-meta-label {
            color: #61766b;
            font-weight: 600;
        }

        .leave-meta-value {
            color: #172a23;
            word-break: break-word;
        }

        .leave-actions {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .leave-actions button,
        .leave-actions a {
            border-radius: 10px;
            border: 1px solid transparent;
            padding: 6px 10px;
            line-height: 1.2;
            font-size: 12px;
            text-decoration: none;
            cursor: pointer;
        }

        .leave-btn-approve {
            background: #e9fff1;
            border-color: #71d399;
            color: #0a7a4e;
        }

        .leave-btn-reject {
            background: #fff1f2;
            border-color: #ff8f9f;
            color: #b12642;
        }

        .leave-btn-edit {
            background: #edf5ff;
            border-color: #89b6f0;
            color: #1d569e;
        }

        .leave-btn-delete {
            background: #fff6eb;
            border-color: #ffc987;
            color: #9e5512;
        }

        @media (max-width: 1240px) {
            .leave-board-grid,
            .leave-dashboard-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 820px) {
            .leave-board-grid,
            .leave-dashboard-summary {
                grid-template-columns: 1fr;
            }

            .leave-column-body {
                max-height: none;
            }
        }
    </style>

    @php
        $role = (string) ($userRole ?? '');
        $isStudent = $role === 'student';
        $selectedStudentId = (string) ($filters['student_id'] ?? '');
        $selectedSubjectId = (string) ($filters['subject_id'] ?? '');
        $studentSelectOptions = collect($studentOptions ?? []);
        $subjectSelectOptions = collect($subjectOptions ?? []);
        $subjectLabelById = $subjectSelectOptions->keyBy('id')->map(fn ($option) => (string) ($option['label'] ?? ''))->all();

        if ($selectedStudentId !== '' && ! $studentSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedStudentId)) {
            $studentSelectOptions = $studentSelectOptions->prepend([
                'id' => (int) $selectedStudentId,
                'label' => 'Student ID: '.$selectedStudentId,
            ]);
        }

        if ($selectedSubjectId !== '' && ! $subjectSelectOptions->contains(fn ($option) => (string) ($option['id'] ?? '') === $selectedSubjectId)) {
            $subjectSelectOptions = $subjectSelectOptions->prepend([
                'id' => (int) $selectedSubjectId,
                'label' => 'Subject ID: '.$selectedSubjectId,
            ]);
        }

        $leaveItems = collect($items ?? []);
        $pendingItems = $leaveItems->filter(fn ($item) => strtolower((string) ($item['status'] ?? '')) === 'pending')->values();
        $approvedItems = $leaveItems->filter(fn ($item) => strtolower((string) ($item['status'] ?? '')) === 'approved')->values();
        $rejectedItems = $leaveItems->filter(fn ($item) => strtolower((string) ($item['status'] ?? '')) === 'rejected')->values();

        $columns = [
            ['label' => 'Pending', 'key' => 'pending', 'items' => $pendingItems],
            ['label' => 'Approved', 'key' => 'approved', 'items' => $approvedItems],
            ['label' => 'Not Approve', 'key' => 'rejected', 'items' => $rejectedItems],
        ];
    @endphp

    <h1 class="title leave-dashboard-title">Leave Requests</h1>
    <p class="leave-dashboard-subtitle">Dashboard for reviewing student and parent leave submissions.</p>

    <div class="nav">
        <a href="{{ route('dashboard') }}">Dashboard</a>
        @can('web-create-leave-requests')
            <a href="{{ route('panel.leave-requests.create') }}" class="active">+ Create Leave Request</a>
        @endcan
    </div>

    @if (session('success'))
        <p class="flash-success">{{ session('success') }}</p>
    @endif

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    @if(! $isStudent)
        <form method="GET" action="{{ route('panel.leave-requests.index') }}" class="panel panel-form panel-spaced">
            <div class="form-grid">
                <div>
                    <input type="text" class="searchable-select-search" placeholder="Search student..." data-select-search-for="filter_student_id">
                    <select id="filter_student_id" name="student_id">
                        <option value="">Student</option>
                        @foreach($studentSelectOptions as $option)
                            <option value="{{ $option['id'] }}" {{ $selectedStudentId === (string) $option['id'] ? 'selected' : '' }}>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <input type="text" class="searchable-select-search" placeholder="Search subject..." data-select-search-for="filter_subject_id">
                    <select id="filter_subject_id" name="subject_id">
                        <option value="">Subject</option>
                        @foreach($subjectSelectOptions as $option)
                            <option value="{{ $option['id'] }}" {{ $selectedSubjectId === (string) $option['id'] ? 'selected' : '' }}>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <select name="status">
                    <option value="">Status</option>
                    @foreach (['pending','approved','rejected'] as $status)
                        <option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <input type="date" name="start_date_from" value="{{ $filters['start_date_from'] ?? '' }}">
                <input type="date" name="start_date_to" value="{{ $filters['start_date_to'] ?? '' }}">
                <input type="number" name="per_page" placeholder="Per Page" value="{{ $filters['per_page'] ?? 20 }}">
            </div>
            <button type="submit" class="btn-space-top">Filter</button>
        </form>
    @endif

    <section class="leave-dashboard-frame">
        <div class="leave-dashboard-summary">
            <article class="leave-summary-item">
                <p class="leave-summary-label">Pending</p>
                <p class="leave-summary-value">{{ $pendingItems->count() }}</p>
            </article>
            <article class="leave-summary-item">
                <p class="leave-summary-label">Approved</p>
                <p class="leave-summary-value">{{ $approvedItems->count() }}</p>
            </article>
            <article class="leave-summary-item">
                <p class="leave-summary-label">Not Approve</p>
                <p class="leave-summary-value">{{ $rejectedItems->count() }}</p>
            </article>
        </div>

        <div class="leave-board-grid">
            @foreach($columns as $column)
                <section class="leave-column leave-column-{{ $column['key'] }}">
                    <header class="leave-column-head">
                        <span>{{ $column['label'] }}</span>
                        <span class="count">{{ collect($column['items'])->count() }}</span>
                    </header>
                    <div class="leave-column-body">
                        @forelse($column['items'] as $item)
                            @php
                                $role = $userRole ?? '';
                                $submittedBy = (int) ($item['submitted_by'] ?? 0);
                                $status = strtolower((string) ($item['status'] ?? ''));
                                $isOwnerPending = in_array($role, ['student', 'parent'], true) && $submittedBy === (int) ($authUserId ?? 0) && $status === 'pending';
                                $isApprover = in_array($role, ['super-admin', 'admin', 'teacher'], true);
                                $canEdit = $isOwnerPending || $isApprover;
                                $canDelete = $isOwnerPending || in_array($role, ['super-admin', 'admin'], true);

                                $subjectIds = collect($item['subject_ids'] ?? [])->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->values()->all();
                                if ($subjectIds === [] && ! empty($item['subject_id'])) {
                                    $subjectIds = [(int) $item['subject_id']];
                                }
                                $subjectLabels = collect($subjectIds)->map(function (int $id) use ($subjectLabelById) {
                                    $label = (string) ($subjectLabelById[$id] ?? ('Subject ID: '.$id));
                                    return preg_replace('/\s*-\s*ID:\s*\d+\s*$/', '', $label) ?: $label;
                                })->implode(', ');

                                $requestType = (string) ($item['request_type'] ?? '');
                                $period = $requestType === 'multi_day'
                                    ? trim((string) ($item['start_date'] ?? '')).' → '.trim((string) ($item['end_date'] ?? '')).' | Return: '.((string) ($item['return_date'] ?? '-')).' | Days: '.((string) ($item['total_days'] ?? '-'))
                                    : trim((string) ($item['start_date'] ?? '')).' '.substr((string) ($item['start_time'] ?? ''), 0, 5).' - '.substr((string) ($item['end_time'] ?? ''), 0, 5);

                                $studentName = trim((string) ($item['student']['user']['name'] ?? 'Student #'.($item['student_id'] ?? '-')));
                                $className = trim((string) ($item['student']['class']['class_name'] ?? $item['student']['class']['name'] ?? $item['student']['class']['grade_level'] ?? '-'));
                                $avatar = trim((string) ($item['student']['user']['image_url'] ?? ''));
                                $avatarInitial = mb_substr($studentName, 0, 1);
                            @endphp

                            <article class="leave-card">
                                <div class="leave-card-top">
                                    @if ($avatar !== '')
                                        <img src="{{ $avatar }}" alt="{{ $studentName }}" class="leave-avatar">
                                    @else
                                        <span class="leave-avatar-fallback">{{ strtoupper($avatarInitial !== '' ? $avatarInitial : 'S') }}</span>
                                    @endif
                                    <div>
                                        <p class="leave-student-name">{{ $studentName }}</p>
                                        <p class="leave-class-name">Class: {{ $className }}</p>
                                    </div>
                                </div>

                                <div class="leave-meta">
                                    <div class="leave-meta-row">
                                        <span class="leave-meta-label">ID</span>
                                        <span class="leave-meta-value">#{{ $item['id'] }}</span>
                                    </div>
                                    <div class="leave-meta-row">
                                        <span class="leave-meta-label">Type</span>
                                        <span class="leave-meta-value">{{ $requestType === 'multi_day' ? 'Multi Day' : 'Hourly' }}</span>
                                    </div>
                                    <div class="leave-meta-row">
                                        <span class="leave-meta-label">Subjects</span>
                                        <span class="leave-meta-value">{{ $subjectLabels !== '' ? $subjectLabels : '-' }}</span>
                                    </div>
                                    <div class="leave-meta-row">
                                        <span class="leave-meta-label">Period</span>
                                        <span class="leave-meta-value">{{ $period }}</span>
                                    </div>
                                    <div class="leave-meta-row">
                                        <span class="leave-meta-label">Reason</span>
                                        <span class="leave-meta-value">{{ $item['reason'] ?? '-' }}</span>
                                    </div>
                                    <div class="leave-meta-row">
                                        <span class="leave-meta-label">Submitted</span>
                                        <span class="leave-meta-value">{{ $item['submitter']['name'] ?? ($item['submitted_by'] ?? '-') }}</span>
                                    </div>
                                    <div class="leave-meta-row">
                                        <span class="leave-meta-label">Approved</span>
                                        <span class="leave-meta-value">{{ $item['approver']['name'] ?? '-' }}</span>
                                    </div>
                                </div>

                                <div class="leave-actions">
                                    @if($isApprover && $status === 'pending')
                                        <form action="{{ route('panel.leave-requests.update-status', $item['id']) }}" method="POST" class="inline-form" onsubmit="return confirm('Approve this leave request?')">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="approved">
                                            <button type="submit" class="leave-btn-approve">Approve</button>
                                        </form>
                                        <form action="{{ route('panel.leave-requests.update-status', $item['id']) }}" method="POST" class="inline-form" onsubmit="return confirm('Reject this leave request?')">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="rejected">
                                            <button type="submit" class="leave-btn-reject">Not Approve</button>
                                        </form>
                                    @endif

                                    @if($canEdit)
                                        <a href="{{ route('panel.leave-requests.edit', $item['id']) }}" class="leave-btn-edit">Edit</a>
                                    @endif

                                    @if($canDelete)
                                        <form action="{{ route('panel.leave-requests.destroy', $item['id']) }}" method="POST" class="inline-form" onsubmit="return confirm('Delete this leave request?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="leave-btn-delete">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <p class="leave-empty">No {{ strtolower($column['label']) }} requests.</p>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    </section>
@endsection
