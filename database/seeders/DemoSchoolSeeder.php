<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Homework;
use App\Models\HomeworkStatus;
use App\Models\IncidentReport;
use App\Models\LeaveRequest;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Score;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Timetable;
use App\Models\User;
use Database\Factories\SchoolClassFactory;
use Database\Factories\SchoolFactory;
use Database\Factories\SubjectFactory;
use Database\Factories\UserFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoSchoolSeeder extends Seeder
{
    public function run(): void
    {
        $schoolSeed = SchoolFactory::new()->make([
            'name' => 'Demo School',
            'school_code' => 'DEMO-001',
            'location' => 'Phnom Penh',
            'config_details' => [
                'timezone' => 'Asia/Phnom_Penh',
                'academic_year' => '2025-2026',
                'language' => 'km',
            ],
        ]);

        $school = School::query()->firstOrCreate(
            ['name' => 'Demo School'],
            $schoolSeed->toArray()
        );

        $classA1 = SchoolClass::query()->firstOrCreate(
            ['school_id' => $school->id, 'name' => 'A1', 'grade_level' => 'Grade 7'],
            SchoolClassFactory::new()->make([
                'school_id' => $school->id,
                'name' => 'A1',
                'grade_level' => 'Grade 7',
                'room' => 'B-201',
            ])->toArray()
        );

        $classA2 = SchoolClass::query()->firstOrCreate(
            ['school_id' => $school->id, 'name' => 'A2', 'grade_level' => 'Grade 8'],
            SchoolClassFactory::new()->make([
                'school_id' => $school->id,
                'name' => 'A2',
                'grade_level' => 'Grade 8',
                'room' => 'B-202',
            ])->toArray()
        );

        $math = Subject::query()->firstOrCreate(
            ['school_id' => $school->id, 'name' => 'Mathematics'],
            SubjectFactory::new()->make([
                'school_id' => $school->id,
                'name' => 'Mathematics',
            ])->toArray()
        );
        $khmer = Subject::query()->firstOrCreate(
            ['school_id' => $school->id, 'name' => 'Khmer'],
            SubjectFactory::new()->make([
                'school_id' => $school->id,
                'name' => 'Khmer',
            ])->toArray()
        );
        $english = Subject::query()->firstOrCreate(
            ['school_id' => $school->id, 'name' => 'English'],
            SubjectFactory::new()->make([
                'school_id' => $school->id,
                'name' => 'English',
            ])->toArray()
        );

        $khmerCoreSubjects = [
            'កម្មវិធីចំណេះដឹងទូទៅខ្មែរ',
            'ភាសាខ្មែរ',
            'គណិតវិទ្យា',
            'រូបវិទ្យា',
            'គីមីវិទ្យា',
            'ជីវវិទ្យា',
            'ផែនដីវិទ្យា',
            'ប្រវត្តិវិទ្យា',
            'ភូមិវិទ្យា',
            'ភាសាអង់គ្លេស',
        ];

        foreach ($khmerCoreSubjects as $subjectName) {
            Subject::query()->firstOrCreate(
                ['school_id' => $school->id, 'name' => $subjectName],
                SubjectFactory::new()->make([
                    'school_id' => $school->id,
                    'name' => $subjectName,
                ])->toArray()
            );
        }

        $superAdmin = $this->upsertUser([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'role' => 'super-admin',
            'school_id' => null,
        ]);

        $admin = $this->upsertUser([
            'name' => 'School Admin',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'school_id' => $school->id,
        ]);

        $teacher = $this->upsertUser([
            'name' => 'Teacher One',
            'email' => 'teacher@example.com',
            'role' => 'teacher',
            'school_id' => $school->id,
        ]);

        $teacherTwo = $this->upsertUser(
            UserFactory::new()->teacher($school->id)->make([
                'name' => 'Teacher Two',
                'email' => 'teacher2@example.com',
                'school_id' => $school->id,
            ])->toArray()
        );

        $studentUser = $this->upsertUser([
            'name' => 'Student One',
            'email' => 'student@example.com',
            'role' => 'student',
            'school_id' => $school->id,
        ]);

        $studentTwoUser = $this->upsertUser(
            UserFactory::new()->student($school->id)->make([
                'name' => 'Student Two',
                'email' => 'student2@example.com',
                'school_id' => $school->id,
            ])->toArray()
        );

        $parent = $this->upsertUser([
            'name' => 'Parent One',
            'email' => 'parent@example.com',
            'role' => 'parent',
            'school_id' => $school->id,
        ]);

        $parentTwo = $this->upsertUser(
            UserFactory::new()->parent($school->id)->make([
                'name' => 'Parent Two',
                'email' => 'parent2@example.com',
                'school_id' => $school->id,
            ])->toArray()
        );

        $student = Student::query()->updateOrCreate(
            ['user_id' => $studentUser->id],
            ['grade' => '7', 'class_id' => $classA1->id]
        );

        $studentTwo = Student::query()->updateOrCreate(
            ['user_id' => $studentTwoUser->id],
            ['grade' => '8', 'class_id' => $classA2->id]
        );

        $now = now();

        DB::table('teacher_class')->updateOrInsert(
            ['teacher_id' => $teacher->id, 'class_id' => $classA1->id, 'subject_id' => $math->id],
            ['updated_at' => $now, 'created_at' => $now]
        );
        DB::table('teacher_class')->updateOrInsert(
            ['teacher_id' => $teacher->id, 'class_id' => $classA1->id, 'subject_id' => $khmer->id],
            ['updated_at' => $now, 'created_at' => $now]
        );
        DB::table('teacher_class')->updateOrInsert(
            ['teacher_id' => $teacherTwo->id, 'class_id' => $classA2->id, 'subject_id' => $english->id],
            ['updated_at' => $now, 'created_at' => $now]
        );

        DB::table('parent_child')->updateOrInsert(
            ['parent_id' => $parent->id, 'student_id' => $student->id],
            ['updated_at' => $now, 'created_at' => $now]
        );
        DB::table('parent_child')->updateOrInsert(
            ['parent_id' => $parentTwo->id, 'student_id' => $studentTwo->id],
            ['updated_at' => $now, 'created_at' => $now]
        );

        Timetable::query()->updateOrCreate(
            ['class_id' => $classA1->id, 'subject_id' => $math->id, 'teacher_id' => $teacher->id, 'day_of_week' => 'monday', 'time_start' => '08:00:00'],
            ['time_end' => '09:00:00']
        );
        Timetable::query()->updateOrCreate(
            ['class_id' => $classA1->id, 'subject_id' => $khmer->id, 'teacher_id' => $teacher->id, 'day_of_week' => 'tuesday', 'time_start' => '08:00:00'],
            ['time_end' => '09:00:00']
        );
        Timetable::query()->updateOrCreate(
            ['class_id' => $classA2->id, 'subject_id' => $english->id, 'teacher_id' => $teacherTwo->id, 'day_of_week' => 'wednesday', 'time_start' => '09:00:00'],
            ['time_end' => '10:00:00']
        );

        $this->seedAttendance($student, $classA1);
        $this->seedAttendance($studentTwo, $classA2);

        $homework = Homework::query()->updateOrCreate(
            ['class_id' => $classA1->id, 'subject_id' => $math->id, 'title' => 'Algebra Worksheet #1'],
            [
                'question' => 'Solve exercises 1-10 from chapter 2.',
                'due_date' => now()->addDays(3)->toDateString(),
                'file_attachments' => ['https://example.com/files/algebra-worksheet-1.pdf'],
            ]
        );

        $homeworkTwo = Homework::query()->updateOrCreate(
            ['class_id' => $classA2->id, 'subject_id' => $english->id, 'title' => 'Reading Comprehension'],
            [
                'question' => 'Read the passage and answer all questions.',
                'due_date' => now()->addDays(2)->toDateString(),
                'file_attachments' => ['https://example.com/files/reading-homework.pdf'],
            ]
        );

        HomeworkStatus::query()->updateOrCreate(
            ['homework_id' => $homework->id, 'student_id' => $student->id],
            ['status' => 'Done', 'completion_date' => now()->toDateString()]
        );
        HomeworkStatus::query()->updateOrCreate(
            ['homework_id' => $homeworkTwo->id, 'student_id' => $studentTwo->id],
            ['status' => 'Not Done', 'completion_date' => null]
        );

        Score::query()->updateOrCreate(
            [
                'student_id' => $student->id,
                'subject_id' => $math->id,
                'class_id' => $classA1->id,
                'month' => now()->month,
                'quarter' => 1,
                'period' => 'Midterm',
            ],
            [
                'exam_score' => 88,
                'total_score' => 92,
                'grade' => 'A',
            ]
        );
        Score::query()->updateOrCreate(
            [
                'student_id' => $studentTwo->id,
                'subject_id' => $english->id,
                'class_id' => $classA2->id,
                'month' => now()->month,
                'quarter' => 1,
                'period' => 'Midterm',
            ],
            [
                'exam_score' => 74,
                'total_score' => 78,
                'grade' => 'C',
            ]
        );

        $demoLeaveRequest = LeaveRequest::query()->updateOrCreate(
            [
                'student_id' => $student->id,
                'subject_id' => $math->id,
                'request_type' => 'hourly',
                'start_date' => now()->toDateString(),
            ],
            [
                'subject_ids' => [$math->id],
                'end_date' => now()->toDateString(),
                'start_time' => '08:00:00',
                'end_time' => '09:00:00',
                'return_date' => now()->toDateString(),
                'total_days' => 1,
                'reason' => 'Fever and cough',
                'status' => 'pending',
                'submitted_by' => $studentUser->id,
            ]
        );

        DB::table('leave_request_recipients')->updateOrInsert(
            ['leave_request_id' => $demoLeaveRequest->id, 'user_id' => $admin->id],
            ['recipient_role' => 'admin', 'updated_at' => $now, 'created_at' => $now]
        );
        DB::table('leave_request_recipients')->updateOrInsert(
            ['leave_request_id' => $demoLeaveRequest->id, 'user_id' => $teacher->id],
            ['recipient_role' => 'teacher', 'updated_at' => $now, 'created_at' => $now]
        );

        $announcement = Announcement::query()->updateOrCreate(
            [
                'school_id' => $school->id,
                'class_id' => null,
                'title' => 'School Meeting',
            ],
            [
                'content' => 'General school meeting on Friday at 8:00 AM.',
                'date' => now()->toDateString(),
            ]
        );

        Message::query()->updateOrCreate(
            [
                'sender_id' => $teacher->id,
                'class_id' => $classA1->id,
                'receiver_id' => null,
                'content' => 'Please submit your homework before 5 PM.',
            ],
            [
                'date' => now(),
            ]
        );
        Message::query()->updateOrCreate(
            [
                'sender_id' => $teacher->id,
                'receiver_id' => $parent->id,
                'class_id' => null,
                'content' => 'Student progress is improving this week.',
            ],
            [
                'date' => now()->subHour(),
            ]
        );

        Notification::query()->updateOrCreate(
            [
                'user_id' => $studentUser->id,
                'title' => 'New announcement',
                'content' => $announcement->title,
            ],
            [
                'date' => now(),
                'read_status' => false,
            ]
        );
        Notification::query()->updateOrCreate(
            [
                'user_id' => $parent->id,
                'title' => 'Incident update',
                'content' => 'Please review latest incident report.',
            ],
            [
                'date' => now()->subMinutes(30),
                'read_status' => false,
            ]
        );

        IncidentReport::query()->updateOrCreate(
            [
                'student_id' => $student->id,
                'description' => 'Fighting in the class',
                'date' => now()->toDateString(),
            ],
            [
                'type' => 'Discipline',
                'acknowledged' => false,
                'reporter_id' => $teacher->id,
            ]
        );

        // Extra sample data generated from factories for quick local API testing.
        $this->seedFactoryDrivenBranchSchool($superAdmin, $admin);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertUser(array $attributes): User
    {
        $password = Hash::make('password123');
        $role = (string) ($attributes['role'] ?? 'student');
        $roleName = match ($role) {
            'super-admin' => 'Super Admin',
            'admin' => 'Admin',
            'teacher' => 'Teacher',
            'student' => 'Student',
            'parent' => 'Guardian',
            default => 'Student',
        };
        $roleId = Role::query()->where('role_name', $roleName)->value('role_id');
        $email = (string) ($attributes['email'] ?? '');
        $usernameBase = $email !== '' ? explode('@', $email)[0] : strtolower(str_replace(' ', '_', (string) ($attributes['name'] ?? 'user')));
        $username = preg_replace('/[^a-z0-9_]+/i', '_', $usernameBase);
        $username = trim((string) $username, '_');
        if ($username === '') {
            $username = 'user_'.substr(md5((string) ($attributes['name'] ?? random_int(1000, 9999))), 0, 6);
        }

        return User::query()->updateOrCreate(
            ['email' => $attributes['email']],
            [
                'username' => $username,
                'name' => $attributes['name'],
                'role' => $role,
                'role_id' => $roleId,
                'school_id' => $attributes['school_id'] ?? null,
                'phone' => $attributes['phone'] ?? '+85510000000',
                'address' => $attributes['address'] ?? 'Phnom Penh',
                'bio' => $attributes['bio'] ?? null,
                'image_url' => $attributes['image_url'] ?? null,
                'active' => $attributes['active'] ?? true,
                'is_active' => $attributes['active'] ?? true,
                'email_verified_at' => $attributes['email_verified_at'] ?? now(),
                'last_login' => $attributes['last_login'] ?? null,
                'password' => $password,
                'password_hash' => $password,
            ]
        );
    }

    private function seedAttendance(Student $student, SchoolClass $class): void
    {
        $subjectId = $class->subjects()->orderBy('subjects.id')->value('subjects.id');
        $records = [
            [now()->subDays(12)->toDateString(), '08:00:00', '09:00:00', 'P'],
            [now()->subDays(11)->toDateString(), '08:00:00', '09:00:00', 'A'],
            [now()->subDays(10)->toDateString(), '08:00:00', '09:00:00', 'L'],
        ];

        foreach ($records as [$date, $start, $end, $status]) {
            Attendance::query()->updateOrCreate(
                [
                    'student_id' => $student->id,
                    'class_id' => $class->id,
                    'date' => $date,
                    'time_start' => $start,
                ],
                [
                    'time_end' => $end,
                    'subject_id' => $subjectId,
                    'status' => $status,
                ]
            );
        }
    }

    private function seedFactoryDrivenBranchSchool(User $superAdmin, User $admin): void
    {
        $branch = School::query()->firstOrCreate(
            ['name' => 'Demo Branch School'],
            SchoolFactory::new()->make([
                'name' => 'Demo Branch School',
                'school_code' => 'BRANCH-001',
                'location' => 'Siem Reap',
            ])->toArray()
        );

        $branchClass = SchoolClass::query()->firstOrCreate(
            ['school_id' => $branch->id, 'name' => 'B1', 'grade_level' => 'Grade 9'],
            SchoolClassFactory::new()->make([
                'school_id' => $branch->id,
                'name' => 'B1',
                'grade_level' => 'Grade 9',
            ])->toArray()
        );

        $branchSubject = Subject::query()->firstOrCreate(
            ['school_id' => $branch->id, 'name' => 'Science'],
            SubjectFactory::new()->make([
                'school_id' => $branch->id,
                'name' => 'Science',
            ])->toArray()
        );

        $branchTeacher = $this->upsertUser(
            UserFactory::new()->teacher($branch->id)->make([
                'name' => 'Branch Teacher',
                'email' => 'branch-teacher@example.com',
                'school_id' => $branch->id,
            ])->toArray()
        );

        $branchStudentUser = $this->upsertUser(
            UserFactory::new()->student($branch->id)->make([
                'name' => 'Branch Student',
                'email' => 'branch-student@example.com',
                'school_id' => $branch->id,
            ])->toArray()
        );

        $branchStudent = Student::query()->updateOrCreate(
            ['user_id' => $branchStudentUser->id],
            ['grade' => '9', 'class_id' => $branchClass->id]
        );

        DB::table('teacher_class')->updateOrInsert(
            ['teacher_id' => $branchTeacher->id, 'class_id' => $branchClass->id, 'subject_id' => $branchSubject->id],
            ['updated_at' => now(), 'created_at' => now()]
        );

        Announcement::query()->updateOrCreate(
            [
                'school_id' => $branch->id,
                'class_id' => $branchClass->id,
                'title' => 'Welcome Branch Students',
            ],
            [
                'content' => 'First class starts next Monday.',
                'date' => now()->toDateString(),
            ]
        );

        Notification::query()->updateOrCreate(
            [
                'user_id' => $branchStudentUser->id,
                'title' => 'Welcome',
                'content' => 'Your branch account is ready.',
            ],
            [
                'date' => now(),
                'read_status' => false,
            ]
        );

        Score::query()->updateOrCreate(
            [
                'student_id' => $branchStudent->id,
                'subject_id' => $branchSubject->id,
                'class_id' => $branchClass->id,
                'month' => now()->month,
                'quarter' => 1,
                'period' => 'Diagnostic',
            ],
            [
                'exam_score' => 65,
                'total_score' => 69,
                'grade' => 'D',
            ]
        );

        // Keep explicit roles seeded and active for quick auth tests.
        $superAdmin->update(['active' => true]);
        $admin->update(['active' => true]);
    }
}
