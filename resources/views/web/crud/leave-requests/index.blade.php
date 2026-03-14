@extends('web.layouts.app')

@section('content')
    <h1 class="title">Leave Request Management (API)</h1>
    <p class="subtitle">Students/parents submit requests. Teachers/admin review and approve.</p>

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

    @php
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
    @endphp

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

    <section class="panel">
        <div class="panel-head">Leave Requests</div>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Student</th>
                <th>Subjects</th>
                <th>Type</th>
                <th>Period</th>
                <th>Status</th>
                <th>Submitted By</th>
                <th>Approved By</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($items as $item)
                @php
                    $role = $userRole ?? '';
                    $submittedBy = (int) ($item['submitted_by'] ?? 0);
                    $status = (string) ($item['status'] ?? '');
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

                    $period = ($item['request_type'] ?? '') === 'multi_day'
                        ? (($item['start_date'] ?? '').' to '.($item['end_date'] ?? '').' | Return: '.($item['return_date'] ?? '-').' | Days: '.($item['total_days'] ?? '-'))
                        : (($item['start_date'] ?? '').' '.substr((string) ($item['start_time'] ?? ''), 0, 5).' - '.substr((string) ($item['end_time'] ?? ''), 0, 5));
                @endphp
                <tr>
                    <td>{{ $item['id'] }}</td>
                    <td>{{ $item['student']['user']['name'] ?? $item['student_id'] }}</td>
                    <td>{{ $subjectLabels !== '' ? $subjectLabels : '-' }}</td>
                    <td>{{ ($item['request_type'] ?? '') === 'multi_day' ? 'Multi Day' : 'Hourly' }}</td>
                    <td>{{ $period }}</td>
                    <td>{{ ucfirst($status) }}</td>
                    <td>{{ $item['submitter']['name'] ?? ($item['submitted_by'] ?? '-') }}</td>
                    <td>{{ $item['approver']['name'] ?? '-' }}</td>
                    <td>
                        @if($isApprover && $status === 'pending')
                            <form action="{{ route('panel.leave-requests.update', $item['id']) }}" method="POST" class="inline-form" onsubmit="return confirm('Approve this leave request?')">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="status" value="approved">
                                <button type="submit">Approve</button>
                            </form>
                            <form action="{{ route('panel.leave-requests.update', $item['id']) }}" method="POST" class="inline-form" onsubmit="return confirm('Reject this leave request?')">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="status" value="rejected">
                                <button type="submit">Reject</button>
                            </form>
                        @endif
                        @if($canEdit)
                            <a href="{{ route('panel.leave-requests.edit', $item['id']) }}">Edit</a>
                        @endif
                        @if($canDelete)
                            <form action="{{ route('panel.leave-requests.destroy', $item['id']) }}" method="POST" class="inline-form" onsubmit="return confirm('Delete this leave request?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit">Delete</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9">No data.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
