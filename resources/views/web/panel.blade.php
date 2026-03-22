@extends('web.layouts.app')

@section('content')
    @php
        $statCount = count($stats ?? []);
        $rowCount = count($rows ?? []);
        $viewerName = (string) (request()->user()?->name ?? 'User');
    @endphp

    <style>
        .panel-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(280px, 0.72fr);
            gap: 14px;
            margin-bottom: 16px;
        }

        .panel-hero-main,
        .panel-hero-side {
            border-radius: 24px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .panel-hero-main {
            position: relative;
            padding: 24px;
            border: 1px solid rgba(15, 118, 110, 0.12);
            background:
                radial-gradient(circle at top right, rgba(96, 165, 250, 0.16), transparent 34%),
                linear-gradient(135deg, rgba(15, 118, 110, 0.08), rgba(255, 255, 255, 0.98));
        }

        .panel-hero-main::after {
            content: "";
            position: absolute;
            right: -54px;
            bottom: -70px;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: rgba(15, 118, 110, 0.08);
        }

        .panel-hero-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 16px;
        }

        .panel-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 999px;
            border: 1px solid rgba(15, 118, 110, 0.14);
            background: rgba(255, 255, 255, 0.94);
            color: var(--primary-2);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .2px;
        }

        .panel-hero-side {
            padding: 18px;
            color: #fff;
            background: linear-gradient(145deg, #134e4a, #0f766e 54%, #2b6cb0);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 14px;
        }

        .panel-hero-side h3 {
            margin: 0;
            font-size: 16px;
        }

        .panel-hero-side p {
            margin: 6px 0 0;
            color: rgba(255, 255, 255, 0.84);
            font-size: 13px;
            line-height: 1.6;
        }

        .panel-quick-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .panel-quick-card {
            border-radius: 16px;
            padding: 14px 12px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.14);
        }

        .panel-quick-card strong {
            display: block;
            font-size: 24px;
            line-height: 1;
        }

        .panel-quick-card span {
            display: block;
            margin-top: 6px;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.82);
        }

        .panel-nav-wrap,
        .panel-modules-wrap,
        .panel-table-wrap {
            border-radius: 18px;
            border: 1px solid var(--line);
            background: #fff;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 14px;
        }

        .panel-wrap-head {
            padding: 14px 16px;
            border-bottom: 1px solid var(--line);
            background: linear-gradient(120deg, #ffffff 0%, var(--surface-soft) 100%);
        }

        .panel-wrap-head h3 {
            margin: 0;
            font-size: 15px;
        }

        .panel-wrap-head p {
            margin: 4px 0 0;
            color: var(--text-muted);
            font-size: 13px;
        }

        .panel-nav-wrap .nav,
        .panel-modules-wrap .module-grid {
            margin: 0;
            padding: 14px 16px 16px;
        }

        .panel-table-wrap .cards,
        .panel-table-wrap .panel {
            margin: 0;
            box-shadow: none;
            border: none;
            border-radius: 0;
        }

        .panel-table-wrap .panel-head {
            background: transparent;
            border-bottom: 1px solid var(--line);
        }

        @media (max-width: 980px) {
            .panel-hero {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 720px) {
            .panel-quick-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <section class="panel-hero">
        <div class="panel-hero-main">
            <div class="topbar" style="margin:0;">
                <div>
                    <h1 class="title">{{ $title }}</h1>
                    <p class="subtitle">{{ $subtitle }}</p>
                </div>
                <span class="role-pill">{{ strtoupper((string) $panel) }} PANEL</span>
            </div>
            <div class="panel-hero-chips">
                <span class="panel-chip">Signed in as {{ $viewerName }}</span>
                <span class="panel-chip">{{ $statCount }} overview metrics</span>
                <span class="panel-chip">{{ $rowCount }} table rows</span>
            </div>
        </div>
        <aside class="panel-hero-side">
            <div>
                <h3>Workspace summary</h3>
                <p>Your dashboard is organized by role so the most important actions and recent data stay easy to find.</p>
            </div>
            <div class="panel-quick-grid">
                <div class="panel-quick-card">
                    <strong>{{ $statCount }}</strong>
                    <span>Quick stats</span>
                </div>
                <div class="panel-quick-card">
                    <strong>{{ $rowCount }}</strong>
                    <span>Recent records</span>
                </div>
                <div class="panel-quick-card">
                    <strong>{{ strtoupper(substr((string) $panel, 0, 1)) }}</strong>
                    <span>Role view</span>
                </div>
            </div>
        </aside>
    </section>

    <section class="panel-nav-wrap">
        <div class="panel-wrap-head">
            <h3>Navigation</h3>
            <p>Move between your main dashboard areas and the modules available for your role.</p>
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
    </section>

    @if(in_array($panel, ['super-admin', 'admin', 'teacher'], true) && !($panel === 'teacher' && request()->routeIs('teacher.dashboard')))
        <section class="panel-modules-wrap">
            <div class="panel-wrap-head">
                <h3>Operation Modules</h3>
                <p>Shortcuts for the most common tasks in your current role.</p>
            </div>
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

    <section class="panel-table-wrap">
        <div class="panel-wrap-head">
            <h3>Overview</h3>
            <p>Your current role summary and latest records from the system.</p>
        </div>
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
    </section>
@endsection
