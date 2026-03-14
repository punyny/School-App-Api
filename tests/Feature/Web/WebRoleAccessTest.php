<?php

namespace Tests\Feature\Web;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_accessible(): void
    {
        $response = $this->get('/login');

        $response->assertOk()->assertSee('School Portal Login');
    }

    public function test_locale_switch_changes_login_page_to_khmer(): void
    {
        $this->from('/login')->post('/locale', [
            'locale' => 'km',
        ])->assertRedirect('/login');

        $response = $this->withSession(['locale' => 'km'])->get('/login');

        $response->assertOk()->assertSee('ចូលប្រើ');
    }

    public function test_security_headers_are_attached_on_web_responses(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin);

        $response = $this->get('/admin/dashboard');

        $response->assertOk()->assertSee('Admin Dashboard');
    }

    public function test_teacher_is_forbidden_from_admin_dashboard(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $this->actingAs($teacher);

        $response = $this->get('/admin/dashboard');

        $response->assertForbidden();
    }

    public function test_dashboard_redirects_teacher_to_teacher_panel(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $this->actingAs($teacher);

        $response = $this->get('/dashboard');

        $response->assertRedirect('/teacher/dashboard');
    }

    public function test_dashboard_redirects_student_to_student_panel(): void
    {
        $this->seed();

        $student = User::query()->where('email', 'student@example.com')->firstOrFail();
        $this->actingAs($student);

        $response = $this->get('/dashboard');

        $response->assertRedirect('/student/dashboard');
    }

    public function test_dashboard_redirects_parent_to_parent_panel(): void
    {
        $this->seed();

        $parent = User::query()->where('email', 'parent@example.com')->firstOrFail();
        $this->actingAs($parent);

        $response = $this->get('/dashboard');

        $response->assertRedirect('/parent/dashboard');
    }

    public function test_student_can_open_student_dashboard(): void
    {
        $this->seed();

        $student = User::query()->where('email', 'student@example.com')->firstOrFail();
        $this->actingAs($student);

        $response = $this->get('/student/dashboard');

        $response->assertOk()->assertSee('Student Dashboard');
    }

    public function test_parent_can_open_parent_dashboard(): void
    {
        $this->seed();

        $parent = User::query()->where('email', 'parent@example.com')->firstOrFail();
        $this->actingAs($parent);

        $response = $this->get('/parent/dashboard');

        $response->assertOk()->assertSee('Parent Dashboard');
    }

    public function test_student_and_parent_can_view_message_and_announcement_pages(): void
    {
        $this->seed();

        $student = User::query()->where('email', 'student@example.com')->firstOrFail();
        $parent = User::query()->where('email', 'parent@example.com')->firstOrFail();

        $this->actingAs($student);
        $studentMessages = $this->get('/panel/messages');
        $studentAnnouncements = $this->get('/panel/announcements');

        $studentMessages->assertOk()->assertSee('Message Management');
        $studentAnnouncements->assertOk()->assertSee('Announcement Management');

        $this->actingAs($parent);
        $parentMessages = $this->get('/panel/messages');
        $parentAnnouncements = $this->get('/panel/announcements');

        $parentMessages->assertOk()->assertSee('Message Management');
        $parentAnnouncements->assertOk()->assertSee('Announcement Management');
    }

    public function test_message_list_is_view_only_no_edit_delete_actions(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $this->actingAs($teacher);

        $response = $this->get('/panel/messages');

        $response->assertOk()
            ->assertSee('Message Management')
            ->assertSee('View')
            ->assertDontSee('Delete this message?');
    }

    public function test_student_and_parent_can_open_leave_request_pages(): void
    {
        $this->seed();

        $student = User::query()->where('email', 'student@example.com')->firstOrFail();
        $parent = User::query()->where('email', 'parent@example.com')->firstOrFail();

        $this->actingAs($student);
        $studentIndex = $this->get('/panel/leave-requests');
        $studentCreate = $this->get('/panel/leave-requests/create');
        $studentIndex->assertOk()->assertSee('Leave Request Management');
        $studentCreate->assertOk()->assertSee('Create Leave Request');

        $this->actingAs($parent);
        $parentIndex = $this->get('/panel/leave-requests');
        $parentCreate = $this->get('/panel/leave-requests/create');
        $parentIndex->assertOk()->assertSee('Leave Request Management');
        $parentCreate->assertOk()->assertSee('Create Leave Request');
    }

    public function test_teacher_can_see_approve_and_reject_buttons_on_pending_leave_requests(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $this->actingAs($teacher);

        $response = $this->get('/panel/leave-requests');

        $response->assertOk()
            ->assertSee('Approve')
            ->assertSee('Reject');
    }

    public function test_teacher_dashboard_hides_modules_one_to_seven_block(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $this->actingAs($teacher);

        $response = $this->get('/teacher/dashboard');

        $response->assertOk()
            ->assertSee('Teacher Dashboard')
            ->assertDontSee('Teacher Dashboard Modules (1-7)');
    }

    public function test_admin_can_open_students_crud_page(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin);

        $response = $this->get('/panel/students');

        $response->assertOk()->assertSee('Student Management');
    }

    public function test_admin_user_create_form_renders_khmer_labels_when_locale_is_khmer(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin)->withSession(['locale' => 'km']);

        $response = $this->get('/panel/users/create');

        $response->assertOk()
            ->assertSee('បង្កើតអ្នកប្រើ')
            ->assertSee('ព័ត៌មានអាណាព្យាបាល')
            ->assertSee('ព័ត៌មានគ្រូ');
    }

    public function test_admin_can_create_student_with_profile_image_from_web(): void
    {
        $this->seed();
        Storage::fake('public');

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin);

        $response = $this->post('/panel/students', [
            'class_id' => '',
            'name' => 'Web Image Student',
            'student_id' => 'WEB-STU-001',
            'khmer_name' => 'សិស្សរូបភាព',
            'email' => 'web-image-student@example.com',
            'password' => 'password123',
            'grade' => '7',
            'image' => UploadedFile::fake()->image('student.png')->size(4096),
        ]);

        $response->assertRedirect('/panel/students');

        $student = Student::query()
            ->whereHas('user', fn ($query) => $query->where('email', 'web-image-student@example.com'))
            ->with('user')
            ->firstOrFail();

        $this->assertNotNull($student->user?->image_url);
        $this->assertStringStartsWith('/storage/profiles/', (string) $student->user?->image_url);
    }

    public function test_user_can_update_profile_image_from_web(): void
    {
        $this->seed();
        Storage::fake('public');

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $this->actingAs($teacher);

        $response = $this->patch('/profile', [
            'name' => 'Teacher Web Image',
            'image' => UploadedFile::fake()->image('teacher.png')->size(4096),
        ]);

        $response->assertSessionHasNoErrors();

        $teacher->refresh();
        $this->assertSame('Teacher Web Image', $teacher->name);
        $this->assertNotNull($teacher->image_url);
        $this->assertStringStartsWith('/storage/profiles/', (string) $teacher->image_url);
    }

    public function test_teacher_profile_page_shows_class_timetable_and_students_sections(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $this->actingAs($teacher);

        $response = $this->get('/profile');

        $response->assertOk()
            ->assertSee('My Classes')
            ->assertSee('My Timetable')
            ->assertSee('My Students');
    }

    public function test_student_profile_page_shows_information_and_scores_sections(): void
    {
        $this->seed();

        $student = User::query()->where('email', 'student@example.com')->firstOrFail();
        $this->actingAs($student);

        $response = $this->get('/profile');

        $response->assertOk()
            ->assertSee('My Information')
            ->assertSee('My Subjects')
            ->assertSee('Recent Scores');
    }

    public function test_parent_profile_page_shows_children_section(): void
    {
        $this->seed();

        $parent = User::query()->where('email', 'parent@example.com')->firstOrFail();
        $this->actingAs($parent);

        $response = $this->get('/profile');

        $response->assertOk()
            ->assertSee('My Children');
    }

    public function test_admin_can_create_teacher_with_profile_image_from_web(): void
    {
        $this->seed();
        Storage::fake('public');

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin);

        $response = $this->post('/panel/users', [
            'role' => 'teacher',
            'name' => 'Web Image Teacher',
            'email' => 'web-image-teacher@example.com',
            'password' => 'password123',
            'phone' => '012345678',
            'image' => UploadedFile::fake()->image('teacher.png')->size(4096),
            'active' => '1',
        ]);

        $response->assertRedirect('/panel/users');

        $teacher = User::query()->where('email', 'web-image-teacher@example.com')->firstOrFail();

        $this->assertSame('teacher', $teacher->role);
        $this->assertNotNull($teacher->image_url);
        $this->assertStringStartsWith('/storage/profiles/', (string) $teacher->image_url);
    }

    public function test_admin_can_create_parent_with_linked_child_from_web(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $student = Student::query()->whereHas('user', fn ($query) => $query->where('email', 'student@example.com'))->firstOrFail();
        $this->actingAs($admin);

        $response = $this->post('/panel/users', [
            'role' => 'parent',
            'name' => 'Web Parent',
            'email' => 'web.parent@example.com',
            'password' => 'password123',
            'phone' => '098887766',
            'child_ids' => [$student->id],
            'active' => '1',
        ]);

        $response->assertRedirect('/panel/users');

        $parent = User::query()->where('email', 'web.parent@example.com')->firstOrFail();
        $this->assertSame('parent', $parent->role);
        $this->assertDatabaseHas('parent_child', [
            'parent_id' => $parent->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_admin_can_update_student_with_profile_image_from_web(): void
    {
        $this->seed();
        Storage::fake('public');

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $student = Student::query()->whereHas('user', fn ($query) => $query->where('email', 'student@example.com'))->firstOrFail();
        $this->actingAs($admin);

        $response = $this->put('/panel/students/'.$student->id, [
            'class_id' => (string) ($student->class_id ?? ''),
            'name' => 'Updated Student Image',
            'student_id' => $student->student_code ?? 'STU-UPDATED',
            'khmer_name' => 'សិស្សកែរូបភាព',
            'email' => 'student@example.com',
            'password' => '',
            'grade' => $student->grade ?? '7',
            'image' => UploadedFile::fake()->image('updated-student.png')->size(4096),
        ]);

        $response->assertSessionHasNoErrors();

        $student->refresh();
        $student->load('user');
        $this->assertSame('Updated Student Image', $student->user?->name);
        $this->assertNotNull($student->user?->image_url);
        $this->assertStringStartsWith('/storage/profiles/', (string) $student->user?->image_url);
    }

    public function test_admin_student_create_form_keeps_class_assignment_for_class_module(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin);

        $response = $this->get('/panel/students/create');

        $response->assertOk()
            ->assertSee('Class assignment is done from Create/Edit Class page.')
            ->assertDontSee('Assign later');
    }

    public function test_admin_user_create_form_does_not_show_super_admin_role_option(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin);

        $response = $this->get('/panel/users/create');

        $response->assertOk()
            ->assertDontSee('value="super-admin"', false)
            ->assertDontSee('value="admin"', false);
    }

    public function test_super_admin_user_create_form_shows_admin_quick_fields(): void
    {
        $this->seed();

        $superAdmin = User::query()->where('email', 'superadmin@example.com')->firstOrFail();
        $this->actingAs($superAdmin);

        $response = $this->get('/panel/users/create');

        $response->assertOk()
            ->assertSee('Quick Create Admin')
            ->assertSee('name="admin_name"', false)
            ->assertDontSee('name="admin_id"', false)
            ->assertSee('name="school_id"', false)
            ->assertSee('id="school_id_select"', false)
            ->assertDontSee('name="school_name"', false)
            ->assertDontSee('name="school_code"', false)
            ->assertDontSee('name="school_camp"', false);
    }

    public function test_teacher_can_open_attendance_crud_page(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $this->actingAs($teacher);

        $response = $this->get('/panel/attendance');

        $response->assertOk()->assertSee('Attendance Management');
    }

    public function test_teacher_can_create_homework_from_web_crud(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $assignment = DB::table('teacher_class')->where('teacher_id', $teacher->id)->first();

        $this->assertNotNull($assignment);
        $this->actingAs($teacher);

        $response = $this->post('/panel/homeworks', [
            'class_id' => $assignment->class_id,
            'subject_id' => $assignment->subject_id,
            'title' => 'Web Homework',
            'question' => 'Complete exercise set A.',
            'due_date' => now()->addDays(2)->toDateString(),
            'file_attachments' => 'https://example.com/file1.pdf',
        ]);

        $response->assertRedirect('/panel/homeworks');

        $this->assertDatabaseHas('homeworks', [
            'title' => 'Web Homework',
            'class_id' => $assignment->class_id,
            'subject_id' => $assignment->subject_id,
        ]);
    }

    public function test_homework_create_redirect_preserves_browser_host_and_port(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $assignment = DB::table('teacher_class')->where('teacher_id', $teacher->id)->first();

        $this->assertNotNull($assignment);

        $this->withServerVariables([
            'HTTP_HOST' => '127.0.0.1:8000',
            'SERVER_NAME' => '127.0.0.1',
            'SERVER_PORT' => '8000',
            'REQUEST_SCHEME' => 'http',
            'HTTPS' => 'off',
        ])->actingAs($teacher);

        $response = $this->post('/panel/homeworks', [
            'class_id' => $assignment->class_id,
            'subject_id' => $assignment->subject_id,
            'title' => 'Web Homework Host Test',
            'question' => 'Host redirect test.',
            'due_date' => now()->addDays(2)->toDateString(),
            'file_attachments' => '',
        ]);

        $response->assertStatus(302);
        $this->assertSame('/panel/homeworks', $response->headers->get('Location'));
    }

    public function test_teacher_can_create_score_from_web_crud(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $assignment = DB::table('teacher_class')->where('teacher_id', $teacher->id)->first();
        $student = Student::query()->where('class_id', $assignment->class_id)->firstOrFail();

        $this->actingAs($teacher);

        $response = $this->post('/panel/scores', [
            'student_id' => $student->id,
            'class_id' => $assignment->class_id,
            'subject_id' => $assignment->subject_id,
            'exam_score' => 80,
            'total_score' => 85,
            'month' => 3,
            'quarter' => 1,
            'period' => 'Web Test',
        ]);

        $response->assertRedirect('/panel/scores');

        $this->assertDatabaseHas('scores', [
            'student_id' => $student->id,
            'class_id' => $assignment->class_id,
            'subject_id' => $assignment->subject_id,
            'period' => 'Web Test',
        ]);
    }

    public function test_admin_bulk_score_create_page_shows_students_without_teacher_assignments(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $student = Student::query()
            ->whereNotNull('class_id')
            ->whereHas('user', fn ($query) => $query->where('school_id', $admin->school_id))
            ->with('user')
            ->firstOrFail();

        DB::table('teacher_class')->where('class_id', $student->class_id)->delete();

        $this->actingAs($admin);

        $response = $this->get('/panel/scores/create?class_id='.$student->class_id.'&assessment_type=yearly&academic_year=2025-2026');

        $response->assertOk()
            ->assertSee('តារាងបញ្ចូលពិន្ទុ')
            ->assertSee((string) $student->user?->name)
            ->assertSee('រក្សាទុកពិន្ទុទាំងអស់');
    }

    public function test_admin_cannot_open_homework_crud_page_but_can_open_score_page(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin);

        $homeworkPage = $this->get('/panel/homeworks');
        $scorePage = $this->get('/panel/scores');

        $homeworkPage->assertForbidden();
        $scorePage->assertOk()->assertSee('Score Management');
    }

    public function test_super_admin_can_open_school_and_user_crud_pages(): void
    {
        $this->seed();

        $superAdmin = User::query()->where('email', 'superadmin@example.com')->firstOrFail();
        $this->actingAs($superAdmin);

        $schoolsPage = $this->get('/panel/schools');
        $usersPage = $this->get('/panel/users');

        $schoolsPage->assertOk()->assertSee('School Management');
        $usersPage->assertOk()->assertSee('User Management');
    }

    public function test_super_admin_can_open_school_scope_management_page(): void
    {
        $this->seed();

        $superAdmin = User::query()->where('email', 'superadmin@example.com')->firstOrFail();
        $admin = User::query()->where('role', 'admin')->whereNotNull('school_id')->firstOrFail();
        $this->actingAs($superAdmin);

        $response = $this->get('/super-admin/schools/'.$admin->school_id.'/manage');

        $response->assertOk()
            ->assertSee('Manage School:')
            ->assertSee('Classes In This School')
            ->assertSee('School Modules');
    }

    public function test_admin_can_open_new_management_crud_pages(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin);

        $classesPage = $this->get('/panel/classes');
        $subjectsPage = $this->get('/panel/subjects');
        $timetablePage = $this->get('/panel/timetables');
        $leavePage = $this->get('/panel/leave-requests');
        $messagesPage = $this->get('/panel/messages');
        $notificationsPage = $this->get('/panel/notifications');
        $incidentPage = $this->get('/panel/incident-reports');
        $auditLogPage = $this->get('/panel/audit-logs');
        $mediaPage = $this->get('/panel/media');

        $classesPage->assertOk()->assertSee('Class Management');
        $subjectsPage->assertOk()->assertSee('Subject Management');
        $timetablePage->assertOk()->assertSee('Timetable Management');
        $leavePage->assertOk()->assertSee('Leave Request Management');
        $messagesPage->assertOk()->assertSee('Message Management');
        $notificationsPage->assertOk()->assertSee('Notification Management');
        $incidentPage->assertOk()->assertSee('Incident Report Management');
        $auditLogPage->assertOk()->assertSee('Audit Log');
        $mediaPage->assertOk()->assertSee('Media Library');
    }

    public function test_admin_can_open_class_detail_assignment_page(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $classId = DB::table('classes')->where('school_id', $admin->school_id)->value('id');

        $this->assertNotNull($classId);
        $this->actingAs($admin);

        $response = $this->get('/panel/classes/'.$classId);

        $response->assertOk()
            ->assertSee('Assign Students To This Class')
            ->assertSee('Class Detail:');
    }

    public function test_admin_can_assign_student_from_class_detail_page(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $classId = (int) DB::table('classes')->where('school_id', $admin->school_id)->value('id');
        $existingStudentIds = Student::query()->where('class_id', $classId)->pluck('id')->all();

        $studentUser = User::factory()->student((int) $admin->school_id)->create();
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'class_id' => null,
            'grade' => '7',
        ]);

        $this->actingAs($admin);

        $response = $this->post('/panel/classes/'.$classId.'/students', [
            'student_ids' => array_merge($existingStudentIds, [$student->id]),
        ]);

        $response->assertRedirect('/panel/classes/'.$classId);

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'class_id' => $classId,
        ]);
    }

    public function test_teacher_can_open_new_teacher_crud_pages(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $this->actingAs($teacher);

        $timetablePage = $this->get('/panel/timetables');
        $leavePage = $this->get('/panel/leave-requests');
        $messagesPage = $this->get('/panel/messages');
        $notificationsPage = $this->get('/panel/notifications');
        $incidentPage = $this->get('/panel/incident-reports');
        $mediaPage = $this->get('/panel/media');

        $timetablePage->assertOk()->assertSee('Timetable Management');
        $leavePage->assertOk()->assertSee('Leave Request Management');
        $messagesPage->assertOk()->assertSee('Message Management');
        $notificationsPage->assertOk()->assertSee('Notification Management');
        $incidentPage->assertOk()->assertSee('Incident Report Management');
        $mediaPage->assertOk()->assertSee('Media Library');
    }

    public function test_teacher_cannot_open_audit_logs_page(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $this->actingAs($teacher);

        $response = $this->get('/panel/audit-logs');

        $response->assertForbidden();
    }

    public function test_admin_can_queue_notification_broadcast_from_web_page(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $classId = DB::table('classes')->where('school_id', $admin->school_id)->value('id');
        $this->actingAs($admin);

        $response = $this->post('/panel/notifications/broadcast', [
            'audience' => 'class',
            'class_id' => $classId,
            'title' => 'Web Broadcast',
            'content' => 'Testing class notification.',
        ]);

        $response->assertRedirect('/panel/notifications');
        $response->assertSessionHas('success');
    }
}
