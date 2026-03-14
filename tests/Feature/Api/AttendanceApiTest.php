<?php

namespace Tests\Feature\Api;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_create_attendance(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $assignment = DB::table('teacher_class')->where('teacher_id', $teacher->id)->first();
        $this->assertNotNull($assignment);
        $student = Student::query()->where('class_id', (int) $assignment->class_id)->firstOrFail();
        $token = $teacher->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/attendance', [
            'student_id' => $student->id,
            'class_id' => (int) $assignment->class_id,
            'date' => '2026-03-09',
            'time_start' => '08:00',
            'time_end' => '09:00',
            'status' => 'P',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'P');
    }

    public function test_teacher_cannot_create_attendance_for_unassigned_class(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $assignment = DB::table('teacher_class')->where('teacher_id', $teacher->id)->first();
        $this->assertNotNull($assignment);

        $unassignedClass = SchoolClass::query()
            ->where('school_id', $teacher->school_id)
            ->whereKeyNot((int) $assignment->class_id)
            ->firstOrFail();
        $student = Student::query()->where('class_id', $unassignedClass->id)->firstOrFail();
        $token = $teacher->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/attendance', [
            'student_id' => $student->id,
            'class_id' => $unassignedClass->id,
            'date' => '2026-03-10',
            'time_start' => '08:00',
            'time_end' => '09:00',
            'status' => 'P',
        ]);

        $response->assertForbidden();
    }

    public function test_student_cannot_create_attendance(): void
    {
        $this->seed();

        $studentUser = User::query()->where('email', 'student@example.com')->firstOrFail();
        $student = Student::query()->firstOrFail();
        $class = SchoolClass::query()->firstOrFail();
        $token = $studentUser->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/attendance', [
            'student_id' => $student->id,
            'class_id' => $class->id,
            'date' => '2026-03-09',
            'time_start' => '10:00',
            'time_end' => '11:00',
            'status' => 'A',
        ]);

        $response->assertForbidden();
    }
}
