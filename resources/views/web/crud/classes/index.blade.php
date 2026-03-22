@extends('web.layouts.app')

@section('content')
    <style>
        .class-preview-list {
            display: grid;
            gap: 6px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .class-preview-list li {
            font-size: 12px;
            line-height: 1.45;
            color: #3f5137;
            background: #f7ffe8;
            border: 1px solid #dce8c7;
            border-radius: 10px;
            padding: 6px 8px;
        }

        .class-preview-more {
            margin-top: 6px;
            font-size: 11px;
            font-weight: 700;
            color: #5f6f52;
        }

        .class-preview-empty {
            font-size: 12px;
            color: #758566;
        }
    </style>

    <h1 class="title">Class Management</h1>
    <p class="subtitle">Manage classes, class teachers, schedules, and student assignments in one place.</p>

    <div class="nav">
        <a href="{{ route('dashboard') }}">Dashboard</a>
        @can('web-manage-classes')
        <a href="{{ route('panel.classes.create') }}" class="active">+ Create Class</a>
        @endcan
    </div>

    @if (session('success'))
        <p class="flash-success">{{ session('success') }}</p>
    @endif

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    <form method="GET" action="{{ route('panel.classes.index') }}" class="panel panel-form panel-spaced">
        <div class="form-grid">
            @if($userRole === 'super-admin')
                <input type="number" name="school_id" placeholder="School ID" value="{{ $filters['school_id'] ?? '' }}">
            @endif
            <input type="text" name="name" placeholder="Class Name" value="{{ $filters['name'] ?? '' }}">
            <input type="text" name="grade_level" placeholder="Grade Level" value="{{ $filters['grade_level'] ?? '' }}">
            <input type="text" name="room" placeholder="Room" value="{{ $filters['room'] ?? '' }}">
            <input type="number" name="per_page" placeholder="Per Page" value="{{ $filters['per_page'] ?? 20 }}">
        </div>
        <button type="submit" class="btn-space-top">Filter</button>
    </form>

    <section class="panel">
        <div class="panel-head">Classes</div>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Grade</th>
                <th>Room</th>
                <th>School</th>
                <th>Students</th>
                <th>Teacher + Subject</th>
                <th>Routine (Mon-Sat)</th>
                <th>Student Names</th>
                <th>Setup</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($items as $item)
                @php
                    $teacherPreview = collect($item['teacher_preview'] ?? [])->filter()->values();
                    $timetablePreview = collect($item['timetable_preview'] ?? [])->filter()->values();
                    $studentPreview = collect($item['student_preview'] ?? [])->filter()->values();
                    $studentTotal = (int) ($item['student_total'] ?? $item['students_count'] ?? 0);
                    $teacherMore = (int) ($item['teacher_preview_more'] ?? 0);
                    $timetableMore = (int) ($item['timetable_preview_more'] ?? 0);
                    $studentMore = (int) ($item['student_preview_more'] ?? 0);
                    $isReady = !empty($item['room'])
                        && $studentTotal > 0
                        && ($teacherPreview->count() + $teacherMore) > 0
                        && ($timetablePreview->count() + $timetableMore) > 0;
                @endphp
                <tr>
                    <td>{{ $item['id'] }}</td>
                    <td>
                        <a href="{{ route('panel.classes.show', $item['id']) }}"><strong>{{ $item['name'] }}</strong></a>
                    </td>
                    <td>{{ $item['grade_level'] ?? '-' }}</td>
                    <td>{{ $item['room'] ?? '-' }}</td>
                    <td>{{ $item['school']['name'] ?? ($item['school_id'] ?? '-') }}</td>
                    <td>{{ $studentTotal }}</td>
                    <td>
                        @if($teacherPreview->isNotEmpty())
                            <ul class="class-preview-list">
                                @foreach($teacherPreview as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                            </ul>
                            @if($teacherMore > 0)
                                <div class="class-preview-more">+{{ $teacherMore }} more assignment(s)</div>
                            @endif
                        @else
                            <div class="class-preview-empty">No teacher assigned.</div>
                        @endif
                    </td>
                    <td>
                        @if($timetablePreview->isNotEmpty())
                            <ul class="class-preview-list">
                                @foreach($timetablePreview as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                            </ul>
                            @if($timetableMore > 0)
                                <div class="class-preview-more">+{{ $timetableMore }} more period(s)</div>
                            @endif
                        @else
                            <div class="class-preview-empty">No routine set.</div>
                        @endif
                    </td>
                    <td>
                        @if($studentPreview->isNotEmpty())
                            <ul class="class-preview-list">
                                @foreach($studentPreview as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                            </ul>
                            @if($studentMore > 0)
                                <div class="class-preview-more">+{{ $studentMore }} more student(s)</div>
                            @endif
                        @else
                            <div class="class-preview-empty">No students assigned.</div>
                        @endif
                    </td>
                    <td>{{ $isReady ? 'Ready' : 'Incomplete' }}</td>
                    <td>
                        @can('web-manage-classes')
                        <a href="{{ route('panel.classes.show', $item['id']) }}">Open</a>

                        <a href="{{ route('panel.classes.edit', $item['id']) }}">Edit</a>

                        <form action="{{ route('panel.classes.destroy', $item['id']) }}" method="POST" class="inline-form" onsubmit="return confirm('Delete this class?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit">Delete</button>
                        </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="11">No data.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
