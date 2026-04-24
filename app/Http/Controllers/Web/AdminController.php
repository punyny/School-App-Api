<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Score;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function dashboard(Request $request): View
    {
        $schoolId = (int) $request->user()->school_id;

        $teacherCount = User::query()
            ->where('school_id', $schoolId)
            ->whereRaw('LOWER(role) = ?', ['teacher'])
            ->count();
        $studentCount = User::query()
            ->where('school_id', $schoolId)
            ->whereRaw('LOWER(role) = ?', ['student'])
            ->count();
        $parentCount = User::query()
            ->where('school_id', $schoolId)
            ->whereRaw('LOWER(role) in (?, ?)', ['parent', 'guardian'])
            ->count();
        $classCount = SchoolClass::query()->where('school_id', $schoolId)->count();

        $attendanceByStatus = [
            'P' => Attendance::query()->where('status', 'P')->whereHas('class', fn ($query) => $query->where('school_id', $schoolId))->count(),
            'A' => Attendance::query()->where('status', 'A')->whereHas('class', fn ($query) => $query->where('school_id', $schoolId))->count(),
            'L' => Attendance::query()->where('status', 'L')->whereHas('class', fn ($query) => $query->where('school_id', $schoolId))->count(),
        ];
        $attendanceTotal = array_sum($attendanceByStatus);

        $topStudents = Score::query()
            ->with(['student.user', 'class'])
            ->whereHas('class', fn ($query) => $query->where('school_id', $schoolId))
            ->orderByDesc('total_score')
            ->limit(5)
            ->get()
            ->map(fn (Score $score) => [
                'student_id' => (int) $score->student_id,
                'name' => $score->student?->user?->name ?? 'Student '.$score->student_id,
                'score' => (float) $score->total_score,
                'grade' => $score->grade ?: '-',
                'image_url' => $score->student?->user?->image_url,
                'class_name' => $score->class?->name ?: '-',
            ])->all();

        $recentUsers = User::query()
            ->with('studentProfile')
            ->where('school_id', $schoolId)
            ->whereRaw('LOWER(role) in (?, ?)', ['teacher', 'student'])
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (User $user) => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'role' => $user->normalizedRole(),
                'email' => $user->email,
                'image_url' => $user->image_url,
                'active' => (bool) $user->active,
                'student_profile_id' => $user->studentProfile?->id,
            ])->all();

        return view('web.panel', [
            'title' => 'Admin Dashboard',
            'subtitle' => 'School control overview with quick access to key modules.',
            'stats' => [
                ['label' => 'Students', 'value' => $studentCount],
                ['label' => 'Teachers', 'value' => $teacherCount],
                ['label' => 'Parents', 'value' => $parentCount],
                ['label' => 'Classes', 'value' => $classCount],
                ['label' => 'Present (P)', 'value' => $attendanceByStatus['P'] ?? 0],
                ['label' => 'Absent (A)', 'value' => $attendanceByStatus['A'] ?? 0],
                ['label' => 'Leave (L)', 'value' => $attendanceByStatus['L'] ?? 0],
                ['label' => 'Total Attendance', 'value' => $attendanceTotal],
            ],
            'tableTitle' => 'Recent Students / Teachers',
            'columns' => ['Name', 'Role', 'Email', 'Status', 'Reference'],
            'rows' => array_map(static fn (array $row): array => [
                (string) ($row['name'] ?? '-'),
                ucfirst((string) ($row['role'] ?? '-')),
                (string) ($row['email'] ?? '-'),
                (bool) ($row['active'] ?? false) ? 'Active' : 'Inactive',
                (string) ($row['student_profile_id'] ?? $row['id'] ?? '-'),
            ], $recentUsers),
            'modules' => [
                [
                    'number' => 1,
                    'title' => 'Manage Users',
                    'description' => 'Create and manage teacher, student, and parent accounts.',
                    'metric_label' => 'Users',
                    'metric_value' => $teacherCount + $studentCount + $parentCount,
                    'links' => [
                        ['label' => 'Users', 'url' => route('panel.users.index')],
                        ['label' => 'Students', 'url' => route('admin.students.index')],
                        ['label' => 'Students CRUD', 'url' => route('panel.students.index')],
                        ['label' => 'New User', 'url' => route('panel.users.create')],
                    ],
                ],
                [
                    'number' => 2,
                    'title' => 'Class Setup',
                    'description' => 'Organize classes, subjects, and timetable flow.',
                    'metric_label' => 'Classes',
                    'metric_value' => $classCount,
                    'links' => [
                        ['label' => 'Classes', 'url' => route('panel.classes.index')],
                        ['label' => 'Subjects', 'url' => route('panel.subjects.index')],
                    ],
                ],
                [
                    'number' => 3,
                    'title' => 'Attendance & Scores',
                    'description' => 'Monitor attendance and score records from one place.',
                    'metric_label' => 'Records',
                    'metric_value' => $attendanceTotal,
                    'links' => [
                        ['label' => 'Attendance', 'url' => route('panel.attendance.index')],
                        ['label' => 'Scores', 'url' => route('panel.scores.index')],
                    ],
                ],
                [
                    'number' => 4,
                    'title' => 'Top Students',
                    'description' => 'Review highest score rows for quick recognition.',
                    'metric_label' => 'Top Rows',
                    'metric_value' => count($topStudents),
                    'links' => [
                        ['label' => 'Scores Table', 'url' => route('panel.scores.index')],
                    ],
                ],
            ],
            'panel' => 'admin',
        ]);
    }

    public function users(Request $request): View
    {
        $schoolId = (int) $request->user()->school_id;

        return $this->renderPage(
            title: 'School Users',
            subtitle: 'All users assigned to your school.',
            stats: [
                ['label' => 'Total Users', 'value' => User::query()->where('school_id', $schoolId)->count()],
            ],
            tableTitle: 'Latest Users',
            columns: ['Name', 'Email', 'Role', 'Phone'],
            rows: User::query()
                ->where('school_id', $schoolId)
                ->latest('id')
                ->limit(25)
                ->get()
                ->map(fn (User $user) => [
                    $user->name,
                    $user->email,
                    $user->role,
                    $user->phone ?: '-',
                ])->all()
        );
    }

    public function classes(Request $request): View
    {
        $schoolId = (int) $request->user()->school_id;

        return $this->renderPage(
            title: 'Classes',
            subtitle: 'Class setup and student distribution.',
            stats: [
                ['label' => 'Total Classes', 'value' => SchoolClass::query()->where('school_id', $schoolId)->count()],
            ],
            tableTitle: 'Class List',
            columns: ['Name', 'Grade', 'Students'],
            rows: SchoolClass::query()
                ->where('school_id', $schoolId)
                ->withCount('students')
                ->orderBy('name')
                ->get()
                ->map(fn (SchoolClass $class) => [
                    $class->name,
                    $class->grade_level ?: '-',
                    (string) $class->students_count,
                ])->all()
        );
    }

    public function subjects(Request $request): View
    {
        $schoolId = (int) $request->user()->school_id;

        return $this->renderPage(
            title: 'Subjects',
            subtitle: 'Subjects available in your school.',
            stats: [
                ['label' => 'Total Subjects', 'value' => Subject::query()->where('school_id', $schoolId)->count()],
            ],
            tableTitle: 'Subject List',
            columns: ['Subject Name'],
            rows: Subject::query()
                ->where('school_id', $schoolId)
                ->orderBy('name')
                ->get()
                ->map(fn (Subject $subject) => [$subject->name])
                ->all()
        );
    }

    public function students(Request $request): View
    {
        $schoolId = (int) $request->user()->school_id;

        return $this->renderPage(
            title: 'Students',
            subtitle: 'Student profiles in your school.',
            stats: [
                ['label' => 'Total Students', 'value' => Student::query()->whereHas('user', fn ($query) => $query->where('school_id', $schoolId))->count()],
            ],
            tableTitle: 'Student List',
            columns: ['Name', 'Email', 'Class', 'Grade'],
            rows: Student::query()
                ->with(['user', 'class'])
                ->whereHas('user', fn ($query) => $query->where('school_id', $schoolId))
                ->latest('id')
                ->limit(30)
                ->get()
                ->map(fn (Student $student) => [
                    $student->user?->name ?? '-',
                    $student->user?->email ?? '-',
                    $student->class?->name ?? '-',
                    $student->grade ?: '-',
                ])->all()
        );
    }

    public function reports(Request $request): View
    {
        $schoolId = (int) $request->user()->school_id;

        $attendanceSummary = [
            ['Present (P)', (string) \App\Models\Attendance::query()->where('status', 'P')->whereHas('class', fn ($query) => $query->where('school_id', $schoolId))->count()],
            ['Absent (A)', (string) \App\Models\Attendance::query()->where('status', 'A')->whereHas('class', fn ($query) => $query->where('school_id', $schoolId))->count()],
            ['Leave (L)', (string) \App\Models\Attendance::query()->where('status', 'L')->whereHas('class', fn ($query) => $query->where('school_id', $schoolId))->count()],
        ];

        return $this->renderPage(
            title: 'Reports',
            subtitle: 'Quick attendance summary for your school.',
            stats: [
                ['label' => 'Recorded Attendance', 'value' => \App\Models\Attendance::query()->whereHas('class', fn ($query) => $query->where('school_id', $schoolId))->count()],
            ],
            tableTitle: 'Attendance Summary',
            columns: ['Status', 'Total'],
            rows: $attendanceSummary
        );
    }

    /**
     * @param  array<int, array{label: string, value: string|int}>  $stats
     * @param  array<int, string>  $columns
     * @param  array<int, array<int, string>>  $rows
     */
    private function renderPage(
        string $title,
        string $subtitle,
        array $stats,
        string $tableTitle,
        array $columns,
        array $rows
    ): View {
        return view('web.panel', [
            'title' => $title,
            'subtitle' => $subtitle,
            'stats' => $stats,
            'tableTitle' => $tableTitle,
            'columns' => $columns,
            'rows' => $rows,
            'panel' => 'admin',
        ]);
    }
}
