<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\Homework;
use App\Models\SchoolClass;
use App\Models\Score;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExportAndDocsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_export_attendance_csv(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $student = Student::query()->firstOrFail();
        $class = SchoolClass::query()->firstOrFail();
        $subject = Subject::query()->firstOrFail();

        Attendance::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'date' => '2030-05-10',
            'time_start' => '08:00:00',
            'time_end' => '09:00:00',
            'status' => 'P',
        ]);

        Sanctum::actingAs($teacher);
        $response = $this->get('/api/attendance/export/csv');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attendance_id,date,time_start', $response->streamedContent());
        $this->assertStringContainsString('subject_name', $response->streamedContent());
        $this->assertStringContainsString(',P,', $response->streamedContent());
    }

    public function test_teacher_can_export_attendance_pdf(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $student = Student::query()->firstOrFail();
        $class = SchoolClass::query()->firstOrFail();
        $subject = Subject::query()->firstOrFail();

        Attendance::query()->create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'date' => '2030-06-10',
            'time_start' => '08:00:00',
            'time_end' => '09:00:00',
            'status' => 'P',
        ]);

        Sanctum::actingAs($teacher);
        $response = $this->get('/api/attendance/export/pdf');

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertTrue(str_starts_with((string) $response->getContent(), '%PDF'));
    }

    public function test_student_can_export_own_scores_csv(): void
    {
        $this->seed();

        $studentUser = User::query()->where('email', 'student@example.com')->firstOrFail();
        $student = Student::query()->firstOrFail();
        $class = SchoolClass::query()->firstOrFail();
        $subject = Subject::query()->firstOrFail();

        Score::query()->create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'exam_score' => 85,
            'total_score' => 88,
            'month' => 3,
            'quarter' => 1,
            'period' => 'Monthly',
            'grade' => 'B',
        ]);

        Sanctum::actingAs($studentUser);
        $response = $this->get('/api/scores/export/csv');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('score_id,student_id,student_name', $content);
        $this->assertStringContainsString((string) $student->id, $content);
    }

    public function test_student_can_export_own_scores_pdf(): void
    {
        $this->seed();

        $studentUser = User::query()->where('email', 'student@example.com')->firstOrFail();
        $student = Student::query()->firstOrFail();
        $class = SchoolClass::query()->firstOrFail();
        $subject = Subject::query()->firstOrFail();

        Score::query()->create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'exam_score' => 85,
            'total_score' => 88,
            'month' => 3,
            'quarter' => 1,
            'period' => 'Monthly',
            'grade' => 'B',
        ]);

        Sanctum::actingAs($studentUser);
        $response = $this->get('/api/scores/export/pdf');

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertTrue(str_starts_with((string) $response->getContent(), '%PDF'));
    }

    public function test_teacher_can_export_homeworks_pdf(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $assignment = \Illuminate\Support\Facades\DB::table('teacher_class')
            ->where('teacher_id', $teacher->id)
            ->first();
        $this->assertNotNull($assignment);

        Homework::query()->create([
            'class_id' => $assignment->class_id,
            'subject_id' => $assignment->subject_id,
            'title' => 'PDF Homework',
            'question' => 'Test question',
            'due_date' => '2026-03-22',
            'file_attachments' => [],
        ]);

        Sanctum::actingAs($teacher);
        $response = $this->get('/api/homeworks/export/pdf');

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertTrue(str_starts_with((string) $response->getContent(), '%PDF'));
    }

    public function test_openapi_yaml_is_accessible(): void
    {
        $response = $this->get('/api/docs/openapi.yaml');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/yaml; charset=UTF-8');
        $response->assertSee('openapi: 3.0.3', false);
        $response->assertSee('/api/attendance/export/csv', false);
        $response->assertSee('/api/attendance/export/pdf', false);
        $response->assertSee('/api/scores/export/pdf', false);
        $response->assertSee('/api/homeworks/export/pdf', false);
    }
}
