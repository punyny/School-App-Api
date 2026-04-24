<?php

namespace Tests\Feature\Api;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ScoreApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_create_score(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $assignment = DB::table('teacher_class')->where('teacher_id', $teacher->id)->first();
        $this->assertNotNull($assignment);
        $student = Student::query()->where('class_id', (int) $assignment->class_id)->firstOrFail();
        $token = $teacher->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/scores', [
            'student_id' => $student->id,
            'subject_id' => (int) $assignment->subject_id,
            'class_id' => (int) $assignment->class_id,
            'exam_score' => 88,
            'total_score' => 92,
            'month' => 3,
            'quarter' => 1,
            'period' => 'Midterm',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.grade', 'A');
    }

    public function test_teacher_cannot_create_score_for_unassigned_subject(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $assignment = DB::table('teacher_class')->where('teacher_id', $teacher->id)->first();
        $this->assertNotNull($assignment);

        $student = Student::query()->where('class_id', (int) $assignment->class_id)->firstOrFail();
        $unassignedSubjectId = Subject::query()
            ->where('school_id', $teacher->school_id)
            ->whereNotIn('id', DB::table('teacher_class')->where('teacher_id', $teacher->id)->pluck('subject_id'))
            ->value('id');

        $this->assertNotNull($unassignedSubjectId);
        $token = $teacher->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/scores', [
            'student_id' => $student->id,
            'subject_id' => (int) $unassignedSubjectId,
            'class_id' => (int) $assignment->class_id,
            'exam_score' => 70,
            'total_score' => 75,
            'assessment_type' => 'monthly',
            'month' => 3,
            'period' => 'Unauthorized Subject',
        ]);

        $response->assertForbidden();
    }

    public function test_student_cannot_create_score(): void
    {
        $this->seed();

        $studentUser = User::query()->where('email', 'student@example.com')->firstOrFail();
        $student = Student::query()->firstOrFail();
        $class = SchoolClass::query()->firstOrFail();
        $subject = Subject::query()->firstOrFail();
        $token = $studentUser->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/scores', [
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'exam_score' => 70,
            'total_score' => 75,
        ]);

        $response->assertForbidden();
    }

    public function test_teacher_semester_scores_auto_compute_rank_in_class(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $assignment = DB::table('teacher_class')->where('teacher_id', $teacher->id)->first();
        $this->assertNotNull($assignment);

        $class = SchoolClass::query()->findOrFail((int) $assignment->class_id);
        $subject = Subject::query()->findOrFail((int) $assignment->subject_id);
        $studentOne = Student::query()->where('class_id', $class->id)->firstOrFail();

        $secondStudentUser = User::factory()->student((int) $teacher->school_id)->create();
        $studentTwo = Student::query()->create([
            'user_id' => $secondStudentUser->id,
            'class_id' => $class->id,
            'grade' => $studentOne->grade,
        ]);

        $token = $teacher->createToken('phpunit')->plainTextToken;

        $first = $this->withToken($token)->postJson('/api/scores', [
            'student_id' => $studentOne->id,
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'exam_score' => 80,
            'total_score' => 80,
            'assessment_type' => 'semester',
            'semester' => 1,
            'academic_year' => '2025-2026',
            'period' => 'Semester 1',
        ]);

        $first->assertCreated()
            ->assertJsonPath('data.rank_in_class', 1)
            ->assertJsonPath('data.month', null);

        $second = $this->withToken($token)->postJson('/api/scores', [
            'student_id' => $studentTwo->id,
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'exam_score' => 92,
            'total_score' => 92,
            'assessment_type' => 'semester',
            'semester' => 1,
            'academic_year' => '2025-2026',
            'period' => 'Semester 1',
        ]);

        $second->assertCreated()
            ->assertJsonPath('data.rank_in_class', 1);

        $firstId = (int) $first->json('data.id');
        $this->withToken($token)->getJson('/api/scores/'.$firstId)
            ->assertOk()
            ->assertJsonPath('data.rank_in_class', 2);
    }

    public function test_yearly_score_cannot_be_created_manually(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $assignment = DB::table('teacher_class')->where('teacher_id', $teacher->id)->first();
        $this->assertNotNull($assignment);

        $student = Student::query()->where('class_id', (int) $assignment->class_id)->firstOrFail();
        $token = $teacher->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/scores', [
            'student_id' => $student->id,
            'subject_id' => (int) $assignment->subject_id,
            'class_id' => (int) $assignment->class_id,
            'exam_score' => 85,
            'total_score' => 87,
            'assessment_type' => 'yearly',
            'month' => 7,
            'semester' => 2,
            'academic_year' => '2025-2026',
            'period' => 'Annual',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('assessment_type');
    }

    public function test_report_card_and_dashboard_summary_use_actual_class_subject_count(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();

        $class = SchoolClass::query()->create([
            'school_id' => (int) $admin->school_id,
            'name' => 'GPA-X1',
            'grade_level' => 'Grade 9',
            'room' => 'R-901',
        ]);

        $passwordHash = Hash::make('password123');

        $studentUser = User::query()->create([
            'name' => 'GPA Subject Count Student',
            'email' => 'gpa.subject.count.student@example.com',
            'role' => 'student',
            'school_id' => (int) $admin->school_id,
            'password' => $passwordHash,
            'password_hash' => $passwordHash,
            'active' => true,
            'is_active' => true,
        ]);

        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'class_id' => $class->id,
            'grade' => '9',
        ]);

        $subjects = Subject::query()
            ->where('school_id', (int) $admin->school_id)
            ->orderBy('id')
            ->take(2)
            ->get();

        if ($subjects->count() < 2) {
            $subjects->push(Subject::query()->create([
                'school_id' => (int) $admin->school_id,
                'name' => 'Supplementary Subject',
            ]));
        }

        DB::table('teacher_class')->insert([
            [
                'teacher_id' => $teacher->id,
                'class_id' => $class->id,
                'subject_id' => (int) $subjects[0]->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'teacher_id' => $teacher->id,
                'class_id' => $class->id,
                'subject_id' => (int) $subjects[1]->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $teacherToken = $teacher->createToken('phpunit')->plainTextToken;

        $this->withToken($teacherToken)->postJson('/api/scores', [
            'student_id' => $student->id,
            'subject_id' => (int) $subjects[0]->id,
            'class_id' => $class->id,
            'exam_score' => 100,
            'total_score' => 100,
            'assessment_type' => 'monthly',
            'month' => 3,
            'period' => 'March',
        ])->assertCreated();

        $adminToken = $admin->createToken('phpunit-admin')->plainTextToken;

        $this->withToken($adminToken)->getJson('/api/report-cards/'.$student->id)
            ->assertOk()
            ->assertJsonPath('data.summary.subjects_count', 2)
            ->assertJsonPath('data.summary.average_score', '50.00')
            ->assertJsonPath('data.summary.gpa', '0.50')
            ->assertJsonPath('data.summary.overall_grade', 'E');

    }

    public function test_semester_total_score_is_computed_from_monthly_scores_and_semester_exam(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $assignment = DB::table('teacher_class')->where('teacher_id', $teacher->id)->first();
        $this->assertNotNull($assignment);
        $student = Student::query()->where('class_id', (int) $assignment->class_id)->firstOrFail();
        $token = $teacher->createToken('phpunit')->plainTextToken;

        $this->withToken($token)->postJson('/api/scores', [
            'student_id' => $student->id,
            'subject_id' => (int) $assignment->subject_id,
            'class_id' => (int) $assignment->class_id,
            'exam_score' => 80,
            'total_score' => 80,
            'assessment_type' => 'monthly',
            'month' => 1,
            'academic_year' => '2025-2026',
            'period' => 'មករា',
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/scores', [
            'student_id' => $student->id,
            'subject_id' => (int) $assignment->subject_id,
            'class_id' => (int) $assignment->class_id,
            'exam_score' => 90,
            'total_score' => 90,
            'assessment_type' => 'monthly',
            'month' => 2,
            'academic_year' => '2025-2026',
            'period' => 'កុម្ភៈ',
        ])->assertCreated();

        $response = $this->withToken($token)->postJson('/api/scores', [
            'student_id' => $student->id,
            'subject_id' => (int) $assignment->subject_id,
            'class_id' => (int) $assignment->class_id,
            'exam_score' => 70,
            'total_score' => 1,
            'assessment_type' => 'semester',
            'semester' => 1,
            'academic_year' => '2025-2026',
            'period' => 'Semester 1',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.total_score', 80)
            ->assertJsonPath('data.grade', 'B');
    }

    public function test_yearly_total_score_is_auto_generated_from_monthly_and_semester_components(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $assignment = DB::table('teacher_class')->where('teacher_id', $teacher->id)->first();
        $this->assertNotNull($assignment);
        $student = Student::query()->where('class_id', (int) $assignment->class_id)->firstOrFail();
        $token = $teacher->createToken('phpunit')->plainTextToken;

        $this->withToken($token)->postJson('/api/scores', [
            'student_id' => $student->id,
            'subject_id' => (int) $assignment->subject_id,
            'class_id' => (int) $assignment->class_id,
            'exam_score' => 60,
            'total_score' => 60,
            'assessment_type' => 'monthly',
            'month' => 1,
            'academic_year' => '2025-2026',
            'period' => 'មករា',
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/scores', [
            'student_id' => $student->id,
            'subject_id' => (int) $assignment->subject_id,
            'class_id' => (int) $assignment->class_id,
            'exam_score' => 80,
            'total_score' => 80,
            'assessment_type' => 'monthly',
            'month' => 2,
            'academic_year' => '2025-2026',
            'period' => 'កុម្ភៈ',
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/scores', [
            'student_id' => $student->id,
            'subject_id' => (int) $assignment->subject_id,
            'class_id' => (int) $assignment->class_id,
            'exam_score' => 70,
            'total_score' => 70,
            'assessment_type' => 'semester',
            'semester' => 1,
            'academic_year' => '2025-2026',
            'period' => 'Semester 1',
        ])->assertCreated();

        $yearlyRows = DB::table('scores')
            ->where('student_id', $student->id)
            ->where('subject_id', (int) $assignment->subject_id)
            ->where('class_id', (int) $assignment->class_id)
            ->where('assessment_type', 'yearly')
            ->where('academic_year', '2025-2026')
            ->get();

        $this->assertCount(1, $yearlyRows);
        $this->assertSame('70.00', number_format((float) $yearlyRows->first()->total_score, 2, '.', ''));
        $this->assertSame('70.00', number_format((float) $yearlyRows->first()->exam_score, 2, '.', ''));
        $this->assertSame('C', $yearlyRows->first()->grade);
    }

    public function test_score_cannot_exceed_subject_full_score(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $assignment = DB::table('teacher_class')->where('teacher_id', $teacher->id)->first();
        $this->assertNotNull($assignment);
        $student = Student::query()->where('class_id', (int) $assignment->class_id)->firstOrFail();
        $subject = Subject::query()->findOrFail((int) $assignment->subject_id);
        $subject->update(['full_score' => 50]);
        $token = $teacher->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/scores', [
            'student_id' => $student->id,
            'subject_id' => (int) $assignment->subject_id,
            'class_id' => (int) $assignment->class_id,
            'exam_score' => 60,
            'total_score' => 60,
            'assessment_type' => 'monthly',
            'month' => 3,
            'period' => 'March',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('exam_score');
    }
}
