<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Support\ProfileImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                    'image_url' => (string) ($school->image_url ?? ''),
                    'admin_count' => (int) ($school->admin_count ?? 0),
                    'teacher_count' => (int) ($school->teacher_count ?? 0),
                    'student_count' => (int) ($school->student_count ?? 0),
                    'parent_count' => (int) ($school->parent_count ?? 0),
                    'class_count' => (int) ($school->classes_count ?? 0),
                    'subject_count' => (int) ($school->subjects_count ?? 0),
                    'lead_admin_name' => $leadAdmin?->name,
                    'lead_admin_email' => $leadAdmin?->email,
                    'lead_admin_id' => $leadAdmin?->id,
                    'manage_url' => route('super-admin.schools.manage', ['school' => $school->id], false),
                    'update_image_url' => route('super-admin.schools.image.update', ['school' => $school->id], false),
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

        return view('web.panel', [
            'title' => 'Super Admin Dashboard',
            'subtitle' => 'System-wide school overview and configuration hub.',
            'stats' => [
                ['label' => 'Schools', 'value' => $schoolCount],
                ['label' => 'Schools Ready', 'value' => $schoolsReadyCount],
                ['label' => 'Admins', 'value' => $adminCount],
                ['label' => 'Teachers', 'value' => $teacherCount],
                ['label' => 'Students', 'value' => $studentCount],
                ['label' => 'Parents', 'value' => $parentCount],
                ['label' => 'Classes', 'value' => $classCount],
            ],
            'tableTitle' => 'School Directory',
            'columns' => [
                'School',
                'Code',
                'Location',
                'Admins',
                'Teachers',
                'Students',
                'Classes',
                'Subjects',
            ],
            'rows' => array_map(static fn (array $row): array => [
                [
                    'label' => (string) ($row['name'] ?? '-'),
                    'url' => (string) ($row['manage_url'] ?? '#'),
                ],
                (string) ($row['school_code'] ?? '-'),
                (string) ($row['location'] ?? '-'),
                (string) ($row['admin_count'] ?? 0),
                (string) ($row['teacher_count'] ?? 0),
                (string) ($row['student_count'] ?? 0),
                (string) ($row['class_count'] ?? 0),
                (string) ($row['subject_count'] ?? 0),
            ], $schools),
            'schoolCards' => $schools,
            'schoolImageAccept' => 'image/png,image/jpeg,image/webp,.jpg,.jpeg,.png,.webp',
            'schoolImageMaxMb' => ProfileImageStorage::maxUploadMb(),
            'modules' => [
                [
                    'number' => 1,
                    'title' => 'School Control',
                    'description' => 'Open, create, and configure school instances.',
                    'metric_label' => 'Schools',
                    'metric_value' => $schoolCount,
                    'links' => [
                        ['label' => 'Schools', 'url' => route('super-admin.schools.index')],
                        ['label' => 'Create School', 'url' => route('panel.schools.create')],
                    ],
                ],
                [
                    'number' => 2,
                    'title' => 'Admin Directory',
                    'description' => 'Manage admin users and school ownership.',
                    'metric_label' => 'Admins',
                    'metric_value' => count($adminDirectory),
                    'links' => [
                        ['label' => 'Admins', 'url' => route('panel.users.index', ['role' => 'admin'])],
                        ['label' => 'Create Admin', 'url' => route('panel.users.create', ['role' => 'admin'])],
                    ],
                ],
                [
                    'number' => 3,
                    'title' => 'Health & Audit',
                    'description' => 'Monitor assignment health and system activity.',
                    'metric_label' => 'Health Rows',
                    'metric_value' => count($schoolHealth),
                    'links' => [
                        ['label' => 'Audit Logs', 'url' => route('panel.audit-logs.index')],
                        ['label' => 'Settings', 'url' => route('super-admin.settings')],
                    ],
                ],
            ],
            'panel' => 'super-admin',
        ]);
    }

    public function updateSchoolImage(Request $request, School $school): RedirectResponse
    {
        $payload = $request->validate([
            'school_image' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'mimes:jpg,jpeg,png,webp',
                'max:'.ProfileImageStorage::maxUploadKb(),
            ],
        ]);

        $imageUrl = ProfileImageStorage::storeForModel(
            $payload['school_image'],
            $school,
            $request->user(),
            'schools',
            'school-logo',
            ['source' => 'super-admin-dashboard']
        );

        $school->forceFill([
            'image_url' => $imageUrl,
        ])->save();

        return back()->with('success', 'School image updated successfully.');
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

        $schoolCode = trim((string) ($school->school_code ?? ''));
        $campus = trim((string) ($school->location ?? ''));

        return view('web.panel', [
            'title' => 'Manage School: '.$school->name,
            'subtitle' => 'Code: '.($schoolCode !== '' ? $schoolCode : 'N/A').' | Campus: '.($campus !== '' ? $campus : 'Not set'),
            'stats' => [
                ['label' => 'Admins', 'value' => (int) ($school->admin_count ?? 0)],
                ['label' => 'Teachers', 'value' => (int) ($school->teacher_count ?? 0)],
                ['label' => 'Students', 'value' => (int) ($school->student_count ?? 0)],
                ['label' => 'Parents', 'value' => (int) ($school->parent_count ?? 0)],
                ['label' => 'Classes', 'value' => (int) ($school->classes_count ?? 0)],
                ['label' => 'Subjects', 'value' => (int) ($school->subjects_count ?? 0)],
                ['label' => 'Recent Students', 'value' => count($recentStudents)],
                ['label' => 'School Admin Rows', 'value' => $admins->count()],
            ],
            'tableTitle' => 'Classes In This School',
            'columns' => ['Class', 'Grade', 'Room', 'Students', 'Subjects', 'Teachers', 'Routine', 'Action'],
            'rows' => array_map(fn (array $class): array => [
                (string) ($class['name'] ?? '-'),
                (string) (($class['grade_level'] ?? '') !== '' ? $class['grade_level'] : '-'),
                (string) (($class['room'] ?? '') !== '' ? $class['room'] : '-'),
                (string) ($class['students_count'] ?? 0),
                (string) ($class['subjects_count'] ?? 0),
                (string) ($class['teachers_count'] ?? 0),
                (string) ($class['timetables_count'] ?? 0),
                [
                    'label' => 'Open Class',
                    'url' => route('panel.classes.show', (int) ($class['id'] ?? 0)),
                ],
            ], $classes),
            'modules' => [
                [
                    'number' => 1,
                    'title' => 'School Admins',
                    'description' => 'Open and manage administrator accounts for this school.',
                    'metric_label' => 'Admins',
                    'metric_value' => $admins->count(),
                    'links' => [
                        ['label' => 'View Admins', 'url' => route('panel.users.index', ['school_id' => $school->id, 'role' => 'admin'])],
                        ['label' => 'Add Admin', 'url' => route('panel.users.create', ['role' => 'admin', 'school_id' => $school->id])],
                    ],
                ],
                [
                    'number' => 2,
                    'title' => 'Teachers & Students',
                    'description' => 'Manage school teachers, students, and guardians.',
                    'metric_label' => 'Users',
                    'metric_value' => (int) ($school->teacher_count ?? 0) + (int) ($school->student_count ?? 0) + (int) ($school->parent_count ?? 0),
                    'links' => [
                        ['label' => 'Teachers', 'url' => route('panel.users.index', ['school_id' => $school->id, 'role' => 'teacher'])],
                        ['label' => 'Students', 'url' => route('panel.users.index', ['school_id' => $school->id, 'role' => 'student'])],
                    ],
                ],
                [
                    'number' => 3,
                    'title' => 'Academic Setup',
                    'description' => 'Configure classes, subjects, and timetable for this school.',
                    'metric_label' => 'Academic Items',
                    'metric_value' => (int) ($school->classes_count ?? 0) + (int) ($school->subjects_count ?? 0),
                    'links' => [
                        ['label' => 'Classes', 'url' => route('panel.classes.index', ['school_id' => $school->id])],
                        ['label' => 'Subjects', 'url' => route('panel.subjects.index', ['school_id' => $school->id])],
                        ['label' => 'Timetable', 'url' => route('panel.timetables.index', ['school_id' => $school->id])],
                    ],
                ],
                [
                    'number' => 4,
                    'title' => 'School Actions',
                    'description' => 'Continue setup and communication flow.',
                    'metric_label' => 'Quick Actions',
                    'metric_value' => 4,
                    'links' => [
                        ['label' => 'Edit School', 'url' => route('panel.schools.edit', $school->id)],
                        ['label' => 'Create Class', 'url' => route('panel.classes.create')],
                        ['label' => 'Create Subject', 'url' => route('panel.subjects.create')],
                        ['label' => 'Create Announcement', 'url' => route('panel.announcements.create')],
                    ],
                ],
            ],
            'panel' => 'super-admin',
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
