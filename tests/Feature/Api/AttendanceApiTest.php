<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
        $subjectId = (int) $assignment->subject_id;
        $session = $this->activeSessionWindow();
        $token = $teacher->createToken('phpunit')->plainTextToken;
        $this->prepareStudentAndSessionForDate(
            student: $student,
            classId: (int) $assignment->class_id,
            subjectId: $subjectId,
            teacherId: (int) $teacher->id,
            date: $session['date'],
            timeStart: $session['time_start_seconds'],
            timeEnd: $session['time_end_seconds'],
        );

        $response = $this->withToken($token)->postJson('/api/attendance', [
            'student_id' => $student->id,
            'class_id' => (int) $assignment->class_id,
            'subject_id' => $subjectId,
            'date' => $session['date'],
            'time_start' => $session['time_start_short'],
            'time_end' => $session['time_end_short'],
            'status' => 'P',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'P')
            ->assertJsonPath('data.subject_id', $subjectId);
    }

    public function test_teacher_create_attendance_sends_telegram_private_notification_to_parent(): void
    {
        $this->seed();

        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.base_url' => 'https://api.telegram.org',
            'services.telegram.parse_mode' => '',
            'services.telegram.webhook_secret' => '',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 778]], 200),
        ]);

        $parent = User::query()->where('email', 'parent@example.com')->firstOrFail();
        $parent->forceFill(['telegram_chat_id' => '7469476859'])->save();
        $student = $parent->children()->with('user')->firstOrFail();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $assignment = DB::table('teacher_class')
            ->where('teacher_id', $teacher->id)
            ->where('class_id', (int) $student->class_id)
            ->first();
        $this->assertNotNull($assignment);

        $subjectId = (int) $assignment->subject_id;
        $session = $this->activeSessionWindow();
        $token = $teacher->createToken('phpunit')->plainTextToken;

        $this->prepareStudentAndSessionForDate(
            student: $student,
            classId: (int) $student->class_id,
            subjectId: $subjectId,
            teacherId: (int) $teacher->id,
            date: $session['date'],
            timeStart: $session['time_start_seconds'],
            timeEnd: $session['time_end_seconds'],
        );

        $response = $this->withToken($token)->postJson('/api/attendance', [
            'student_id' => $student->id,
            'class_id' => (int) $student->class_id,
            'subject_id' => $subjectId,
            'date' => $session['date'],
            'time_start' => $session['time_start_short'],
            'time_end' => $session['time_end_short'],
            'status' => 'A',
            'remarks' => 'Sick today',
        ]);

        $response->assertCreated();

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($student): bool {
            $data = $request->data();

            return str_contains($request->url(), '/bottest-bot-token/sendMessage')
                && (string) ($data['chat_id'] ?? '') === '7469476859'
                && str_contains((string) ($data['text'] ?? ''), 'សិស្ស : '.(string) ($student->user?->name ?? ''))
                && str_contains((string) ($data['text'] ?? ''), 'ស្ថានភាព : ❌ អវត្តមាន');
        });
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

    public function test_teacher_can_save_daily_attendance_sheet_for_entire_class(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $assignment = DB::table('teacher_class')->where('teacher_id', $teacher->id)->first();
        $this->assertNotNull($assignment);
        $subjectId = (int) $assignment->subject_id;

        $students = Student::query()
            ->where('class_id', (int) $assignment->class_id)
            ->orderBy('id')
            ->take(1)
            ->get();

        $this->assertCount(1, $students);

        $session = $this->activeSessionWindow();
        $token = $teacher->createToken('phpunit')->plainTextToken;
        $this->prepareStudentAndSessionForDate(
            student: $students[0],
            classId: (int) $assignment->class_id,
            subjectId: $subjectId,
            teacherId: (int) $teacher->id,
            date: $session['date'],
            timeStart: $session['time_start_seconds'],
            timeEnd: $session['time_end_seconds'],
        );

        $payload = [
            'class_id' => (int) $assignment->class_id,
            'subject_id' => $subjectId,
            'date' => $session['date'],
            'time_start' => $session['time_start_short'],
            'time_end' => $session['time_end_short'],
            'records' => [
                [
                    'student_id' => $students[0]->id,
                    'status' => 'P',
                    'remarks' => 'On time',
                ],
            ],
        ];

        $response = $this->withToken($token)->postJson('/api/attendance/daily-sheet', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.updated', 0);

        $this->assertDatabaseHas('attendance', [
            'student_id' => $students[0]->id,
            'class_id' => (int) $assignment->class_id,
            'subject_id' => $subjectId,
            'date' => $session['date'],
            'time_start' => $session['time_start_seconds'],
            'status' => 'P',
            'remarks' => 'On time',
        ]);

        $updateResponse = $this->withToken($token)->postJson('/api/attendance/daily-sheet', [
            'class_id' => (int) $assignment->class_id,
            'subject_id' => $subjectId,
            'date' => $session['date'],
            'time_start' => $session['time_start_short'],
            'time_end' => $session['time_end_short'],
            'records' => [
                [
                    'student_id' => $students[0]->id,
                    'status' => 'L',
                    'remarks' => 'Excused leave',
                ],
            ],
        ]);

        $updateResponse->assertCreated()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.updated', 1);

        $this->assertDatabaseHas('attendance', [
            'student_id' => $students[0]->id,
            'class_id' => (int) $assignment->class_id,
            'subject_id' => $subjectId,
            'date' => $session['date'],
            'time_start' => $session['time_start_seconds'],
            'status' => 'L',
            'remarks' => 'Excused leave',
        ]);
    }

    public function test_teacher_cannot_mark_attendance_outside_live_session_time(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $assignment = DB::table('teacher_class')->where('teacher_id', $teacher->id)->first();
        $this->assertNotNull($assignment);

        $student = Student::query()->where('class_id', (int) $assignment->class_id)->firstOrFail();
        $subjectId = (int) $assignment->subject_id;
        $outsideDate = now()->subDay()->toDateString();

        $this->prepareStudentAndSessionForDate(
            student: $student,
            classId: (int) $assignment->class_id,
            subjectId: $subjectId,
            teacherId: (int) $teacher->id,
            date: $outsideDate,
            timeStart: '08:00:00',
            timeEnd: '09:00:00',
        );

        $token = $teacher->createToken('phpunit')->plainTextToken;
        $response = $this->withToken($token)->postJson('/api/attendance', [
            'student_id' => $student->id,
            'class_id' => (int) $assignment->class_id,
            'subject_id' => $subjectId,
            'date' => $outsideDate,
            'time_start' => '08:00',
            'time_end' => '09:00',
            'status' => 'P',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    public function test_substitute_teacher_can_save_daily_attendance_for_session(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $substitute = User::query()->where('email', 'teacher2@example.com')->firstOrFail();
        $assignment = DB::table('teacher_class')->where('teacher_id', $teacher->id)->first();
        $this->assertNotNull($assignment);

        $classId = (int) $assignment->class_id;
        $subjectId = (int) $assignment->subject_id;
        $student = Student::query()->where('class_id', $classId)->firstOrFail();
        $session = $this->activeSessionWindow();

        DB::table('teacher_class')
            ->where('teacher_id', $substitute->id)
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->delete();

        $this->prepareStudentAndSessionForDate(
            student: $student,
            classId: $classId,
            subjectId: $subjectId,
            teacherId: (int) $teacher->id,
            date: $session['date'],
            timeStart: $session['time_start_seconds'],
            timeEnd: $session['time_end_seconds'],
        );

        DB::table('substitute_teacher_assignments')->insert([
            'school_id' => (int) $teacher->school_id,
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'original_teacher_id' => (int) $teacher->id,
            'substitute_teacher_id' => (int) $substitute->id,
            'assigned_by_user_id' => (int) User::query()->where('email', 'admin@example.com')->value('id'),
            'date' => $session['date'],
            'time_start' => $session['time_start_seconds'],
            'time_end' => $session['time_end_seconds'],
            'notes' => 'Coverage for absent teacher',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = $substitute->createToken('phpunit')->plainTextToken;
        $response = $this->withToken($token)->postJson('/api/attendance/daily-sheet', [
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'date' => $session['date'],
            'time_start' => $session['time_start_short'],
            'time_end' => $session['time_end_short'],
            'records' => [
                [
                    'student_id' => $student->id,
                    'status' => 'P',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.created', 1);
    }

    public function test_original_teacher_cannot_mark_daily_sheet_when_substitute_is_assigned(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $substitute = User::query()->where('email', 'teacher2@example.com')->firstOrFail();
        $assignment = DB::table('teacher_class')->where('teacher_id', $teacher->id)->first();
        $this->assertNotNull($assignment);

        $classId = (int) $assignment->class_id;
        $subjectId = (int) $assignment->subject_id;
        $student = Student::query()->where('class_id', $classId)->firstOrFail();
        $session = $this->activeSessionWindow();

        $this->prepareStudentAndSessionForDate(
            student: $student,
            classId: $classId,
            subjectId: $subjectId,
            teacherId: (int) $teacher->id,
            date: $session['date'],
            timeStart: $session['time_start_seconds'],
            timeEnd: $session['time_end_seconds'],
        );

        DB::table('substitute_teacher_assignments')->insert([
            'school_id' => (int) $teacher->school_id,
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'original_teacher_id' => (int) $teacher->id,
            'substitute_teacher_id' => (int) $substitute->id,
            'assigned_by_user_id' => (int) User::query()->where('email', 'admin@example.com')->value('id'),
            'date' => $session['date'],
            'time_start' => $session['time_start_seconds'],
            'time_end' => $session['time_end_seconds'],
            'notes' => 'Temporary substitution',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = $teacher->createToken('phpunit')->plainTextToken;
        $response = $this->withToken($token)->postJson('/api/attendance/daily-sheet', [
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'date' => $session['date'],
            'time_start' => $session['time_start_short'],
            'time_end' => $session['time_end_short'],
            'records' => [
                [
                    'student_id' => $student->id,
                    'status' => 'P',
                ],
            ],
        ]);

        $response->assertForbidden();
    }

    public function test_substitute_teacher_can_access_tracking_context_for_assigned_session(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $substitute = User::query()->where('email', 'teacher2@example.com')->firstOrFail();
        $assignment = DB::table('teacher_class')->where('teacher_id', $teacher->id)->first();
        $this->assertNotNull($assignment);

        $classId = (int) $assignment->class_id;
        $subjectId = (int) $assignment->subject_id;
        $student = Student::query()->where('class_id', $classId)->firstOrFail();
        $session = $this->activeSessionWindow();

        DB::table('teacher_class')
            ->where('teacher_id', $substitute->id)
            ->where('class_id', $classId)
            ->delete();

        DB::table('timetables')
            ->where('teacher_id', $substitute->id)
            ->where('class_id', $classId)
            ->delete();

        $this->prepareStudentAndSessionForDate(
            student: $student,
            classId: $classId,
            subjectId: $subjectId,
            teacherId: (int) $teacher->id,
            date: $session['date'],
            timeStart: $session['time_start_seconds'],
            timeEnd: $session['time_end_seconds'],
        );

        DB::table('substitute_teacher_assignments')->insert([
            'school_id' => (int) $teacher->school_id,
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'original_teacher_id' => (int) $teacher->id,
            'substitute_teacher_id' => (int) $substitute->id,
            'assigned_by_user_id' => (int) User::query()->where('email', 'admin@example.com')->value('id'),
            'date' => $session['date'],
            'time_start' => $session['time_start_seconds'],
            'time_end' => $session['time_end_seconds'],
            'notes' => 'Substitute tracking test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = $substitute->createToken('phpunit')->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/attendance/tracking-context?class_id='.$classId.'&date='.$session['date']);

        $response->assertOk()
            ->assertJsonPath('data.class_id', $classId)
            ->assertJsonFragment([
                'subject_id' => $subjectId,
                'time_start' => $session['time_start_short'],
                'time_end' => $session['time_end_short'],
            ]);
    }

    public function test_substitute_teacher_can_see_assigned_class_in_class_list(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $substitute = User::query()->where('email', 'teacher2@example.com')->firstOrFail();
        $assignment = DB::table('teacher_class')->where('teacher_id', $teacher->id)->first();
        $this->assertNotNull($assignment);

        $classId = (int) $assignment->class_id;
        $subjectId = (int) $assignment->subject_id;
        $student = Student::query()->where('class_id', $classId)->firstOrFail();
        $session = $this->activeSessionWindow();

        DB::table('teacher_class')
            ->where('teacher_id', $substitute->id)
            ->where('class_id', $classId)
            ->delete();

        DB::table('timetables')
            ->where('teacher_id', $substitute->id)
            ->where('class_id', $classId)
            ->delete();

        $this->prepareStudentAndSessionForDate(
            student: $student,
            classId: $classId,
            subjectId: $subjectId,
            teacherId: (int) $teacher->id,
            date: $session['date'],
            timeStart: $session['time_start_seconds'],
            timeEnd: $session['time_end_seconds'],
        );

        DB::table('substitute_teacher_assignments')->insert([
            'school_id' => (int) $teacher->school_id,
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'original_teacher_id' => (int) $teacher->id,
            'substitute_teacher_id' => (int) $substitute->id,
            'assigned_by_user_id' => (int) User::query()->where('email', 'admin@example.com')->value('id'),
            'date' => $session['date'],
            'time_start' => $session['time_start_seconds'],
            'time_end' => $session['time_end_seconds'],
            'notes' => 'Substitute class visibility test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = $substitute->createToken('phpunit')->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/classes?per_page=100');
        $response->assertOk();

        $classIds = collect($response->json('data', []))
            ->map(fn (array $row): int => (int) ($row['id'] ?? 0))
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $this->assertContains($classId, $classIds);
    }

    public function test_monthly_report_groups_absence_by_student_subject_and_time(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $assignment = DB::table('teacher_class')->where('teacher_id', $teacher->id)->first();
        $this->assertNotNull($assignment);

        $student = Student::query()->where('class_id', (int) $assignment->class_id)->firstOrFail();
        $subject = Subject::query()->findOrFail((int) $assignment->subject_id);
        $token = $teacher->createToken('phpunit')->plainTextToken;

        Attendance::query()->create([
            'student_id' => $student->id,
            'class_id' => (int) $assignment->class_id,
            'subject_id' => $subject->id,
            'date' => '2030-04-03',
            'time_start' => '08:00:00',
            'time_end' => '09:00:00',
            'status' => 'A',
            'remarks' => 'Sick',
        ]);

        Attendance::query()->create([
            'student_id' => $student->id,
            'class_id' => (int) $assignment->class_id,
            'subject_id' => $subject->id,
            'date' => '2030-04-10',
            'time_start' => '08:00:00',
            'time_end' => '09:00:00',
            'status' => 'L',
            'remarks' => 'Permission',
        ]);

        $response = $this->withToken($token)->getJson('/api/attendance/monthly-report?month=2030-04');

        $response->assertOk()
            ->assertJsonPath('data.summary.absent_count', 1)
            ->assertJsonPath('data.summary.total_missed_records', 2)
            ->assertJsonCount(2, 'data.absence_rows')
            ->assertJsonPath('data.subject_rows.0.subject_name', $subject->name)
            ->assertJsonPath('data.subject_rows.0.absent_count', 1)
            ->assertJsonPath('data.subject_rows.0.leave_count', 1)
            ->assertJsonPath('data.subject_rows.0.time_slot', '08:00:00 - 09:00:00');
    }

    public function test_admin_monthly_report_includes_class_summary(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $assignment = DB::table('teacher_class')->where('teacher_id', $teacher->id)->first();
        $this->assertNotNull($assignment);

        $student = Student::query()->where('class_id', (int) $assignment->class_id)->firstOrFail();
        Attendance::query()->create([
            'student_id' => $student->id,
            'class_id' => (int) $assignment->class_id,
            'subject_id' => (int) $assignment->subject_id,
            'date' => now()->toDateString(),
            'time_start' => '08:00:00',
            'time_end' => '09:00:00',
            'status' => 'A',
            'remarks' => 'Admin monthly summary check',
        ]);

        $token = $admin->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/attendance/monthly-report?month='.now()->format('Y-m'));

        $response->assertOk();
        $this->assertNotEmpty($response->json('data.class_rows'));
    }

    public function test_teacher_monthly_report_only_shows_subjects_they_teach(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $teacherTwo = User::query()->where('email', 'teacher2@example.com')->firstOrFail();
        $assignment = DB::table('teacher_class')->where('teacher_id', $teacher->id)->first();
        $this->assertNotNull($assignment);

        $classId = (int) $assignment->class_id;
        $ownSubject = Subject::query()->findOrFail((int) $assignment->subject_id);
        $student = Student::query()->where('class_id', $classId)->firstOrFail();
        $existingTeacherSubjectIds = DB::table('teacher_class')
            ->where('teacher_id', $teacher->id)
            ->pluck('subject_id')
            ->filter()
            ->all();
        $otherSubject = Subject::query()
            ->where('school_id', $teacher->school_id)
            ->whereNotIn('id', $existingTeacherSubjectIds === [] ? [$ownSubject->id] : $existingTeacherSubjectIds)
            ->firstOrFail();

        DB::table('teacher_class')->updateOrInsert([
            'teacher_id' => $teacherTwo->id,
            'class_id' => $classId,
            'subject_id' => $otherSubject->id,
        ], [
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Attendance::query()->create([
            'student_id' => $student->id,
            'class_id' => $classId,
            'subject_id' => $ownSubject->id,
            'date' => '2031-07-04',
            'time_start' => '08:00:00',
            'time_end' => '09:00:00',
            'status' => 'A',
        ]);

        Attendance::query()->create([
            'student_id' => $student->id,
            'class_id' => $classId,
            'subject_id' => $otherSubject->id,
            'date' => '2031-07-05',
            'time_start' => '09:00:00',
            'time_end' => '10:00:00',
            'status' => 'A',
        ]);

        $token = $teacher->createToken('phpunit')->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/attendance/monthly-report?month=2031-07');

        $response->assertOk()
            ->assertJsonPath('data.class_rows', []);

        $subjectNames = collect($response->json('data.subject_rows', []))
            ->pluck('subject_name')
            ->all();

        $this->assertContains($ownSubject->name, $subjectNames);
        $this->assertNotContains($otherSubject->name, $subjectNames);
    }

    public function test_semester_report_uses_selected_year_and_semester(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $assignment = DB::table('teacher_class')->where('teacher_id', $teacher->id)->first();
        $this->assertNotNull($assignment);

        $student = Student::query()->where('class_id', (int) $assignment->class_id)->firstOrFail();
        $subjectId = (int) $assignment->subject_id;

        Attendance::query()->create([
            'student_id' => $student->id,
            'class_id' => (int) $assignment->class_id,
            'subject_id' => $subjectId,
            'date' => '2032-03-15',
            'time_start' => '08:00:00',
            'time_end' => '09:00:00',
            'status' => 'A',
            'remarks' => 'Absent in semester 1',
        ]);

        Attendance::query()->create([
            'student_id' => $student->id,
            'class_id' => (int) $assignment->class_id,
            'subject_id' => $subjectId,
            'date' => '2032-08-15',
            'time_start' => '08:00:00',
            'time_end' => '09:00:00',
            'status' => 'A',
            'remarks' => 'Absent in semester 2',
        ]);

        $token = $teacher->createToken('phpunit')->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/attendance/monthly-report?period_type=semester&year=2032&semester=1');

        $response->assertOk()
            ->assertJsonPath('data.period_mode', 'semester')
            ->assertJsonPath('data.date_from', '2032-01-01')
            ->assertJsonPath('data.date_to', '2032-06-30')
            ->assertJsonCount(1, 'data.absence_rows')
            ->assertJsonPath('data.absence_rows.0.remarks', 'Absent in semester 1');
    }

    private function prepareStudentAndSessionForDate(
        Student $student,
        int $classId,
        int $subjectId,
        int $teacherId,
        string $date,
        string $timeStart,
        string $timeEnd,
    ): void {
        $dayOfWeek = strtolower(Carbon::parse($date)->format('l'));

        $student->forceFill([
            'admission_date' => '2020-01-01',
        ])->save();

        SchoolClass::query()->whereKey($classId)->update([
            'study_days' => json_encode([$dayOfWeek], JSON_THROW_ON_ERROR),
            'study_time_start' => '00:00:00',
            'study_time_end' => '23:59:00',
        ]);

        DB::table('timetables')->updateOrInsert(
            [
                'class_id' => $classId,
                'subject_id' => $subjectId,
                'teacher_id' => $teacherId,
                'day_of_week' => $dayOfWeek,
                'time_start' => $timeStart,
                'time_end' => $timeEnd,
            ],
            [
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    /**
     * @return array{date:string,time_start_short:string,time_end_short:string,time_start_seconds:string,time_end_seconds:string}
     */
    private function activeSessionWindow(): array
    {
        $start = now()->subMinutes(5)->second(0);
        $end = now()->addMinutes(25)->second(0);

        if ($end->lessThanOrEqualTo($start)) {
            $end = $start->copy()->addMinutes(30);
        }

        return [
            'date' => $start->toDateString(),
            'time_start_short' => $start->format('H:i'),
            'time_end_short' => $end->format('H:i'),
            'time_start_seconds' => $start->format('H:i:s'),
            'time_end_seconds' => $end->format('H:i:s'),
        ];
    }
}
