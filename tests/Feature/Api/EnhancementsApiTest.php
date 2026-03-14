<?php

namespace Tests\Feature\Api;

use App\Jobs\SendBroadcastNotificationJob;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EnhancementsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_v1_login_and_dashboard_summary_endpoints_work(): void
    {
        $this->seed();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
            'device_name' => 'phpunit',
        ])->assertOk();

        $token = (string) $login->json('token');

        $summary = $this->withToken($token)->getJson('/api/v1/dashboard/summary');
        $summary->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'role',
                    'classes_total',
                    'students_total',
                    'subjects_total',
                    'attendance_today' => ['P', 'A', 'L'],
                ],
            ]);

        $summary->assertHeader('X-Request-Id');
        $summary->assertHeader('X-Response-Time-Ms');
    }

    public function test_timetable_conflict_is_blocked(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $assignment = $teacher->teachingClasses()->firstOrFail();
        $subjectId = (int) ($assignment->pivot->subject_id ?? 0);
        $token = $teacher->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/timetables', [
            'class_id' => $assignment->id,
            'subject_id' => $subjectId,
            'day_of_week' => 'monday',
            'time_start' => '08:30',
            'time_end' => '09:30',
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_promote_and_restore_student(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $student = Student::query()
            ->whereHas('user', fn ($query) => $query->where('email', 'student@example.com'))
            ->firstOrFail();

        $toClass = SchoolClass::query()
            ->where('school_id', $admin->school_id)
            ->whereKeyNot($student->class_id)
            ->firstOrFail();

        $token = $admin->createToken('phpunit')->plainTextToken;

        $this->withToken($token)->postJson('/api/academic/promotions/promote-class', [
            'from_class_id' => $student->class_id,
            'to_class_id' => $toClass->id,
            'student_ids' => [$student->id],
            'dry_run' => true,
        ])->assertOk()
            ->assertJsonPath('data.total', 1);

        $this->withToken($token)->postJson('/api/academic/promotions/promote-class', [
            'from_class_id' => $student->class_id,
            'to_class_id' => $toClass->id,
            'student_ids' => [$student->id],
        ])->assertOk()
            ->assertJsonPath('data.promoted_count', 1);

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'class_id' => $toClass->id,
        ]);

        $this->withToken($token)->deleteJson('/api/students/'.$student->id)->assertOk();
        $this->assertSoftDeleted('students', ['id' => $student->id]);

        $this->withToken($token)->postJson('/api/students/'.$student->id.'/restore')->assertOk();

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'deleted_at' => null,
        ]);
    }

    public function test_admin_can_import_students_scores_and_attendance_csv(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $class = SchoolClass::query()->where('school_id', $admin->school_id)->firstOrFail();
        $subject = Subject::query()->where('school_id', $admin->school_id)->firstOrFail();
        $token = $admin->createToken('phpunit')->plainTextToken;

        $studentsCsv = implode("\n", [
            'name,email,password,class_id,grade',
            'CSV Student,csv.student@example.com,password123,'.$class->id.',7',
        ]);

        $this->withToken($token)->withHeader('Accept', 'application/json')->post('/api/students/import/csv', [
            'file' => UploadedFile::fake()->createWithContent('students.csv', $studentsCsv),
        ])->assertCreated();

        $importedStudent = Student::query()
            ->whereHas('user', fn ($query) => $query->where('email', 'csv.student@example.com'))
            ->firstOrFail();

        $scoresCsv = implode("\n", [
            'student_id,class_id,subject_id,exam_score,total_score,month,quarter,period,grade',
            $importedStudent->id.','.$class->id.','.$subject->id.',80,85,3,1,CSV Import Test,B',
        ]);

        $this->withToken($token)->withHeader('Accept', 'application/json')->post('/api/scores/import/csv', [
            'file' => UploadedFile::fake()->createWithContent('scores.csv', $scoresCsv),
        ])->assertCreated();

        $this->assertDatabaseHas('scores', [
            'student_id' => $importedStudent->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'period' => 'CSV Import Test',
        ]);

        $attendanceCsv = implode("\n", [
            'student_id,class_id,date,time_start,time_end,status',
            $importedStudent->id.','.$class->id.','.now()->toDateString().',08:00,09:00,P',
        ]);

        $this->withToken($token)->withHeader('Accept', 'application/json')->post('/api/attendance/import/csv', [
            'file' => UploadedFile::fake()->createWithContent('attendance.csv', $attendanceCsv),
        ])->assertCreated();
    }

    public function test_admin_can_queue_broadcast_notification(): void
    {
        Queue::fake();
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $token = $admin->createToken('phpunit')->plainTextToken;

        $this->withToken($token)->postJson('/api/notifications/broadcast', [
            'title' => 'Queued Notice',
            'content' => 'Please check your dashboard.',
            'role' => 'student',
        ])->assertStatus(202)
            ->assertJsonPath('data.recipients_count', 2);

        Queue::assertPushed(SendBroadcastNotificationJob::class);
    }

    public function test_admin_can_queue_broadcast_notification_using_audience_shortcut(): void
    {
        Queue::fake();
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $classId = SchoolClass::query()->where('school_id', $admin->school_id)->value('id');
        $token = $admin->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/notifications/broadcast', [
            'title' => 'Class Alert',
            'content' => 'Meeting after school.',
            'audience' => 'class',
            'class_id' => $classId,
        ])->assertStatus(202);

        $this->assertGreaterThan(0, (int) $response->json('data.recipients_count'));
        Queue::assertPushed(SendBroadcastNotificationJob::class);
    }
}
