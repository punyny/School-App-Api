<?php

namespace Tests\Feature\Api;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AcademicModulesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_academic_structure_exam_and_finance_modules(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $teacherUser = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $student = Student::query()->firstOrFail();
        $class = SchoolClass::query()->findOrFail((int) $student->class_id);
        $subject = Subject::query()->where('school_id', $admin->school_id)->firstOrFail();
        $token = $admin->createToken('phpunit')->plainTextToken;

        $startYear = Carbon::now()->year + 1;
        $endYear = $startYear + 1;
        $yearName = $startYear.'/'.$endYear;
        $yearStartDate = Carbon::create($startYear, 9, 1)->toDateString();
        $yearEndDate = Carbon::create($endYear, 8, 31)->toDateString();
        $termEndDate = Carbon::create($endYear, 1, 31)->toDateString();
        $examStartDate = Carbon::create($startYear, 11, 1)->toDateString();
        $examEndDate = Carbon::create($startYear, 11, 5)->toDateString();
        $enrollmentDate = Carbon::create($startYear, 9, 2)->toDateString();
        $feeDueDate = Carbon::create($startYear, 10, 10)->toDateString();
        $feePaymentDate = Carbon::create($startYear, 10, 5)->toDateString();

        $year = $this->withToken($token)->postJson('/api/academic-years', [
            'year_name' => $yearName,
            'start_date' => $yearStartDate,
            'end_date' => $yearEndDate,
            'is_current' => true,
        ])->assertCreated();
        $academicYearId = (int) $year->json('data.academic_year_id');

        $term = $this->withToken($token)->postJson('/api/terms', [
            'academic_year_id' => $academicYearId,
            'term_name' => 'Semester 1',
            'start_date' => $yearStartDate,
            'end_date' => $termEndDate,
        ])->assertCreated();
        $termId = (int) $term->json('data.term_id');

        $teacherProfile = $this->withToken($token)->postJson('/api/teachers', [
            'user_id' => $teacherUser->id,
            'employee_no' => 'EMP-1001',
            'first_name' => 'Teacher',
            'last_name' => 'One',
            'hire_date' => '2025-01-10',
            'status' => 'Active',
        ])->assertCreated();
        $teacherId = (int) $teacherProfile->json('data.teacher_id');

        $section = $this->withToken($token)->postJson('/api/sections', [
            'class_id' => $class->id,
            'academic_year_id' => $academicYearId,
            'section_name' => 'A',
            'class_teacher_id' => $teacherId,
            'room_no' => 'C-101',
        ])->assertCreated();
        $sectionId = (int) $section->json('data.section_id');

        $this->withToken($token)->postJson('/api/teacher-assignments', [
            'teacher_id' => $teacherId,
            'subject_id' => $subject->id,
            'section_id' => $sectionId,
        ])->assertCreated();

        $enrollment = $this->withToken($token)->postJson('/api/enrollments', [
            'student_id' => $student->id,
            'academic_year_id' => $academicYearId,
            'class_id' => $class->id,
            'section_id' => $sectionId,
            'roll_no' => '01',
            'enrollment_date' => $enrollmentDate,
            'status' => 'Enrolled',
        ])->assertCreated();
        $enrollmentId = (int) $enrollment->json('data.enrollment_id');

        $exam = $this->withToken($token)->postJson('/api/exams', [
            'term_id' => $termId,
            'exam_name' => 'Midterm',
            'start_date' => $examStartDate,
            'end_date' => $examEndDate,
        ])->assertCreated();
        $examId = (int) $exam->json('data.exam_id');

        $examSubject = $this->withToken($token)->postJson('/api/exam-subjects', [
            'exam_id' => $examId,
            'subject_id' => $subject->id,
            'max_marks' => 100,
            'pass_marks' => 40,
        ])->assertCreated();
        $examSubjectId = (int) $examSubject->json('data.exam_subject_id');

        $this->withToken($token)->postJson('/api/marks', [
            'exam_subject_id' => $examSubjectId,
            'enrollment_id' => $enrollmentId,
            'obtained_marks' => 89,
            'grade_letter' => 'A',
        ])->assertCreated();

        $feeType = $this->withToken($token)->postJson('/api/fee-types', [
            'fee_name' => 'Tuition',
            'default_amount' => 120.00,
        ])->assertCreated();
        $feeTypeId = (int) $feeType->json('data.fee_type_id');

        $studentFee = $this->withToken($token)->postJson('/api/student-fees', [
            'enrollment_id' => $enrollmentId,
            'fee_type_id' => $feeTypeId,
            'amount_due' => 120.00,
            'due_date' => $feeDueDate,
            'status' => 'Unpaid',
        ])->assertCreated();
        $studentFeeId = (int) $studentFee->json('data.student_fee_id');

        $this->withToken($token)->postJson('/api/payments', [
            'student_fee_id' => $studentFeeId,
            'amount_paid' => 60.00,
            'payment_date' => $feePaymentDate,
            'payment_method' => 'Cash',
        ])->assertCreated();
    }
}
