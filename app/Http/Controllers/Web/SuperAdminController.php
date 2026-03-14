<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SuperAdminController extends Controller
{
    public function dashboard(): View
    {
        $schoolCount = School::query()->count();
        $adminCount = User::query()->whereRaw('LOWER(role) = ?', ['admin'])->count();
        $teacherCount = User::query()->whereRaw('LOWER(role) = ?', ['teacher'])->count();
        $studentCount = User::query()->whereRaw('LOWER(role) = ?', ['student'])->count();
        $parentCount = User::query()->whereRaw('LOWER(role) in (?, ?)', ['parent', 'guardian'])->count();
        $classCount = SchoolClass::query()->count();
        $schoolsReadyCount = School::query()
            ->whereExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('users')
                ->whereColumn('users.school_id', 'schools.id')
                ->whereRaw('LOWER(users.role) = ?', ['admin']))
            ->count();

        $schools = School::query()
            ->withCount([
                'users as admin_count' => fn ($query) => $query->whereRaw('LOWER(role) = ?', ['admin']),
                'users as teacher_count' => fn ($query) => $query->whereRaw('LOWER(role) = ?', ['teacher']),
                'users as student_count' => fn ($query) => $query->whereRaw('LOWER(role) = ?', ['student']),
                'users as parent_count' => fn ($query) => $query->whereRaw('LOWER(role) in (?, ?)', ['parent', 'guardian']),
                'classes',
                'subjects',
            ])
            ->with([
                'users' => fn ($query) => $query
                    ->whereRaw('LOWER(role) = ?', ['admin'])
                    ->orderBy('name')
                    ->select(['id', 'school_id', 'name', 'email', 'phone', 'active']),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (School $school): array {
                /** @var User|null $leadAdmin */
                $leadAdmin = $school->users->first();

                return [
                    'id' => (int) $school->id,
                    'name' => (string) $school->name,
                    'school_code' => (string) ($school->school_code ?? ''),
                    'location' => (string) ($school->location ?? ''),
                    'admin_count' => (int) ($school->admin_count ?? 0),
                    'teacher_count' => (int) ($school->teacher_count ?? 0),
                    'student_count' => (int) ($school->student_count ?? 0),
                    'parent_count' => (int) ($school->parent_count ?? 0),
                    'class_count' => (int) ($school->classes_count ?? 0),
                    'subject_count' => (int) ($school->subjects_count ?? 0),
                    'lead_admin_name' => $leadAdmin?->name,
                    'lead_admin_email' => $leadAdmin?->email,
                    'lead_admin_id' => $leadAdmin?->id,
                ];
            })
            ->all();

        $adminDirectory = User::query()
            ->with('school')
            ->whereRaw('LOWER(role) = ?', ['admin'])
            ->latest('id')
            ->limit(12)
            ->get()
            ->map(fn (User $admin) => [
                'id' => (int) $admin->id,
                'name' => (string) $admin->name,
                'email' => (string) $admin->email,
                'phone' => (string) ($admin->phone ?? '-'),
                'active' => (bool) $admin->active,
                'school_id' => (int) ($admin->school_id ?? 0),
                'school_name' => (string) ($admin->school?->name ?? 'No school'),
                'school_code' => (string) ($admin->school?->school_code ?? ''),
            ])
            ->all();

        $schoolHealth = School::query()
            ->leftJoin('classes', 'classes.school_id', '=', 'schools.id')
            ->leftJoin('teacher_class', 'teacher_class.class_id', '=', 'classes.id')
            ->select('schools.id', 'schools.name', 'schools.school_code')
            ->selectRaw('COUNT(DISTINCT classes.id) as class_count')
            ->selectRaw('COUNT(DISTINCT teacher_class.teacher_id) as teacher_assignment_count')
            ->selectRaw('COUNT(DISTINCT teacher_class.subject_id) as subject_assignment_count')
            ->groupBy('schools.id', 'schools.name', 'schools.school_code')
            ->orderBy('schools.name')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'school_code' => (string) ($row->school_code ?? ''),
                'class_count' => (int) ($row->class_count ?? 0),
                'teacher_assignment_count' => (int) ($row->teacher_assignment_count ?? 0),
                'subject_assignment_count' => (int) ($row->subject_assignment_count ?? 0),
            ])
            ->all();

        return view('web.super_admin.dashboard', [
            'schoolCount' => $schoolCount,
            'adminCount' => $adminCount,
            'teacherCount' => $teacherCount,
            'studentCount' => $studentCount,
            'parentCount' => $parentCount,
            'classCount' => $classCount,
            'schoolsReadyCount' => $schoolsReadyCount,
            'schools' => $schools,
            'adminDirectory' => $adminDirectory,
            'schoolHealth' => $schoolHealth,
        ]);
    }

    public function schools(): View
    {
        return $this->renderPage(
            title: 'Schools',
            subtitle: 'All school instances managed by super-admin.',
            stats: [
                ['label' => 'Total Schools', 'value' => School::query()->count()],
            ],
            tableTitle: 'School Directory',
            columns: ['Name', 'Location', 'Users', 'Classes', 'Subjects'],
            rows: School::query()
                ->withCount(['users', 'classes', 'subjects'])
                ->orderBy('name')
                ->get()
                ->map(fn (School $school) => [
                    $school->name,
                    $school->location ?: '-',
                    (string) $school->users_count,
                    (string) $school->classes_count,
                    (string) $school->subjects_count,
                ])->all()
        );
    }

    public function users(): View
    {
        return $this->renderPage(
            title: 'All Users',
            subtitle: 'Cross-school user management view.',
            stats: [
                ['label' => 'Super Admin', 'value' => User::query()->whereRaw('LOWER(role) = ?', ['super-admin'])->count()],
                ['label' => 'Admins', 'value' => User::query()->whereRaw('LOWER(role) = ?', ['admin'])->count()],
                ['label' => 'Teachers', 'value' => User::query()->whereRaw('LOWER(role) = ?', ['teacher'])->count()],
                ['label' => 'Parents', 'value' => User::query()->whereRaw('LOWER(role) in (?, ?)', ['parent', 'guardian'])->count()],
            ],
            tableTitle: 'Latest 20 Users',
            columns: ['Name', 'Email', 'Role', 'Active', 'School'],
            rows: User::query()
                ->with('school')
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(fn (User $user) => [
                    $user->name,
                    $user->email,
                    $user->normalizedRole(),
                    $user->active ? 'Yes' : 'No',
                    $user->school?->name ?? '-',
                ])->all()
        );
    }

    public function manageSchool(School $school): View
    {
        $school->loadCount([
            'classes',
            'subjects',
            'users as admin_count' => fn ($query) => $query->whereRaw('LOWER(role) = ?', ['admin']),
            'users as teacher_count' => fn ($query) => $query->whereRaw('LOWER(role) = ?', ['teacher']),
            'users as student_count' => fn ($query) => $query->whereRaw('LOWER(role) = ?', ['student']),
            'users as parent_count' => fn ($query) => $query->whereRaw('LOWER(role) in (?, ?)', ['parent', 'guardian']),
        ]);

        $admins = User::query()
            ->where('school_id', $school->id)
            ->whereRaw('LOWER(role) = ?', ['admin'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'active']);

        $classes = SchoolClass::query()
            ->where('school_id', $school->id)
            ->select('classes.*')
            ->selectSub(
                DB::table('teacher_class')
                    ->selectRaw('COUNT(DISTINCT subject_id)')
                    ->whereColumn('teacher_class.class_id', 'classes.id'),
                'subjects_count'
            )
            ->selectSub(
                DB::table('teacher_class')
                    ->selectRaw('COUNT(DISTINCT teacher_id)')
                    ->whereColumn('teacher_class.class_id', 'classes.id'),
                'teachers_count'
            )
            ->withCount(['students', 'timetables'])
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get()
            ->map(fn (SchoolClass $schoolClass) => [
                'id' => (int) $schoolClass->id,
                'name' => (string) $schoolClass->name,
                'grade_level' => (string) ($schoolClass->grade_level ?? ''),
                'room' => (string) ($schoolClass->room ?? ''),
                'students_count' => (int) ($schoolClass->students_count ?? 0),
                'subjects_count' => (int) ($schoolClass->subjects_count ?? 0),
                'teachers_count' => (int) ($schoolClass->teachers_count ?? 0),
                'timetables_count' => (int) ($schoolClass->timetables_count ?? 0),
            ])
            ->all();

        $recentStudents = Student::query()
            ->with(['user', 'class'])
            ->whereHas('user', fn ($query) => $query->where('school_id', $school->id))
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (Student $student) => [
                'id' => (int) $student->id,
                'user_id' => (int) ($student->user?->id ?? 0),
                'name' => (string) ($student->user?->name ?? 'Student '.$student->id),
                'khmer_name' => (string) ($student->user?->khmer_name ?? ''),
                'student_code' => (string) ($student->student_code ?? ''),
                'class_name' => (string) ($student->class?->name ?? 'Unassigned'),
                'grade' => (string) ($student->grade ?? ''),
            ])
            ->all();

        return view('web.super_admin.manage_school', [
            'school' => $school,
            'admins' => $admins,
            'classes' => $classes,
            'recentStudents' => $recentStudents,
        ]);
    }

    public function settings(): View
    {
        return $this->renderPage(
            title: 'System Settings',
            subtitle: 'Global configuration summary.',
            stats: [
                ['label' => 'App Name', 'value' => (string) config('app.name')],
                ['label' => 'Timezone', 'value' => (string) config('app.timezone')],
                ['label' => 'Environment', 'value' => (string) config('app.env')],
            ],
            tableTitle: 'Security Defaults',
            columns: ['Setting', 'Value'],
            rows: [
                ['Password Timeout', (string) config('auth.password_timeout').'s'],
                ['Session Driver', (string) config('session.driver')],
                ['Queue Connection', (string) config('queue.default')],
            ]
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
            'panel' => 'super-admin',
        ]);
    }
}
