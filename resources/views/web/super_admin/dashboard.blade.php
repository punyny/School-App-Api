@extends('web.layouts.app')

@section('content')
    <div class="topbar">
        <div>
            <h1 class="title">Super Admin Dashboard</h1>
            <p class="subtitle">System-wide school control with direct access to each school admin and setup flow.</p>
        </div>
        <div class="mini-actions">
            <a href="{{ route('panel.schools.create') }}">+ Create School</a>
            <a href="{{ route('panel.users.create', ['role' => 'admin']) }}">+ Create Admin</a>
            <a href="{{ route('panel.schools.index') }}">School Directory</a>
            <a href="{{ route('panel.users.index', ['role' => 'admin']) }}">Admin Directory</a>
            <a href="{{ route('panel.audit-logs.index') }}">Audit Logs</a>
        </div>
    </div>

    <section class="metric-grid">
        <article class="metric-card metric-card-blue">
            <p class="metric-number">{{ $adminCount }}</p>
            <p class="metric-label">Admins</p>
        </article>
        <article class="metric-card metric-card-purple">
            <p class="metric-number">{{ $teacherCount }}</p>
            <p class="metric-label">Teachers</p>
        </article>
        <article class="metric-card metric-card-orange">
            <p class="metric-number">{{ $parentCount }}</p>
            <p class="metric-label">Parents</p>
        </article>
        <article class="metric-card metric-card-green">
            <p class="metric-number">{{ $studentCount }}</p>
            <p class="metric-label">Students</p>
        </article>
        <article class="metric-card metric-card-blue">
            <p class="metric-number">{{ $classCount }}</p>
            <p class="metric-label">Classes</p>
        </article>
        <article class="metric-card metric-card-purple">
            <p class="metric-number">{{ $schoolCount }}</p>
            <p class="metric-label">Schools</p>
        </article>
    </section>

    <section class="admin-grid-mid">
        <article class="panel panel-form">
            <div class="panel-head">School Control Map</div>
            @if(count($schools) === 0)
                <div class="empty">No schools found.</div>
            @else
                <div class="module-grid">
                    @foreach($schools as $school)
                        <a href="{{ route('super-admin.schools.manage', $school['id']) }}" class="module-link" style="padding:14px;">
                            <strong style="display:block; font-size:15px; color:#244018;">{{ $school['name'] }}</strong>
                            <p class="text-muted" style="margin:6px 0 0;">
                                Code: {{ $school['school_code'] !== '' ? $school['school_code'] : 'N/A' }}<br>
                                Campus: {{ $school['location'] !== '' ? $school['location'] : 'Not set' }}
                            </p>
                            <div class="cards" style="margin-top:12px;">
                                <div class="card">
                                    <div class="label">Admins</div>
                                    <div class="value">{{ $school['admin_count'] }}</div>
                                </div>
                                <div class="card">
                                    <div class="label">Classes</div>
                                    <div class="value">{{ $school['class_count'] }}</div>
                                </div>
                                <div class="card">
                                    <div class="label">Students</div>
                                    <div class="value">{{ $school['student_count'] }}</div>
                                </div>
                                <div class="card">
                                    <div class="label">Subjects</div>
                                    <div class="value">{{ $school['subject_count'] }}</div>
                                </div>
                            </div>
                            <p class="text-muted" style="margin:10px 0 0;">
                                Lead admin:
                                <strong style="color:#2f9e44;">
                                    {{ $school['lead_admin_name'] ?? 'Assign admin now' }}
                                </strong>
                            </p>
                            <p class="text-muted" style="margin:4px 0 0;">Click to open school scope.</p>
                        </a>
                    @endforeach
                </div>
            @endif
        </article>

        <article class="panel panel-form">
            <div class="panel-head">System Overview</div>
            <div class="cards">
                <div class="card">
                    <div class="label">Classes</div>
                    <div class="value">{{ $classCount }}</div>
                </div>
                <div class="card">
                    <div class="label">Schools Ready</div>
                    <div class="value">{{ $schoolsReadyCount }}</div>
                </div>
            </div>

            <div class="quick-actions">
                <a href="{{ route('panel.schools.index') }}">Manage Schools</a>
                <a href="{{ route('panel.users.index', ['role' => 'admin']) }}">Manage Admins</a>
                <a href="{{ route('panel.users.index', ['role' => 'teacher']) }}">All Teachers</a>
                <a href="{{ route('panel.users.index', ['role' => 'student']) }}">All Students</a>
                <a href="{{ route('panel.classes.index') }}">All Classes</a>
                <a href="{{ route('panel.subjects.index') }}">All Subjects</a>
                <a href="{{ route('panel.timetables.index') }}">All Timetables</a>
                <a href="{{ route('panel.audit-logs.index') }}">System Audit</a>
            </div>

            <div class="top-list" style="margin-top:14px;">
                @foreach($schoolHealth as $row)
                    <div class="top-item">
                        <span class="rank-badge">{{ $loop->iteration }}</span>
                        <div>
                            <strong>{{ $row['name'] }}</strong>
                            <p class="text-muted">
                                Code: {{ $row['school_code'] !== '' ? $row['school_code'] : 'N/A' }} |
                                Classes: {{ $row['class_count'] }} |
                                Teacher Assignments: {{ $row['teacher_assignment_count'] }} |
                                Subject Assignments: {{ $row['subject_assignment_count'] }}
                            </p>
                        </div>
                        <a href="{{ route('super-admin.schools.manage', $row['id']) }}">Manage</a>
                    </div>
                @endforeach
            </div>
        </article>
    </section>

    <section class="admin-grid-bottom">
        <article class="panel">
            <div class="panel-head">Admin Directory</div>
            <table>
                <thead>
                    <tr>
                        <th>Admin</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>School</th>
                        <th>Status</th>
                        <th>Manage</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($adminDirectory as $admin)
                        <tr>
                            <td><strong>{{ $admin['name'] }}</strong></td>
                            <td>{{ $admin['email'] }}</td>
                            <td>{{ $admin['phone'] }}</td>
                            <td>
                                {{ $admin['school_name'] }}
                                @if($admin['school_code'] !== '')
                                    <div class="text-muted">{{ $admin['school_code'] }}</div>
                                @endif
                            </td>
                            <td>
                                @if($admin['active'])
                                    <span class="badge-soft" style="border-color:#c5f2da;background:#ebfff4;color:#1e7a56;">Active</span>
                                @else
                                    <span class="badge-soft" style="border-color:#ffd6df;background:#fff1f4;color:#a23d58;">Inactive</span>
                                @endif
                            </td>
                            <td>
                                @if($admin['school_id'] > 0)
                                    <a href="{{ route('super-admin.schools.manage', $admin['school_id']) }}">Open school</a>
                                    <span class="text-muted"> | </span>
                                @endif
                                <a href="{{ route('panel.users.show', $admin['id']) }}">Admin detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No admins found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </article>

        <article class="panel panel-form">
            <div class="panel-head">How To Use This Dashboard</div>
            <div class="top-list">
                <div class="top-item">
                    <span class="rank-badge">1</span>
                    <div>
                        <strong>Select a school</strong>
                        <p class="text-muted">Click any school card or admin row to enter that school's scope only.</p>
                    </div>
                </div>
                <div class="top-item">
                    <span class="rank-badge">2</span>
                    <div>
                        <strong>Manage school modules</strong>
                        <p class="text-muted">From the school page you can jump to admins, teachers, students, classes, subjects, and timetable for that school.</p>
                    </div>
                </div>
                <div class="top-item">
                    <span class="rank-badge">3</span>
                    <div>
                        <strong>Assign students by class</strong>
                        <p class="text-muted">Open a class, then select students from that same school and save the assignment.</p>
                    </div>
                </div>
            </div>
        </article>
    </section>
@endsection
