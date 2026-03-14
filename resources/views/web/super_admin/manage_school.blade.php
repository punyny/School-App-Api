@extends('web.layouts.app')

@section('content')
    <div class="topbar">
        <div>
            <h1 class="title">Manage School: {{ $school->name }}</h1>
            <p class="subtitle">
                Scope view for one school only.
                Code: {{ $school->school_code ?: 'N/A' }} |
                Campus: {{ $school->location ?: 'Not set' }}
            </p>
        </div>
        <div class="mini-actions">
            <a href="{{ route('super-admin.dashboard') }}">Back to dashboard</a>
            <a href="{{ route('panel.schools.edit', $school->id) }}">Edit School</a>
            <a href="{{ route('panel.users.create', ['role' => 'admin', 'school_id' => $school->id]) }}">+ Add Admin</a>
        </div>
    </div>

    <section class="metric-grid">
        <article class="metric-card metric-card-purple">
            <p class="metric-number">{{ $school->admin_count }}</p>
            <p class="metric-label">Admins</p>
        </article>
        <article class="metric-card metric-card-blue">
            <p class="metric-number">{{ $school->teacher_count }}</p>
            <p class="metric-label">Teachers</p>
        </article>
        <article class="metric-card metric-card-orange">
            <p class="metric-number">{{ $school->student_count }}</p>
            <p class="metric-label">Students</p>
        </article>
        <article class="metric-card metric-card-green">
            <p class="metric-number">{{ $school->classes_count }}</p>
            <p class="metric-label">Classes</p>
        </article>
    </section>

    <section class="admin-grid-mid">
        <article class="panel panel-form">
            <div class="panel-head">School Modules</div>
            <div class="module-grid">
                <a class="module-link" href="{{ route('panel.users.index', ['school_id' => $school->id, 'role' => 'admin']) }}">
                    <strong>School Admins</strong><br>
                    <span class="text-muted">Only admins in {{ $school->name }}</span>
                </a>
                <a class="module-link" href="{{ route('panel.users.index', ['school_id' => $school->id, 'role' => 'teacher']) }}">
                    <strong>Teachers</strong><br>
                    <span class="text-muted">Faculty list for this school</span>
                </a>
                <a class="module-link" href="{{ route('panel.users.index', ['school_id' => $school->id, 'role' => 'student']) }}">
                    <strong>Students</strong><br>
                    <span class="text-muted">Student list scoped to this school</span>
                </a>
                <a class="module-link" href="{{ route('panel.classes.index', ['school_id' => $school->id]) }}">
                    <strong>Classes</strong><br>
                    <span class="text-muted">Open classes then assign students</span>
                </a>
                <a class="module-link" href="{{ route('panel.subjects.index', ['school_id' => $school->id]) }}">
                    <strong>Subjects</strong><br>
                    <span class="text-muted">Manage school subject catalog</span>
                </a>
                <a class="module-link" href="{{ route('panel.timetables.index', ['school_id' => $school->id]) }}">
                    <strong>Timetable</strong><br>
                    <span class="text-muted">View and update class routine</span>
                </a>
            </div>
        </article>

        <article class="panel panel-form">
            <div class="panel-head">School Setup Summary</div>
            <div class="cards">
                <div class="card">
                    <div class="label">Subjects</div>
                    <div class="value">{{ $school->subjects_count }}</div>
                </div>
                <div class="card">
                    <div class="label">Parents</div>
                    <div class="value">{{ $school->parent_count }}</div>
                </div>
            </div>

            <div class="quick-actions">
                <a href="{{ route('panel.users.create', ['role' => 'student', 'school_id' => $school->id]) }}">+ New Student</a>
                <a href="{{ route('panel.users.create', ['school_id' => $school->id]) }}">+ Teacher / Guardian</a>
                <a href="{{ route('panel.classes.create') }}">+ Class</a>
                <a href="{{ route('panel.subjects.create') }}">+ Subject</a>
                <a href="{{ route('panel.timetables.create') }}">+ Timetable</a>
                <a href="{{ route('panel.announcements.create') }}">+ Announcement</a>
            </div>
        </article>
    </section>

    <section class="admin-grid-bottom">
        <article class="panel">
            <div class="panel-head">School Admins</div>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($admins as $admin)
                        <tr>
                            <td><strong>{{ $admin->name }}</strong></td>
                            <td>{{ $admin->email }}</td>
                            <td>{{ $admin->phone ?: '-' }}</td>
                            <td>{{ $admin->active ? 'Active' : 'Inactive' }}</td>
                            <td><a href="{{ route('panel.users.show', $admin->id) }}">View admin</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No admin assigned yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </article>

        <article class="panel">
            <div class="panel-head">Classes In This School</div>
            <table>
                <thead>
                    <tr>
                        <th>Class</th>
                        <th>Grade</th>
                        <th>Room</th>
                        <th>Students</th>
                        <th>Subjects</th>
                        <th>Teachers</th>
                        <th>Routine</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($classes as $class)
                        <tr>
                            <td><strong>{{ $class['name'] }}</strong></td>
                            <td>{{ $class['grade_level'] !== '' ? $class['grade_level'] : '-' }}</td>
                            <td>{{ $class['room'] !== '' ? $class['room'] : '-' }}</td>
                            <td>{{ $class['students_count'] }}</td>
                            <td>{{ $class['subjects_count'] }}</td>
                            <td>{{ $class['teachers_count'] }}</td>
                            <td>{{ $class['timetables_count'] }}</td>
                            <td><a href="{{ route('panel.classes.show', $class['id']) }}">Open class</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8">No classes found for this school.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </article>
    </section>

    <section class="panel panel-form">
        <div class="panel-head">Recent Students</div>
        @if(count($recentStudents) === 0)
            <div class="empty">No students found.</div>
        @else
            <div class="top-list">
                @foreach($recentStudents as $student)
                    <div class="top-item">
                        <span class="rank-badge">{{ $loop->iteration }}</span>
                        <div>
                            <strong>{{ $student['name'] }}</strong>
                            <p class="text-muted">
                                {{ $student['student_code'] !== '' ? $student['student_code'].' | ' : '' }}
                                {{ $student['khmer_name'] !== '' ? $student['khmer_name'].' | ' : '' }}
                                Class: {{ $student['class_name'] }}{{ $student['grade'] !== '' ? ' | Grade '.$student['grade'] : '' }}
                            </p>
                        </div>
                        @if(($student['user_id'] ?? 0) > 0)
                            <a href="{{ route('panel.users.show', $student['user_id']) }}">Detail</a>
                        @else
                            <span class="text-muted">No detail</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endsection
