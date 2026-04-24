<?php

namespace Tests\Feature\Web;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentReportWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_student_reports_page(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $response = $this->actingAs($admin)->get('/panel/student-reports');

        $response->assertOk()
            ->assertSee('Student Academic Report')
            ->assertSee('Generate Report');
    }

    public function test_super_admin_can_generate_student_report_for_monthly_period(): void
    {
        $this->seed();

        $superAdmin = User::query()->where('email', 'superadmin@example.com')->firstOrFail();
        $studentId = (int) Student::query()
            ->whereHas('user', fn ($query) => $query->where('email', 'student@example.com'))
            ->value('id');

        $response = $this->actingAs($superAdmin)->get('/panel/student-reports?student_id='.$studentId.'&report_mode=monthly&month=3&academic_year=2026-2027');

        $response->assertOk()
            ->assertSee('Student Information')
            ->assertSee('Subject Breakdown')
            ->assertSee('Attendance Summary (Selected Period)');
    }

    public function test_teacher_is_forbidden_from_student_reports_page(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();

        $response = $this->actingAs($teacher)->get('/panel/student-reports');

        $response->assertForbidden();
    }

    public function test_admin_can_export_student_report_excel(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $studentId = (int) Student::query()
            ->whereHas('user', fn ($query) => $query->where('email', 'student@example.com'))
            ->value('id');

        $response = $this->actingAs($admin)->get('/panel/student-reports/export-excel?student_id='.$studentId.'&report_mode=monthly&month=3&academic_year=2026-2027');

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');

        $disposition = (string) $response->headers->get('content-disposition', '');
        $this->assertStringContainsString('attachment; filename=student_report_', $disposition);

        $content = $response->streamedContent();
        $this->assertStringContainsString('Student ID', $content);
        $this->assertStringContainsString('Report Mode', $content);
    }
}
