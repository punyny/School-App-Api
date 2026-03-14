@extends('web.layouts.app')

@section('content')
    <div class="topbar">
        <div>
            <h1 class="title">{{ $title }}</h1>
            <p class="subtitle">{{ $subtitle }}</p>
        </div>
        <span class="role-pill">{{ strtoupper((string) $panel) }} PANEL</span>
    </div>

    <nav class="nav">
        <a href="{{ route('profile.show') }}" class="{{ request()->routeIs('profile.*') ? 'active' : '' }}">My Profile</a>
        @if ($panel === 'super-admin')
            <a href="{{ route('super-admin.dashboard') }}" class="{{ request()->routeIs('super-admin.dashboard') ? 'active' : '' }}">Dashboard</a>
            <a href="{{ route('super-admin.schools.index') }}" class="{{ request()->routeIs('super-admin.schools.*') ? 'active' : '' }}">Schools</a>
            <a href="{{ route('super-admin.users.index') }}" class="{{ request()->routeIs('super-admin.users.*') ? 'active' : '' }}">Users</a>
            <a href="{{ route('super-admin.settings') }}" class="{{ request()->routeIs('super-admin.settings') ? 'active' : '' }}">Settings</a>
            @can('web-manage-schools')
                <a href="{{ route('panel.schools.index') }}" class="{{ request()->routeIs('panel.schools.*') ? 'active' : '' }}">Schools CRUD</a>
            @endcan
            @can('web-manage-users')
                <a href="{{ route('panel.users.index') }}" class="{{ request()->routeIs('panel.users.*') ? 'active' : '' }}">Users CRUD</a>
            @endcan
            @can('web-manage-classes')
                <a href="{{ route('panel.classes.index') }}" class="{{ request()->routeIs('panel.classes.*') ? 'active' : '' }}">Classes CRUD</a>
            @endcan
            @can('web-manage-subjects')
                <a href="{{ route('panel.subjects.index') }}" class="{{ request()->routeIs('panel.subjects.*') ? 'active' : '' }}">Subjects CRUD</a>
            @endcan
            @can('web-manage-timetables')
                <a href="{{ route('panel.timetables.index') }}" class="{{ request()->routeIs('panel.timetables.*') ? 'active' : '' }}">Timetables CRUD</a>
            @endcan
            @if ($panel !== 'super-admin')
                @can('web-manage-students')
                    <a href="{{ route('panel.students.index') }}" class="{{ request()->routeIs('panel.students.*') ? 'active' : '' }}">Students CRUD</a>
                @endcan
            @endif
            @can('web-manage-announcements')
                <a href="{{ route('panel.announcements.index') }}" class="{{ request()->routeIs('panel.announcements.*') ? 'active' : '' }}">Announcements CRUD</a>
            @endcan
            @can('web-manage-attendance')
                <a href="{{ route('panel.attendance.index') }}" class="{{ request()->routeIs('panel.attendance.*') ? 'active' : '' }}">Attendance CRUD</a>
            @endcan
            @can('web-manage-scores')
                <a href="{{ route('panel.scores.index') }}" class="{{ request()->routeIs('panel.scores.*') ? 'active' : '' }}">Scores CRUD</a>
            @endcan
            @can('web-view-leave-requests')
                <a href="{{ route('panel.leave-requests.index') }}" class="{{ request()->routeIs('panel.leave-requests.*') ? 'active' : '' }}">Leave Requests CRUD</a>
            @endcan
            @can('web-manage-messages')
                <a href="{{ route('panel.messages.index') }}" class="{{ request()->routeIs('panel.messages.*') ? 'active' : '' }}">Messages CRUD</a>
            @endcan
            @can('web-manage-notifications')
                <a href="{{ route('panel.notifications.index') }}" class="{{ request()->routeIs('panel.notifications.*') ? 'active' : '' }}">Notifications CRUD</a>
            @endcan
            @can('web-manage-incident-reports')
                <a href="{{ route('panel.incident-reports.index') }}" class="{{ request()->routeIs('panel.incident-reports.*') ? 'active' : '' }}">Incident Reports CRUD</a>
            @endcan
            @can('web-view-audit-logs')
                <a href="{{ route('panel.audit-logs.index') }}" class="{{ request()->routeIs('panel.audit-logs.*') ? 'active' : '' }}">Audit Logs</a>
            @endcan
        @elseif ($panel === 'admin')
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">Dashboard</a>
            <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">Users</a>
            <a href="{{ route('admin.classes.index') }}" class="{{ request()->routeIs('admin.classes.*') ? 'active' : '' }}">Classes</a>
            <a href="{{ route('admin.subjects.index') }}" class="{{ request()->routeIs('admin.subjects.*') ? 'active' : '' }}">Subjects</a>
            <a href="{{ route('admin.students.index') }}" class="{{ request()->routeIs('admin.students.*') ? 'active' : '' }}">Students</a>
            <a href="{{ route('admin.reports.index') }}" class="{{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">Reports</a>
            @can('web-manage-users')
                <a href="{{ route('panel.users.index') }}" class="{{ request()->routeIs('panel.users.*') ? 'active' : '' }}">Users CRUD</a>
            @endcan
            @can('web-manage-classes')
                <a href="{{ route('panel.classes.index') }}" class="{{ request()->routeIs('panel.classes.*') ? 'active' : '' }}">Classes CRUD</a>
            @endcan
            @can('web-manage-subjects')
                <a href="{{ route('panel.subjects.index') }}" class="{{ request()->routeIs('panel.subjects.*') ? 'active' : '' }}">Subjects CRUD</a>
            @endcan
            @can('web-manage-timetables')
                <a href="{{ route('panel.timetables.index') }}" class="{{ request()->routeIs('panel.timetables.*') ? 'active' : '' }}">Timetables CRUD</a>
            @endcan
            @can('web-manage-students')
                <a href="{{ route('panel.students.index') }}" class="{{ request()->routeIs('panel.students.*') ? 'active' : '' }}">Students CRUD</a>
            @endcan
            @can('web-manage-announcements')
                <a href="{{ route('panel.announcements.index') }}" class="{{ request()->routeIs('panel.announcements.*') ? 'active' : '' }}">Announcements CRUD</a>
            @endcan
            @can('web-manage-attendance')
                <a href="{{ route('panel.attendance.index') }}" class="{{ request()->routeIs('panel.attendance.*') ? 'active' : '' }}">Attendance CRUD</a>
            @endcan
            @can('web-manage-scores')
                <a href="{{ route('panel.scores.index') }}" class="{{ request()->routeIs('panel.scores.*') ? 'active' : '' }}">Scores CRUD</a>
            @endcan
            @can('web-view-leave-requests')
                <a href="{{ route('panel.leave-requests.index') }}" class="{{ request()->routeIs('panel.leave-requests.*') ? 'active' : '' }}">Leave Requests CRUD</a>
            @endcan
            @can('web-manage-messages')
                <a href="{{ route('panel.messages.index') }}" class="{{ request()->routeIs('panel.messages.*') ? 'active' : '' }}">Messages CRUD</a>
            @endcan
            @can('web-manage-notifications')
                <a href="{{ route('panel.notifications.index') }}" class="{{ request()->routeIs('panel.notifications.*') ? 'active' : '' }}">Notifications CRUD</a>
            @endcan
            @can('web-manage-incident-reports')
                <a href="{{ route('panel.incident-reports.index') }}" class="{{ request()->routeIs('panel.incident-reports.*') ? 'active' : '' }}">Incident Reports CRUD</a>
            @endcan
            @can('web-view-audit-logs')
                <a href="{{ route('panel.audit-logs.index') }}" class="{{ request()->routeIs('panel.audit-logs.*') ? 'active' : '' }}">Audit Logs</a>
            @endcan
        @elseif ($panel === 'teacher')
            <a href="{{ route('teacher.dashboard') }}" class="{{ request()->routeIs('teacher.dashboard') ? 'active' : '' }}">Dashboard</a>
            <a href="{{ route('teacher.attendance.index') }}" class="{{ request()->routeIs('teacher.attendance.*') ? 'active' : '' }}">Attendance</a>
            <a href="{{ route('teacher.homeworks.index') }}" class="{{ request()->routeIs('teacher.homeworks.*') ? 'active' : '' }}">Homeworks</a>
            <a href="{{ route('teacher.scores.index') }}" class="{{ request()->routeIs('teacher.scores.*') ? 'active' : '' }}">Scores</a>
            <a href="{{ route('teacher.messages.index') }}" class="{{ request()->routeIs('teacher.messages.*') ? 'active' : '' }}">Messages</a>
            <a href="{{ route('teacher.incidents.index') }}" class="{{ request()->routeIs('teacher.incidents.*') ? 'active' : '' }}">Incidents</a>
            @can('web-manage-timetables')
                <a href="{{ route('panel.timetables.index') }}" class="{{ request()->routeIs('panel.timetables.*') ? 'active' : '' }}">Timetables CRUD</a>
            @endcan
            @can('web-manage-announcements')
                <a href="{{ route('panel.announcements.index') }}" class="{{ request()->routeIs('panel.announcements.*') ? 'active' : '' }}">Announcements CRUD</a>
            @endcan
            @can('web-manage-attendance')
                <a href="{{ route('panel.attendance.index') }}" class="{{ request()->routeIs('panel.attendance.*') ? 'active' : '' }}">Attendance CRUD</a>
            @endcan
            @can('web-manage-homeworks')
                <a href="{{ route('panel.homeworks.index') }}" class="{{ request()->routeIs('panel.homeworks.*') ? 'active' : '' }}">Homeworks CRUD</a>
            @endcan
            @can('web-manage-scores')
                <a href="{{ route('panel.scores.index') }}" class="{{ request()->routeIs('panel.scores.*') ? 'active' : '' }}">Scores CRUD</a>
            @endcan
            @can('web-view-leave-requests')
                <a href="{{ route('panel.leave-requests.index') }}" class="{{ request()->routeIs('panel.leave-requests.*') ? 'active' : '' }}">Leave Requests CRUD</a>
            @endcan
            @can('web-manage-messages')
                <a href="{{ route('panel.messages.index') }}" class="{{ request()->routeIs('panel.messages.*') ? 'active' : '' }}">Messages CRUD</a>
            @endcan
            @can('web-manage-notifications')
                <a href="{{ route('panel.notifications.index') }}" class="{{ request()->routeIs('panel.notifications.*') ? 'active' : '' }}">Notifications CRUD</a>
            @endcan
            @can('web-manage-incident-reports')
                <a href="{{ route('panel.incident-reports.index') }}" class="{{ request()->routeIs('panel.incident-reports.*') ? 'active' : '' }}">Incident Reports CRUD</a>
            @endcan
        @elseif ($panel === 'student')
            <a href="{{ route('student.dashboard') }}" class="{{ request()->routeIs('student.dashboard') ? 'active' : '' }}">Dashboard</a>
        @elseif ($panel === 'parent')
            <a href="{{ route('parent.dashboard') }}" class="{{ request()->routeIs('parent.dashboard') ? 'active' : '' }}">Dashboard</a>
        @endif
    </nav>

    @if(in_array($panel, ['super-admin', 'admin', 'teacher'], true) && !($panel === 'teacher' && request()->routeIs('teacher.dashboard')))
        <section class="panel panel-form panel-spaced">
            <div class="panel-head">Operation Modules</div>
            <div class="module-grid">
                @can('web-manage-users')
                    <a class="module-link" href="{{ route('panel.users.index', ['role' => 'teacher']) }}">Teacher: list / update / delete</a>
                @endcan
                @if ($panel !== 'super-admin')
                    @can('web-manage-students')
                        <a class="module-link" href="{{ route('panel.students.index') }}">Student: list / create / delete</a>
                    @endcan
                @endif
                @can('web-manage-users')
                    <a class="module-link" href="{{ route('panel.users.index', ['role' => 'parent']) }}">Parent: create / list / update</a>
                @endcan
                @can('web-manage-classes')
                    <a class="module-link" href="{{ route('panel.classes.index') }}">Class: class / room / grade level</a>
                @endcan
                @can('web-manage-attendance')
                    <a class="module-link" href="{{ route('panel.attendance.index') }}">Attendance Check: P / A / L</a>
                @endcan
                @can('web-manage-subjects')
                    <a class="module-link" href="{{ route('panel.subjects.index') }}">Subject: list / create / delete</a>
                @endcan
                @can('web-manage-announcements')
                    <a class="module-link" href="{{ route('panel.announcements.index') }}">School News: create / list / update</a>
                @endcan
                @can('web-manage-notifications')
                    <a class="module-link" href="{{ route('panel.notifications.index') }}">Notification: to teacher / student / class</a>
                @endcan
                @can('web-manage-scores')
                    <a class="module-link" href="{{ route('panel.scores.index') }}">Score: monthly / semester / yearly / rank</a>
                @endcan
                @can('web-manage-timetables')
                    <a class="module-link" href="{{ route('panel.timetables.index') }}">Timetable: add / update / view</a>
                @endcan
            </div>
        </section>
    @endif

    @if ($panel === 'parent')
        <form method="GET" action="{{ route('parent.dashboard') }}" class="panel panel-form panel-spaced">
            <div class="form-grid-wide">
                <div>
                    <label class="label-tight">Select Child</label>
                    <select name="student_id">
                        @forelse(($childOptions ?? []) as $childOption)
                            <option value="{{ $childOption['id'] }}" {{ ($selectedChildId ?? '') === (string) $childOption['id'] ? 'selected' : '' }}>{{ $childOption['label'] }}</option>
                        @empty
                            <option value="">No child linked</option>
                        @endforelse
                    </select>
                </div>
            </div>
            <button type="submit" class="btn-space-top" {{ empty($childOptions ?? []) ? 'disabled' : '' }}>Apply</button>
        </form>
    @endif

    <section class="cards">
        @foreach($stats as $item)
            <div class="card">
                <div class="label">{{ $item['label'] }}</div>
                <div class="value">{{ $item['value'] }}</div>
            </div>
        @endforeach
    </section>

    <section class="panel">
        <div class="panel-head">{{ $tableTitle }}</div>
        @if (count($rows) === 0)
            <div class="empty">No data available.</div>
        @else
            <table>
                <thead>
                    <tr>
                        @foreach ($columns as $column)
                            <th>{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            @foreach ($row as $value)
                                <td>{{ $value }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection
