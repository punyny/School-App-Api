<?php

namespace Tests\Feature\Api;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_students_only_in_own_school(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $token = $admin->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/students');

        $response->assertOk();
        $emails = collect($response->json('data'))->pluck('user.email')->all();

        $this->assertContains('student@example.com', $emails);
        $this->assertContains('student2@example.com', $emails);
        $this->assertNotContains('branch-student@example.com', $emails);
    }

    public function test_super_admin_can_filter_students_by_school(): void
    {
        $this->seed();

        $superAdmin = User::query()->where('email', 'superadmin@example.com')->firstOrFail();
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $token = $superAdmin->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/students?school_id='.$admin->school_id);

        $response->assertOk();
        $emails = collect($response->json('data'))->pluck('user.email')->all();

        $this->assertContains('student@example.com', $emails);
        $this->assertContains('student2@example.com', $emails);
        $this->assertNotContains('branch-student@example.com', $emails);
    }

    public function test_admin_can_create_student_with_parent_link(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $parent = User::query()->where('email', 'parent@example.com')->firstOrFail();
        $token = $admin->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/students', [
            'name' => 'New Student',
            'email' => 'new.student@example.com',
            'password' => 'password123',
            'grade' => '7',
            'parent_ids' => [$parent->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'new.student@example.com')
            ->assertJsonPath('data.user.role', 'student')
            ->assertJsonPath('data.class_id', null);

        $student = Student::query()->whereHas('user', fn ($query) => $query->where('email', 'new.student@example.com'))->firstOrFail();

        $this->assertDatabaseHas('parent_child', [
            'parent_id' => $parent->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_admin_can_register_student_before_assigning_class(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $token = $admin->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/students', [
            'name' => 'Unassigned Student',
            'student_id' => 'STU-NO-CLASS-01',
            'khmer_name' => 'សិស្ស មិនទាន់ចាត់ថ្នាក់',
            'email' => 'unassigned.student@example.com',
            'password' => 'password123',
            'grade' => '7',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'unassigned.student@example.com')
            ->assertJsonPath('data.class_id', null);

        $this->assertDatabaseHas('students', [
            'student_code' => 'STU-NO-CLASS-01',
            'class_id' => null,
        ]);
    }

    public function test_admin_can_create_student_without_manual_student_id(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $token = $admin->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/students', [
            'name' => 'Auto ID Student',
            'email' => 'auto.id.student@example.com',
            'grade' => '7',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'auto.id.student@example.com')
            ->assertJsonPath('data.class_id', null);

        $studentCode = (string) ($response->json('data.student_code') ?? '');
        $userCode = (string) ($response->json('data.user.user_code') ?? '');

        $this->assertNotSame('', $studentCode);
        $this->assertSame($studentCode, $userCode);
    }

    public function test_admin_user_create_student_ignores_class_id_and_starts_unassigned(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $otherSchoolClass = SchoolClass::query()->where('school_id', '!=', $admin->school_id)->firstOrFail();
        $token = $admin->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/users', [
            'role' => 'student',
            'class_id' => $otherSchoolClass->id,
            'name' => 'User API Student',
            'email' => 'user.api.student@example.com',
            'password' => 'password123',
            'grade' => '7',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.role', 'student')
            ->assertJsonPath('data.student_profile.class_id', null);
    }

    public function test_admin_can_create_student_with_profile_image_upload(): void
    {
        $this->seed();
        Storage::fake('public');

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $class = SchoolClass::query()->where('school_id', $admin->school_id)->firstOrFail();
        $token = $admin->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->post('/api/students', [
            'class_id' => $class->id,
            'name' => 'Image Student',
            'email' => 'image.student@example.com',
            'password' => 'password123',
            'grade' => '7',
            'image' => UploadedFile::fake()->image('student.png'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'image.student@example.com');

        $student = Student::query()->whereHas('user', fn ($query) => $query->where('email', 'image.student@example.com'))->firstOrFail();
        $student->load('user');
        $this->assertNotNull($student->user?->image_url);
        $this->assertStringStartsWith('/storage/profiles/', (string) $student->user?->image_url);
        $this->assertDatabaseHas('media', [
            'mediable_type' => User::class,
            'mediable_id' => $student->user_id,
            'category' => 'profile',
            'url' => $student->user?->image_url,
            'is_primary' => true,
        ]);

        $storedPath = ltrim(str_replace('/storage/', '', (string) $student->user?->image_url), '/');
        Storage::disk('public')->assertExists($storedPath);
    }

    public function test_admin_can_update_and_delete_student(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $parent = User::query()->where('email', 'parent@example.com')->firstOrFail();
        $student = Student::query()->whereHas('user', fn ($query) => $query->where('email', 'student@example.com'))->firstOrFail();
        $class = SchoolClass::query()->where('school_id', $admin->school_id)->whereKeyNot($student->class_id)->first()
            ?? SchoolClass::query()->where('school_id', $admin->school_id)->firstOrFail();
        $token = $admin->createToken('phpunit')->plainTextToken;

        $update = $this->withToken($token)->putJson('/api/students/'.$student->id, [
            'class_id' => $class->id,
            'name' => 'Updated Student Name',
            'email' => 'student.updated@example.com',
            'grade' => '8',
            'parent_ids' => [$parent->id],
        ]);

        $update->assertOk()
            ->assertJsonPath('data.user.name', 'Updated Student Name')
            ->assertJsonPath('data.user.email', 'student.updated@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $student->user_id,
            'email' => 'student.updated@example.com',
        ]);

        $delete = $this->withToken($token)->deleteJson('/api/students/'.$student->id);
        $delete->assertOk();

        $this->assertSoftDeleted('students', ['id' => $student->id]);
        $this->assertSoftDeleted('users', ['id' => $student->user_id]);
    }

    public function test_class_id_is_ignored_on_student_create_and_student_starts_unassigned(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $otherSchoolClass = SchoolClass::query()->where('school_id', '!=', $admin->school_id)->firstOrFail();
        $token = $admin->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/students', [
            'class_id' => $otherSchoolClass->id,
            'name' => 'Invalid Student',
            'email' => 'invalid.student@example.com',
            'password' => 'password123',
            'grade' => '9',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.class_id', null);
    }

    public function test_admin_can_import_student_csv_with_grade_and_class_label(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $token = $admin->createToken('phpunit')->plainTextToken;

        $csv = implode("\n", [
            'name,khmer_name,student_id,class,email,password,grade',
            'CSV Label Student,សិស្ស CSV,STU-LABEL-01,Grade 7 A,csv.label.student@example.com,password123,7',
        ]);

        $response = $this->withToken($token)->withHeader('Accept', 'application/json')->post('/api/students/import/csv', [
            'file' => UploadedFile::fake()->createWithContent('students-label.csv', $csv),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.created', 1);

        $student = Student::query()->where('student_code', 'STU-LABEL-01')->firstOrFail();
        $class = SchoolClass::query()->findOrFail($student->class_id);

        $this->assertSame('A1', $class->name);
        $this->assertSame('Grade 7', $class->grade_level);
    }

    public function test_admin_can_import_student_csv_without_password_column(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $token = $admin->createToken('phpunit')->plainTextToken;

        $csv = implode("\n", [
            'name,khmer_name,student_id,class,email,grade',
            'CSV No Password Student,សិស្សគ្មានពាក្យសម្ងាត់,STU-NO-PASS-01,Grade 7 A,csv.no.password.student@example.com,7',
        ]);

        $response = $this->withToken($token)->withHeader('Accept', 'application/json')->post('/api/students/import/csv', [
            'file' => UploadedFile::fake()->createWithContent('students-no-password.csv', $csv),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.created', 1);

        $student = Student::query()->where('student_code', 'STU-NO-PASS-01')->with('user')->firstOrFail();

        $this->assertSame('csv.no.password.student@example.com', $student->user?->email);
        $this->assertNotEmpty((string) $student->user?->password);
    }

    public function test_admin_can_create_guardian_role_using_guardian_alias(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $token = $admin->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/users', [
            'role' => 'guardian',
            'name' => 'Guardian Alias User',
            'email' => 'guardian.alias@example.com',
            'password' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.role', 'parent');

        $this->assertDatabaseHas('users', [
            'email' => 'guardian.alias@example.com',
            'role' => 'parent',
            'school_id' => $admin->school_id,
        ]);
    }

    public function test_admin_can_create_parent_with_linked_children(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $student = Student::query()->whereHas('user', fn ($query) => $query->where('email', 'student@example.com'))->firstOrFail();
        $token = $admin->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/users', [
            'role' => 'parent',
            'name' => 'Linked Parent',
            'email' => 'linked.parent@example.com',
            'password' => 'password123',
            'phone' => '099001122',
            'child_ids' => [$student->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.role', 'parent')
            ->assertJsonPath('data.children.0.id', $student->id);

        $parent = User::query()->where('email', 'linked.parent@example.com')->firstOrFail();

        $this->assertDatabaseHas('parent_child', [
            'parent_id' => $parent->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_admin_cannot_create_super_admin_user(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $token = $admin->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/users', [
            'role' => 'super-admin',
            'name' => 'Invalid Super Admin',
            'email' => 'invalid.superadmin@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('role');

        $this->assertDatabaseMissing('users', [
            'email' => 'invalid.superadmin@example.com',
        ]);
    }

    public function test_super_admin_can_create_admin_with_minimal_alias_fields(): void
    {
        $this->seed();

        $superAdmin = User::query()->where('email', 'superadmin@example.com')->firstOrFail();
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $token = $superAdmin->createToken('phpunit')->plainTextToken;
        $schoolName = (string) optional($admin->school)->name;

        $response = $this->withToken($token)->postJson('/api/users', [
            'school_name' => $schoolName,
            'school_id' => (int) $admin->school_id,
            'admin_name' => 'Second School Admin',
            'role' => 'admin',
            'phone' => '012345678',
            'email' => 'second.admin@example.com',
            'password' => 'password123',
            'admin_id' => 'ADM-9001',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.name', 'Second School Admin')
            ->assertJsonPath('data.user_code', 'ADM-9001');

        $this->assertDatabaseHas('users', [
            'email' => 'second.admin@example.com',
            'role' => 'admin',
            'phone' => '012345678',
            'user_code' => 'ADM-9001',
            'school_id' => (int) $admin->school_id,
        ]);
    }

    public function test_super_admin_can_create_admin_without_manual_admin_id(): void
    {
        $this->seed();

        $superAdmin = User::query()->where('email', 'superadmin@example.com')->firstOrFail();
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $token = $superAdmin->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/users', [
            'school_id' => (int) $admin->school_id,
            'admin_name' => 'Auto Code Admin',
            'role' => 'admin',
            'phone' => '012000111',
            'email' => 'auto.code.admin@example.com',
            'password' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.name', 'Auto Code Admin');

        $generatedCode = (string) ($response->json('data.user_code') ?? '');
        $this->assertNotSame('', $generatedCode);
        $this->assertStringStartsWith('ADM-', $generatedCode);
    }

    public function test_super_admin_can_create_admin_and_new_school_using_name_and_camp(): void
    {
        $this->seed();

        $superAdmin = User::query()->where('email', 'superadmin@example.com')->firstOrFail();
        $token = $superAdmin->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/users', [
            'role' => 'admin',
            'admin_name' => 'New Campus Admin',
            'email' => 'new.campus.admin@example.com',
            'password' => 'password123',
            'phone' => '011223344',
            'admin_id' => 'ADM-NEW-CAMPUS',
            'school_name' => 'New Campus School',
            'school_camp' => 'Siem Reap Campus',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.name', 'New Campus Admin');

        $this->assertDatabaseHas('schools', [
            'name' => 'New Campus School',
            'location' => 'Siem Reap Campus',
        ]);

        $schoolId = (int) \App\Models\School::query()
            ->where('name', 'New Campus School')
            ->value('id');

        $this->assertTrue($schoolId > 0);

        $this->assertDatabaseHas('users', [
            'email' => 'new.campus.admin@example.com',
            'role' => 'admin',
            'school_id' => $schoolId,
            'user_code' => 'ADM-NEW-CAMPUS',
        ]);
    }

    public function test_super_admin_can_create_admin_using_existing_school_code(): void
    {
        $this->seed();

        $superAdmin = User::query()->where('email', 'superadmin@example.com')->firstOrFail();
        $token = $superAdmin->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/users', [
            'role' => 'admin',
            'admin_name' => 'Code Based Admin',
            'email' => 'code.admin@example.com',
            'password' => 'password123',
            'phone' => '015556677',
            'admin_id' => 'ADM-CODE-01',
            'school_code' => 'DEMO-001',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.school.school_code', 'DEMO-001');

        $this->assertDatabaseHas('users', [
            'email' => 'code.admin@example.com',
            'role' => 'admin',
            'user_code' => 'ADM-CODE-01',
        ]);
    }

    public function test_super_admin_can_create_admin_when_school_code_is_sent_in_school_id_field(): void
    {
        $this->seed();

        $superAdmin = User::query()->where('email', 'superadmin@example.com')->firstOrFail();
        $token = $superAdmin->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/users', [
            'role' => 'admin',
            'admin_name' => 'Alias School Field Admin',
            'email' => 'alias.school.field.admin@example.com',
            'password' => 'password123',
            'phone' => '016667788',
            'admin_id' => 'ADM-ALIAS-01',
            'school_id' => 'DEMO-001',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.school.school_code', 'DEMO-001');
    }

    public function test_super_admin_school_id_takes_priority_over_other_school_fields_when_creating_admin(): void
    {
        $this->seed();

        $superAdmin = User::query()->where('email', 'superadmin@example.com')->firstOrFail();
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $token = $superAdmin->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/users', [
            'role' => 'admin',
            'admin_name' => 'Priority School Admin',
            'email' => 'priority.school.admin@example.com',
            'password' => 'password123',
            'phone' => '017778899',
            'admin_id' => 'ADM-PRIORITY-01',
            'school_id' => (int) $admin->school_id,
            'school_code' => 'ANY-NON-MATCHING-CODE',
            'school_name' => 'Any Non Matching Name',
            'school_camp' => 'Any Non Matching Campus',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.school_id', (int) $admin->school_id);
    }

    public function test_admin_cannot_assign_admin_role_to_new_user(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $token = $admin->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/users', [
            'role' => 'admin',
            'name' => 'Unauthorized Admin',
            'email' => 'unauthorized.admin@example.com',
            'password' => 'password123',
            'phone' => '010000001',
            'admin_id' => 'ADM-FAIL-01',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('role');

        $this->assertDatabaseMissing('users', [
            'email' => 'unauthorized.admin@example.com',
        ]);
    }
}
