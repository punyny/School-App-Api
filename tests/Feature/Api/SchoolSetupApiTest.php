<?php

namespace Tests\Feature\Api;

use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SchoolSetupApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_manage_school_class_subject_user_and_timetable(): void
    {
        $this->seed();

        $superAdmin = User::query()->where('email', 'superadmin@example.com')->firstOrFail();
        $token = $superAdmin->createToken('phpunit')->plainTextToken;

        $school = $this->withToken($token)->postJson('/api/schools', [
            'name' => 'API Integration School',
            'school_code' => 'API-001',
            'location' => 'Battambang',
            'config_details' => ['timezone' => 'Asia/Phnom_Penh'],
        ])->assertCreated()
            ->assertJsonPath('data.school_code', 'API-001');

        $schoolId = (int) $school->json('data.id');

        $class = $this->withToken($token)->postJson('/api/classes', [
            'school_id' => $schoolId,
            'name' => 'C1',
            'grade_level' => 'Grade 10',
            'room' => 'A-101',
        ])->assertCreated()
            ->assertJsonPath('data.room', 'A-101');

        $subject = $this->withToken($token)->postJson('/api/subjects', [
            'school_id' => $schoolId,
            'name' => 'Computer Science',
        ])->assertCreated();

        $teacher = $this->withToken($token)->postJson('/api/users', [
            'school_id' => $schoolId,
            'role' => 'teacher',
            'name' => 'API Teacher',
            'email' => 'api.teacher@example.com',
            'password' => 'password123',
        ])->assertCreated();

        $classId = (int) $class->json('data.id');
        $subjectId = (int) $subject->json('data.id');
        $teacherId = (int) $teacher->json('data.id');

        DB::table('teacher_class')->insert([
            'teacher_id' => $teacherId,
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withToken($token)->postJson('/api/timetables', [
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'teacher_id' => $teacherId,
            'day_of_week' => 'monday',
            'time_start' => '08:00',
            'time_end' => '09:00',
        ])->assertCreated();
    }

    public function test_admin_cannot_manage_schools_but_can_manage_users_classes_and_subjects_in_own_school(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $token = $admin->createToken('phpunit')->plainTextToken;

        $this->withToken($token)->postJson('/api/schools', [
            'name' => 'Not Allowed School',
        ])->assertForbidden();

        $this->withToken($token)->postJson('/api/users', [
            'role' => 'teacher',
            'name' => 'Admin Created Teacher',
            'email' => 'admin.created.teacher@example.com',
            'password' => 'password123',
        ])->assertCreated()
            ->assertJsonPath('data.school_id', $admin->school_id);

        $class = $this->withToken($token)->postJson('/api/classes', [
            'name' => 'ADMIN-CLASS',
            'grade_level' => 'Grade 7',
        ])->assertCreated();

        $this->assertSame((int) $admin->school_id, (int) $class->json('data.school_id'));

        $subject = $this->withToken($token)->postJson('/api/subjects', [
            'name' => 'ADMIN-SUBJECT',
        ])->assertCreated();

        $this->assertSame((int) $admin->school_id, (int) $subject->json('data.school_id'));
    }

    public function test_admin_cannot_create_user_without_password(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $token = $admin->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/users', [
            'role' => 'teacher',
            'name' => 'No Password Teacher',
            'email' => 'no.password.teacher@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_admin_can_import_user_csv_without_password_column(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $token = $admin->createToken('phpunit')->plainTextToken;

        $csv = implode("\n", [
            'role,name,user_code,email',
            'teacher,CSV Imported Teacher,TCH-CSV-01,csv.import.teacher@example.com',
        ]);

        $response = $this->withToken($token)
            ->withHeader('Accept', 'application/json')
            ->post('/api/users/import/csv', [
                'file' => UploadedFile::fake()->createWithContent('users.csv', $csv),
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.created', 1);

        $teacher = User::query()->where('email', 'csv.import.teacher@example.com')->firstOrFail();

        $this->assertSame('teacher', $teacher->role);
        $this->assertNotEmpty((string) $teacher->password);
    }

    public function test_admin_create_auto_generates_username_around_soft_deleted_collision(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $token = $admin->createToken('phpunit')->plainTextToken;

        $existing = User::factory()->teacher((int) $admin->school_id)->create([
            'email' => 'matt.rorny2023@old-example.test',
            'username' => null,
            'name' => 'Old Matt',
        ]);
        $existing->delete();

        $response = $this->withToken($token)->postJson('/api/users', [
            'role' => 'teacher',
            'name' => 'New Matt',
            'email' => 'matt.rorny2023@edu.diu.kh',
            'password' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.username', 'matt_rorny2023_1');

        $this->assertSoftDeleted('users', ['id' => $existing->id]);
        $this->assertDatabaseHas('users', [
            'email' => 'matt.rorny2023@edu.diu.kh',
            'username' => 'matt_rorny2023_1',
        ]);
    }

    public function test_admin_can_recreate_deleted_teacher_with_same_email(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $token = $admin->createToken('phpunit')->plainTextToken;

        $deletedTeacher = User::factory()->teacher((int) $admin->school_id)->create([
            'name' => 'Deleted Teacher',
            'email' => 'deleted.teacher@example.com',
            'user_code' => 'TCH-DELETE-0001',
        ]);
        $deletedTeacherId = $deletedTeacher->id;
        $deletedTeacher->delete();

        $response = $this->withToken($token)->postJson('/api/users', [
            'role' => 'teacher',
            'name' => 'Recreated Teacher',
            'email' => 'deleted.teacher@example.com',
            'password' => 'password123',
            'phone' => '010202020',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.id', $deletedTeacherId)
            ->assertJsonPath('data.name', 'Recreated Teacher');

        $this->assertDatabaseHas('users', [
            'id' => $deletedTeacherId,
            'email' => 'deleted.teacher@example.com',
            'name' => 'Recreated Teacher',
            'deleted_at' => null,
        ]);
    }

    public function test_teacher_can_create_timetable_for_own_assignment_only(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $assignment = DB::table('teacher_class')->where('teacher_id', $teacher->id)->first();

        $this->assertNotNull($assignment);
        $token = $teacher->createToken('phpunit')->plainTextToken;

        $this->withToken($token)->postJson('/api/timetables', [
            'class_id' => $assignment->class_id,
            'subject_id' => $assignment->subject_id,
            'day_of_week' => 'tuesday',
            'time_start' => '10:00',
            'time_end' => '11:00',
        ])->assertCreated()
            ->assertJsonPath('data.teacher_id', $teacher->id);

        $unassignedClass = SchoolClass::query()
            ->where('school_id', $teacher->school_id)
            ->whereKeyNot($assignment->class_id)
            ->firstOrFail();

        $this->withToken($token)->postJson('/api/timetables', [
            'class_id' => $unassignedClass->id,
            'subject_id' => $assignment->subject_id,
            'day_of_week' => 'wednesday',
            'time_start' => '10:00',
            'time_end' => '11:00',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('class_id');
    }

    public function test_teacher_subject_list_contains_only_assigned_subjects(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $token = $teacher->createToken('phpunit')->plainTextToken;
        $assignedSubjectIds = DB::table('teacher_class')
            ->where('teacher_id', $teacher->id)
            ->pluck('subject_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $this->assertNotEmpty($assignedSubjectIds);

        $response = $this->withToken($token)->getJson('/api/subjects');
        $response->assertOk();

        $responseSubjectIds = collect($response->json('data', []))
            ->map(fn ($row): int => (int) ($row['id'] ?? 0))
            ->filter(fn (int $id): bool => $id > 0)
            ->all();

        $this->assertNotEmpty($responseSubjectIds);
        $this->assertEqualsCanonicalizing($assignedSubjectIds, $responseSubjectIds);
    }

    public function test_admin_can_install_khmer_core_subjects_with_extra_subjects(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $token = $admin->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/subjects/install-khmer-core', [
            'extra_subjects' => ['ភាសាបារាំង', 'គណិតវិទ្យា'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.school_id', $admin->school_id);

        $this->assertDatabaseHas('subjects', [
            'school_id' => $admin->school_id,
            'name' => 'កម្មវិធីចំណេះដឹងទូទៅខ្មែរ',
        ]);
        $this->assertDatabaseHas('subjects', [
            'school_id' => $admin->school_id,
            'name' => 'ភាសាបារាំង',
        ]);

        $repeat = $this->withToken($token)->postJson('/api/subjects/install-khmer-core');
        $repeat->assertOk()
            ->assertJsonPath('data.created', 0);
    }

    public function test_super_admin_must_provide_school_id_for_khmer_core_install(): void
    {
        $this->seed();

        $superAdmin = User::query()->where('email', 'superadmin@example.com')->firstOrFail();
        $token = $superAdmin->createToken('phpunit')->plainTextToken;

        $this->withToken($token)->postJson('/api/subjects/install-khmer-core')
            ->assertStatus(422)
            ->assertJsonValidationErrors('school_id');

        $targetSchoolId = (int) User::query()->where('email', 'admin@example.com')->value('school_id');
        $this->assertTrue($targetSchoolId > 0);

        $this->withToken($token)->postJson('/api/subjects/install-khmer-core', [
            'school_id' => $targetSchoolId,
        ])->assertOk()
            ->assertJsonPath('data.school_id', $targetSchoolId);
    }
}
