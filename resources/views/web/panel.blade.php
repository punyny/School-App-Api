@extends('web.layouts.app')

@section('content')
    @php
        $viewerName = (string) (request()->user()?->name ?? 'User');
        $panelValue = trim((string) ($panel ?? ''));
        $safePanel = $panelValue !== '' ? $panelValue : 'dashboard';

        $navigationLinks = [];
        $appendLink = function (string $label, string $routeName, array $params = []) use (&$navigationLinks): void {
            if (! \Illuminate\Support\Facades\Route::has($routeName)) {
                return;
            }

            $navigationLinks[] = [
                'label' => $label,
                'url' => route($routeName, $params, false),
            ];
        };

        $appendLink('My Profile', 'profile.show');

        if ($safePanel === 'super-admin') {
            $appendLink('Dashboard', 'super-admin.dashboard');
            $appendLink('Schools', 'super-admin.schools.index');
            $appendLink('Users', 'super-admin.users.index');
            $appendLink('Settings', 'super-admin.settings');
            $appendLink('Student Reports', 'panel.student-reports.index');
            $appendLink('Schools CRUD', 'panel.schools.index');
            $appendLink('Users CRUD', 'panel.users.index');
            $appendLink('Classes CRUD', 'panel.classes.index');
        } elseif ($safePanel === 'admin') {
            $appendLink('Dashboard', 'admin.dashboard');
            $appendLink('Users', 'admin.users.index');
            $appendLink('Students', 'admin.students.index');
            $appendLink('Classes', 'admin.classes.index');
            $appendLink('Reports', 'admin.reports.index');
            $appendLink('Student Reports', 'panel.student-reports.index');
            $appendLink('Attendance CRUD', 'panel.attendance.index');
            $appendLink('Students CRUD', 'panel.students.index');
            $appendLink('Scores CRUD', 'panel.scores.index');
            $appendLink('Messages CRUD', 'panel.messages.index');
        } elseif ($safePanel === 'teacher') {
            $appendLink('Dashboard', 'teacher.dashboard');
            $appendLink('Students', 'panel.students.index');
            $appendLink('Attendance', 'teacher.attendance.index');
            $appendLink('Homeworks', 'teacher.homeworks.index');
            $appendLink('Scores', 'teacher.scores.index');
            $appendLink('Messages', 'teacher.messages.index');
            $appendLink('Timetable', 'panel.timetables.index');
        } elseif ($safePanel === 'student') {
            $appendLink('Dashboard', 'student.dashboard');
            $appendLink('Leave Requests', 'panel.leave-requests.index');
            $appendLink('Messages', 'panel.messages.index');
        } elseif ($safePanel === 'parent') {
            $appendLink('Dashboard', 'parent.dashboard');
            $appendLink('Leave Requests', 'panel.leave-requests.index');
            $appendLink('Messages', 'panel.messages.index');
        }

        $payload = [
            'title' => (string) ($title ?? 'Dashboard'),
            'subtitle' => (string) ($subtitle ?? ''),
            'stats' => $stats ?? [],
            'tableTitle' => (string) ($tableTitle ?? 'Overview'),
            'columns' => $columns ?? [],
            'rows' => $rows ?? [],
            'panel' => $safePanel,
            'viewerName' => $viewerName,
            'csrfToken' => csrf_token(),
            'modules' => $modules ?? ($teacherDashboardModules ?? []),
            'schoolCards' => $schoolCards ?? [],
            'schoolImageAccept' => (string) ($schoolImageAccept ?? ''),
            'schoolImageMaxMb' => (string) ($schoolImageMaxMb ?? ''),
            'childOptions' => $childOptions ?? [],
            'selectedChildId' => (string) ($selectedChildId ?? ''),
            'navigationLinks' => $navigationLinks,
            'currentPath' => request()->path() === '' ? '/' : '/'.request()->path(),
        ];
    @endphp

    @if(session('success'))
        <p class="flash-success">{{ session('success') }}</p>
    @endif

    @if ($errors->any())
        <p class="flash-error">{{ $errors->first() }}</p>
    @endif

    <div id="react-panel-root"></div>

    <script>
        window.__PANEL_PAGE__ = @json($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    </script>
@endsection

@push('scripts')
    @vite('resources/js/react-panel.js')
@endpush
