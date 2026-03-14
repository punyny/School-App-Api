<?php

namespace Tests\Feature\Api;

use App\Models\Homework;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomeworkApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_create_homework(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $assignment = DB::table('teacher_class')->where('teacher_id', $teacher->id)->first();
        $this->assertNotNull($assignment);
        $token = $teacher->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/homeworks', [
            'class_id' => $assignment->class_id,
            'subject_id' => $assignment->subject_id,
            'title' => 'Chapter 1 Exercise',
            'question' => 'Solve questions 1-10',
            'due_date' => '2026-03-20',
            'due_time' => '17:00',
            'file_attachments' => ['https://example.com/hw1.pdf'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Chapter 1 Exercise');
    }

    public function test_admin_cannot_create_homework(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $class = SchoolClass::query()->where('school_id', $admin->school_id)->firstOrFail();
        $subject = Subject::query()->where('school_id', $admin->school_id)->firstOrFail();
        $token = $admin->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/homeworks', [
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'title' => 'Admin Homework',
        ]);

        $response->assertForbidden();
    }

    public function test_student_can_update_homework_status(): void
    {
        $this->seed();

        $studentUser = User::query()->where('email', 'student@example.com')->firstOrFail();
        $class = SchoolClass::query()->firstOrFail();
        $subject = Subject::query()->firstOrFail();
        $student = Student::query()->firstOrFail();
        $token = $studentUser->createToken('phpunit')->plainTextToken;

        $homework = Homework::query()->create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'title' => 'Homework Status Test',
            'question' => 'Q1-Q3',
            'due_date' => '2026-03-20',
            'file_attachments' => [],
        ]);

        $response = $this->withToken($token)->postJson("/api/homeworks/{$homework->id}/status", [
            'student_id' => $student->id,
            'status' => 'Done',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'Done');
    }

    public function test_teacher_can_upload_homework_attachments_into_media_library(): void
    {
        $this->seed();
        Storage::fake('public');

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $assignment = DB::table('teacher_class')->where('teacher_id', $teacher->id)->first();
        $this->assertNotNull($assignment);
        $token = $teacher->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->post('/api/homeworks', [
            'class_id' => $assignment->class_id,
            'subject_id' => $assignment->subject_id,
            'title' => 'Homework With Files',
            'question' => 'Read the file.',
            'attachments' => [
                UploadedFile::fake()->create('lesson.pdf', 120, 'application/pdf'),
            ],
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Homework With Files');

        $homework = Homework::query()->where('title', 'Homework With Files')->firstOrFail();
        $this->assertDatabaseHas('media', [
            'mediable_type' => Homework::class,
            'mediable_id' => $homework->id,
            'category' => 'attachment',
        ]);
        $this->assertNotEmpty($homework->fresh()->file_attachments);
    }
}
