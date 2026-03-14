<?php

namespace Tests\Feature\Api;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaveRequestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_submit_hourly_leave_request(): void
    {
        $this->seed();

        $studentUser = User::query()->where('email', 'student@example.com')->firstOrFail();
        $student = Student::query()->where('user_id', $studentUser->id)->firstOrFail();
        $subjectId = (int) DB::table('teacher_class')
            ->where('class_id', $student->class_id)
            ->value('subject_id');
        Sanctum::actingAs($studentUser);

        $response = $this->postJson('/api/leave-requests', [
            'subject_ids' => [$subjectId],
            'request_type' => 'hourly',
            'start_date' => '2026-03-12',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'reason' => 'Medical appointment',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.request_type', 'hourly');
    }

    public function test_parent_can_submit_multi_day_leave_and_teacher_can_approve(): void
    {
        $this->seed();

        $parent = User::query()->where('email', 'parent@example.com')->firstOrFail();
        $student = $parent->children()->firstOrFail();
        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $this->assertSame('teacher', $teacher->role);

        $subjectId = (int) DB::table('teacher_class')
            ->where('class_id', $student->class_id)
            ->where('teacher_id', $teacher->id)
            ->value('subject_id');
        $this->assertGreaterThan(0, $subjectId);

        Sanctum::actingAs($parent);
        $submit = $this->postJson('/api/leave-requests', [
            'student_id' => $student->id,
            'subject_ids' => [$subjectId],
            'request_type' => 'multi_day',
            'start_date' => '2026-03-15',
            'end_date' => '2026-03-16',
            'return_date' => '2026-03-17',
            'total_days' => 2,
            'reason' => 'Family event',
        ]);

        $submit->assertCreated();
        $leaveRequestId = (int) $submit->json('data.id');
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->assertTrue(
            $teacher->teachingClasses()
                ->where('classes.id', $student->class_id)
                ->whereIn('teacher_class.subject_id', [$subjectId])
                ->exists()
        );
        $this->assertDatabaseHas('leave_request_recipients', [
            'leave_request_id' => $leaveRequestId,
            'user_id' => $teacher->id,
        ]);
        $this->assertDatabaseHas('leave_request_recipients', [
            'leave_request_id' => $leaveRequestId,
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseMissing('leave_request_recipients', [
            'leave_request_id' => $leaveRequestId,
            'user_id' => $parent->id,
        ]);

        Sanctum::actingAs($teacher);
        $approve = $this->patchJson("/api/leave-requests/{$leaveRequestId}/status", [
            'status' => 'approved',
        ]);

        $approve->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('notification', [
            'user_id' => $student->user_id,
            'title' => 'Leave request approved',
            'read_status' => 0,
        ]);

        $this->assertDatabaseHas('attendance', [
            'student_id' => $student->id,
            'class_id' => $student->class_id,
            'date' => '2026-03-15',
            'status' => 'L',
        ]);
        $this->assertDatabaseHas('attendance', [
            'student_id' => $student->id,
            'class_id' => $student->class_id,
            'date' => '2026-03-16',
            'status' => 'L',
        ]);
    }

    public function test_teacher_cannot_submit_leave_request(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        Sanctum::actingAs($teacher);

        $response = $this->postJson('/api/leave-requests', [
            'student_id' => Student::query()->value('id'),
            'subject_ids' => [DB::table('subjects')->value('id')],
            'request_type' => 'hourly',
            'start_date' => '2026-03-12',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'reason' => 'Not allowed',
        ]);

        $response->assertForbidden();
    }
}
