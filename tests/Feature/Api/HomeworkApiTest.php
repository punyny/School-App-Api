<?php

namespace Tests\Feature\Api;

use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\SchoolClass;
use App\Models\Score;
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

    public function test_student_can_submit_homework_with_text_and_attachment(): void
    {
        $this->seed();
        Storage::fake('public');

        $studentUser = User::query()->where('email', 'student@example.com')->firstOrFail();
        $student = Student::query()->where('user_id', $studentUser->id)->firstOrFail();
        $class = SchoolClass::query()->findOrFail((int) $student->class_id);
        $subject = Subject::query()->where('school_id', $class->school_id)->firstOrFail();
        $studentToken = $studentUser->createToken('phpunit')->plainTextToken;

        $homework = Homework::query()->create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'title' => 'Submission Practice',
            'question' => 'Answer with text and one file.',
            'due_date' => '2026-04-25',
            'file_attachments' => [],
        ]);

        $response = $this->withToken($studentToken)->post("/api/homeworks/{$homework->id}/submissions", [
            'answer_text' => 'My answer from student.',
            'attachments' => [
                UploadedFile::fake()->image('answer.png'),
            ],
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('data.homework_id', $homework->id)
            ->assertJsonPath('data.student_id', $student->id)
            ->assertJsonPath('data.answer_text', 'My answer from student.');

        $this->assertDatabaseHas('homework_submissions', [
            'homework_id' => $homework->id,
            'student_id' => $student->id,
            'answer_text' => 'My answer from student.',
        ]);

        $this->assertDatabaseHas('homeworkstatus', [
            'homework_id' => $homework->id,
            'student_id' => $student->id,
            'status' => 'Done',
        ]);

        $submissionId = (int) $response->json('data.id');
        $this->assertGreaterThan(0, $submissionId);
        $this->assertDatabaseHas('media', [
            'mediable_type' => HomeworkSubmission::class,
            'mediable_id' => $submissionId,
            'category' => 'attachment',
        ]);
    }

    public function test_teacher_can_view_student_submission_in_homework_detail(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $assignment = DB::table('teacher_class')->where('teacher_id', $teacher->id)->first();
        $this->assertNotNull($assignment);
        $teacherToken = $teacher->createToken('phpunit')->plainTextToken;

        $student = Student::query()->where('class_id', (int) $assignment->class_id)->firstOrFail();
        $studentUser = User::query()->findOrFail((int) $student->user_id);
        $studentToken = $studentUser->createToken('phpunit')->plainTextToken;

        $homework = Homework::query()->create([
            'class_id' => (int) $assignment->class_id,
            'subject_id' => (int) $assignment->subject_id,
            'title' => 'Teacher review submission',
            'question' => 'Write short answer.',
            'due_date' => '2026-04-26',
            'file_attachments' => [],
        ]);

        $submit = $this->withToken($studentToken)->postJson("/api/homeworks/{$homework->id}/submissions", [
            'answer_text' => 'Student submitted answer',
        ]);
        $submit->assertOk();

        $response = $this->withToken($teacherToken)->getJson("/api/homeworks/{$homework->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $homework->id)
            ->assertJsonPath('data.submissions.0.student_id', $student->id)
            ->assertJsonPath('data.submissions.0.answer_text', 'Student submitted answer');
    }

    public function test_teacher_can_grade_submission_and_create_homework_auto_monthly_score(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $assignment = DB::table('teacher_class')->where('teacher_id', $teacher->id)->first();
        $this->assertNotNull($assignment);
        $teacherToken = $teacher->createToken('phpunit')->plainTextToken;

        $student = Student::query()->where('class_id', (int) $assignment->class_id)->firstOrFail();
        $homework = Homework::query()->create([
            'class_id' => (int) $assignment->class_id,
            'subject_id' => (int) $assignment->subject_id,
            'title' => 'Grade Homework Monthly',
            'question' => 'Solve and submit.',
            'due_date' => '2026-04-30',
            'file_attachments' => [],
        ]);

        $submission = HomeworkSubmission::query()->create([
            'homework_id' => $homework->id,
            'student_id' => $student->id,
            'answer_text' => 'Monthly graded answer',
            'submitted_at' => now(),
        ]);

        $response = $this->withToken($teacherToken)->postJson(
            "/api/homeworks/{$homework->id}/submissions/{$submission->id}/grade",
            [
                'teacher_score' => 16,
                'teacher_score_max' => 20,
                'score_weight_percent' => 25,
                'assessment_type' => 'monthly',
                'month' => 4,
                'academic_year' => '2026',
                'teacher_feedback' => 'Good progress.',
            ]
        );

        $response->assertOk()
            ->assertJsonPath('data.submission.id', $submission->id)
            ->assertJsonPath('data.submission.score_assessment_type', 'monthly')
            ->assertJsonPath('data.auto_score.period', 'homework-auto');

        $submission->refresh();
        $this->assertSame('monthly', $submission->score_assessment_type);
        $this->assertSame(4, (int) $submission->score_month);
        $this->assertSame('2026', $submission->score_academic_year);
        $this->assertNotNull($submission->graded_at);
        $this->assertSame((int) $teacher->id, (int) $submission->graded_by_user_id);

        $autoScore = Score::query()
            ->where('student_id', $student->id)
            ->where('class_id', (int) $assignment->class_id)
            ->where('subject_id', (int) $assignment->subject_id)
            ->where('assessment_type', 'monthly')
            ->where('month', 4)
            ->whereNull('semester')
            ->where('academic_year', '2026')
            ->where('period', 'homework-auto')
            ->first();

        $this->assertNotNull($autoScore);
        $this->assertEqualsWithDelta(80.0, (float) $autoScore->exam_score, 0.01);
        $this->assertEqualsWithDelta(20.0, (float) $autoScore->total_score, 0.01);
    }

    public function test_admin_can_grade_submission_and_create_homework_auto_score(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $adminToken = $admin->createToken('phpunit')->plainTextToken;

        $class = SchoolClass::query()->where('school_id', $admin->school_id)->firstOrFail();
        $subject = Subject::query()->where('school_id', $admin->school_id)->firstOrFail();
        $student = Student::query()->where('class_id', $class->id)->firstOrFail();

        $homework = Homework::query()->create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'title' => 'Admin Grade Homework',
            'question' => 'Admin can grade this submission.',
            'due_date' => '2026-04-30',
            'file_attachments' => [],
        ]);

        $submission = HomeworkSubmission::query()->create([
            'homework_id' => $homework->id,
            'student_id' => $student->id,
            'answer_text' => 'Submission for admin grading',
            'submitted_at' => now(),
        ]);

        $response = $this->withToken($adminToken)->postJson(
            "/api/homeworks/{$homework->id}/submissions/{$submission->id}/grade",
            [
                'teacher_score' => 14,
                'teacher_score_max' => 20,
                'score_weight_percent' => 50,
                'assessment_type' => 'monthly',
                'month' => 2,
                'academic_year' => '2026',
                'teacher_feedback' => 'Graded by admin.',
            ]
        );

        $response->assertOk()
            ->assertJsonPath('data.submission.id', $submission->id)
            ->assertJsonPath('data.auto_score.period', 'homework-auto');

        $this->assertDatabaseHas('homework_submissions', [
            'id' => $submission->id,
            'graded_by_user_id' => $admin->id,
            'score_assessment_type' => 'monthly',
            'score_month' => 2,
            'score_academic_year' => '2026',
        ]);

        $this->assertDatabaseHas('scores', [
            'student_id' => $student->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'assessment_type' => 'monthly',
            'month' => 2,
            'semester' => null,
            'academic_year' => '2026',
            'period' => 'homework-auto',
        ]);
    }

    public function test_regrading_submission_moves_homework_auto_score_to_new_bucket(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $assignment = DB::table('teacher_class')->where('teacher_id', $teacher->id)->first();
        $this->assertNotNull($assignment);
        $teacherToken = $teacher->createToken('phpunit')->plainTextToken;

        $student = Student::query()->where('class_id', (int) $assignment->class_id)->firstOrFail();
        $homework = Homework::query()->create([
            'class_id' => (int) $assignment->class_id,
            'subject_id' => (int) $assignment->subject_id,
            'title' => 'Grade Homework Rebucket',
            'question' => 'Submit for rebucket test.',
            'due_date' => '2026-05-01',
            'file_attachments' => [],
        ]);

        $submission = HomeworkSubmission::query()->create([
            'homework_id' => $homework->id,
            'student_id' => $student->id,
            'answer_text' => 'Submission for rebucket test',
            'submitted_at' => now(),
        ]);

        $this->withToken($teacherToken)->postJson(
            "/api/homeworks/{$homework->id}/submissions/{$submission->id}/grade",
            [
                'teacher_score' => 9,
                'teacher_score_max' => 10,
                'score_weight_percent' => 40,
                'assessment_type' => 'monthly',
                'month' => 3,
                'academic_year' => '2026',
            ]
        )->assertOk();

        $this->assertDatabaseHas('scores', [
            'student_id' => $student->id,
            'class_id' => (int) $assignment->class_id,
            'subject_id' => (int) $assignment->subject_id,
            'assessment_type' => 'monthly',
            'month' => 3,
            'semester' => null,
            'academic_year' => '2026',
            'period' => 'homework-auto',
        ]);

        $response = $this->withToken($teacherToken)->postJson(
            "/api/homeworks/{$homework->id}/submissions/{$submission->id}/grade",
            [
                'teacher_score' => 18,
                'teacher_score_max' => 20,
                'score_weight_percent' => 60,
                'assessment_type' => 'semester',
                'semester' => 1,
                'academic_year' => '2026',
                'teacher_feedback' => 'Moved to semester bucket.',
            ]
        );

        $response->assertOk()
            ->assertJsonPath('data.submission.score_assessment_type', 'semester')
            ->assertJsonPath('data.auto_score.assessment_type', 'semester')
            ->assertJsonPath('data.auto_score.semester', 1);

        $this->assertDatabaseMissing('scores', [
            'student_id' => $student->id,
            'class_id' => (int) $assignment->class_id,
            'subject_id' => (int) $assignment->subject_id,
            'assessment_type' => 'monthly',
            'month' => 3,
            'academic_year' => '2026',
            'period' => 'homework-auto',
        ]);

        $semesterAutoScore = Score::query()
            ->where('student_id', $student->id)
            ->where('class_id', (int) $assignment->class_id)
            ->where('subject_id', (int) $assignment->subject_id)
            ->where('assessment_type', 'semester')
            ->where('semester', 1)
            ->whereNull('month')
            ->where('academic_year', '2026')
            ->where('period', 'homework-auto')
            ->first();

        $this->assertNotNull($semesterAutoScore);
        $this->assertEqualsWithDelta(90.0, (float) $semesterAutoScore->exam_score, 0.01);
        $this->assertEqualsWithDelta(54.0, (float) $semesterAutoScore->total_score, 0.01);
    }
}
