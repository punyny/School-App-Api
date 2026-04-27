<?php

use App\Http\Controllers\Api\AcademicPromotionController;
use App\Http\Controllers\Api\AcademicYearController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClassController;
use App\Http\Controllers\Api\DashboardSummaryController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\ExamSubjectController;
use App\Http\Controllers\Api\FeeTypeController;
use App\Http\Controllers\Api\GuardianController;
use App\Http\Controllers\Api\HomeworkController;
use App\Http\Controllers\Api\IncidentReportController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\MarkController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReportCardController;
use App\Http\Controllers\Api\SchoolController;
use App\Http\Controllers\Api\ScoreController;
use App\Http\Controllers\Api\SectionController;
use App\Http\Controllers\Api\StudentManagementController;
use App\Http\Controllers\Api\StudentFeeController;
use App\Http\Controllers\Api\SubstituteTeacherAssignmentController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\TeacherAssignmentController;
use App\Http\Controllers\Api\TeacherProfileController;
use App\Http\Controllers\Api\TermController;
use App\Http\Controllers\Api\TimetableController;
use App\Http\Controllers\Api\TelegramLinkController;
use App\Http\Controllers\Api\TelegramWebhookController;
use App\Http\Controllers\Api\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/docs/openapi.yaml', function () {
    return response(
        file_get_contents(base_path('docs/openapi.yaml')) ?: '',
        200,
        ['Content-Type' => 'application/yaml; charset=UTF-8']
    );
});

Route::post('/integrations/telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->middleware('throttle:120,1');

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/magic-link/request', [AuthController::class, 'requestMagicLink']);
    Route::post('/magic-link/verify', [AuthController::class, 'verifyMagicLink']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    Route::middleware(['auth:sanctum', 'verified'])->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::patch('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
    });
});

Route::middleware(['auth:sanctum', 'verified', 'role:super-admin', 'admin.ip'])->group(function (): void {
    Route::get('/schools', [SchoolController::class, 'index']);
    Route::post('/schools', [SchoolController::class, 'store'])
        ->middleware('can:create,'.\App\Models\School::class);
    Route::get('/schools/{school}', [SchoolController::class, 'show']);
    Route::put('/schools/{school}', [SchoolController::class, 'update'])
        ->middleware('can:update,school');
    Route::patch('/schools/{school}', [SchoolController::class, 'update'])
        ->middleware('can:update,school');
    Route::delete('/schools/{school}', [SchoolController::class, 'destroy'])
        ->middleware('can:delete,school');
});

Route::middleware(['auth:sanctum', 'verified', 'role:super-admin,admin', 'admin.ip'])->group(function (): void {
    Route::get('/audit-logs', [AuditLogController::class, 'index']);

    Route::get('/users', [UserManagementController::class, 'index']);
    Route::post('/users', [UserManagementController::class, 'store'])
        ->middleware('can:create,'.\App\Models\User::class);
    Route::post('/users/bulk-delete', [UserManagementController::class, 'bulkDestroy'])
        ->middleware('can:create,'.\App\Models\User::class);
    Route::get('/users/{user}', [UserManagementController::class, 'show']);
    Route::post('/users/import/csv', [UserManagementController::class, 'importCsv'])
        ->middleware('can:create,'.\App\Models\User::class);
    Route::put('/users/{user}', [UserManagementController::class, 'update'])
        ->middleware('can:update,user');
    Route::patch('/users/{user}', [UserManagementController::class, 'update'])
        ->middleware('can:update,user');
    Route::post('/users/{user}/change-password', [UserManagementController::class, 'changePassword'])
        ->middleware('role:super-admin')
        ->middleware('can:update,user');
    Route::post('/users/{user}/resend-verification-email', [UserManagementController::class, 'resendVerificationEmail'])
        ->middleware('can:update,user');
    Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])
        ->middleware('can:delete,user');
    Route::post('/users/{userId}/restore', [UserManagementController::class, 'restore']);
});

Route::middleware(['auth:sanctum', 'verified', 'role:super-admin,admin,teacher,student,parent', 'admin.ip'])->group(function (): void {
    Route::get('/dashboard/summary', [DashboardSummaryController::class, 'summary']);
    Route::post('/integrations/telegram/link-code', [TelegramLinkController::class, 'issueLinkCode'])
        ->middleware('throttle:6,1');

    Route::get('/classes', [ClassController::class, 'index']);
    Route::get('/classes/{schoolClass}', [ClassController::class, 'show']);

    Route::get('/subjects', [SubjectController::class, 'index']);
    Route::get('/subjects/{subject}', [SubjectController::class, 'show']);

    Route::get('/timetables', [TimetableController::class, 'index']);
    Route::get('/timetables/{timetable}', [TimetableController::class, 'show']);

    Route::get('/academic-years', [AcademicYearController::class, 'index']);
    Route::get('/academic-years/{academicYear}', [AcademicYearController::class, 'show']);
    Route::get('/terms', [TermController::class, 'index']);
    Route::get('/terms/{term}', [TermController::class, 'show']);
    Route::get('/sections', [SectionController::class, 'index']);
    Route::get('/sections/{section}', [SectionController::class, 'show']);
    Route::get('/guardians', [GuardianController::class, 'index']);
    Route::get('/guardians/{guardian}', [GuardianController::class, 'show']);
    Route::get('/teachers', [TeacherProfileController::class, 'index']);
    Route::get('/teachers/{teacher}', [TeacherProfileController::class, 'show']);
    Route::get('/teacher-assignments', [TeacherAssignmentController::class, 'index']);
    Route::get('/teacher-assignments/{teacherAssignment}', [TeacherAssignmentController::class, 'show']);
    Route::get('/enrollments', [EnrollmentController::class, 'index']);
    Route::get('/enrollments/{enrollment}', [EnrollmentController::class, 'show']);
    Route::get('/exams', [ExamController::class, 'index']);
    Route::get('/exams/{exam}', [ExamController::class, 'show']);
    Route::get('/exam-subjects', [ExamSubjectController::class, 'index']);
    Route::get('/exam-subjects/{examSubject}', [ExamSubjectController::class, 'show']);
    Route::get('/marks', [MarkController::class, 'index']);
    Route::get('/marks/{mark}', [MarkController::class, 'show']);
    Route::get('/fee-types', [FeeTypeController::class, 'index']);
    Route::get('/fee-types/{feeType}', [FeeTypeController::class, 'show']);
    Route::get('/student-fees', [StudentFeeController::class, 'index']);
    Route::get('/student-fees/{studentFee}', [StudentFeeController::class, 'show']);
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'verified', 'role:super-admin,admin', 'admin.ip'])->group(function (): void {
    Route::post('/classes', [ClassController::class, 'store'])
        ->middleware('can:create,'.\App\Models\SchoolClass::class);
    Route::put('/classes/{schoolClass}', [ClassController::class, 'update'])
        ->middleware('can:update,schoolClass');
    Route::patch('/classes/{schoolClass}', [ClassController::class, 'update'])
        ->middleware('can:update,schoolClass');
    Route::put('/classes/{schoolClass}/teacher-assignments', [ClassController::class, 'syncTeacherAssignments'])
        ->middleware('can:update,schoolClass');
    Route::patch('/classes/{schoolClass}/teacher-assignments', [ClassController::class, 'syncTeacherAssignments'])
        ->middleware('can:update,schoolClass');
    Route::delete('/classes/{schoolClass}', [ClassController::class, 'destroy'])
        ->middleware('can:delete,schoolClass');
    Route::post('/classes/{classId}/restore', [ClassController::class, 'restore']);

    Route::post('/subjects', [SubjectController::class, 'store'])
        ->middleware('can:create,'.\App\Models\Subject::class);
    Route::post('/subjects/install-khmer-core', [SubjectController::class, 'installKhmerCore'])
        ->middleware('can:create,'.\App\Models\Subject::class);
    Route::put('/subjects/{subject}', [SubjectController::class, 'update'])
        ->middleware('can:update,subject');
    Route::patch('/subjects/{subject}', [SubjectController::class, 'update'])
        ->middleware('can:update,subject');
    Route::delete('/subjects/{subject}', [SubjectController::class, 'destroy'])
        ->middleware('can:delete,subject');

    Route::post('/academic-years', [AcademicYearController::class, 'store']);
    Route::put('/academic-years/{academicYear}', [AcademicYearController::class, 'update']);
    Route::patch('/academic-years/{academicYear}', [AcademicYearController::class, 'update']);
    Route::delete('/academic-years/{academicYear}', [AcademicYearController::class, 'destroy']);

    Route::post('/terms', [TermController::class, 'store']);
    Route::put('/terms/{term}', [TermController::class, 'update']);
    Route::patch('/terms/{term}', [TermController::class, 'update']);
    Route::delete('/terms/{term}', [TermController::class, 'destroy']);

    Route::post('/sections', [SectionController::class, 'store']);
    Route::put('/sections/{section}', [SectionController::class, 'update']);
    Route::patch('/sections/{section}', [SectionController::class, 'update']);
    Route::delete('/sections/{section}', [SectionController::class, 'destroy']);

    Route::post('/guardians', [GuardianController::class, 'store']);
    Route::put('/guardians/{guardian}', [GuardianController::class, 'update']);
    Route::patch('/guardians/{guardian}', [GuardianController::class, 'update']);
    Route::delete('/guardians/{guardian}', [GuardianController::class, 'destroy']);

    Route::post('/teachers', [TeacherProfileController::class, 'store']);
    Route::put('/teachers/{teacher}', [TeacherProfileController::class, 'update']);
    Route::patch('/teachers/{teacher}', [TeacherProfileController::class, 'update']);
    Route::delete('/teachers/{teacher}', [TeacherProfileController::class, 'destroy']);

    Route::post('/teacher-assignments', [TeacherAssignmentController::class, 'store']);
    Route::put('/teacher-assignments/{teacherAssignment}', [TeacherAssignmentController::class, 'update']);
    Route::patch('/teacher-assignments/{teacherAssignment}', [TeacherAssignmentController::class, 'update']);
    Route::delete('/teacher-assignments/{teacherAssignment}', [TeacherAssignmentController::class, 'destroy']);

    Route::post('/enrollments', [EnrollmentController::class, 'store']);
    Route::put('/enrollments/{enrollment}', [EnrollmentController::class, 'update']);
    Route::patch('/enrollments/{enrollment}', [EnrollmentController::class, 'update']);
    Route::delete('/enrollments/{enrollment}', [EnrollmentController::class, 'destroy']);

    Route::post('/exams', [ExamController::class, 'store']);
    Route::put('/exams/{exam}', [ExamController::class, 'update']);
    Route::patch('/exams/{exam}', [ExamController::class, 'update']);
    Route::delete('/exams/{exam}', [ExamController::class, 'destroy']);

    Route::post('/exam-subjects', [ExamSubjectController::class, 'store']);
    Route::put('/exam-subjects/{examSubject}', [ExamSubjectController::class, 'update']);
    Route::patch('/exam-subjects/{examSubject}', [ExamSubjectController::class, 'update']);
    Route::delete('/exam-subjects/{examSubject}', [ExamSubjectController::class, 'destroy']);

    Route::post('/marks', [MarkController::class, 'store']);
    Route::put('/marks/{mark}', [MarkController::class, 'update']);
    Route::patch('/marks/{mark}', [MarkController::class, 'update']);
    Route::delete('/marks/{mark}', [MarkController::class, 'destroy']);

    Route::post('/fee-types', [FeeTypeController::class, 'store']);
    Route::put('/fee-types/{feeType}', [FeeTypeController::class, 'update']);
    Route::patch('/fee-types/{feeType}', [FeeTypeController::class, 'update']);
    Route::delete('/fee-types/{feeType}', [FeeTypeController::class, 'destroy']);

    Route::post('/student-fees', [StudentFeeController::class, 'store']);
    Route::put('/student-fees/{studentFee}', [StudentFeeController::class, 'update']);
    Route::patch('/student-fees/{studentFee}', [StudentFeeController::class, 'update']);
    Route::delete('/student-fees/{studentFee}', [StudentFeeController::class, 'destroy']);

    Route::post('/payments', [PaymentController::class, 'store']);
    Route::put('/payments/{payment}', [PaymentController::class, 'update']);
    Route::patch('/payments/{payment}', [PaymentController::class, 'update']);
    Route::delete('/payments/{payment}', [PaymentController::class, 'destroy']);

    Route::post('/substitute-assignments', [SubstituteTeacherAssignmentController::class, 'store']);
    Route::delete('/substitute-assignments/{substituteAssignment}', [SubstituteTeacherAssignmentController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'verified', 'role:super-admin,admin,teacher,student', 'admin.ip'])->group(function (): void {
    Route::post('/timetables', [TimetableController::class, 'store'])
        ->middleware('can:create,'.\App\Models\Timetable::class);
    Route::put('/timetables/{timetable}', [TimetableController::class, 'update'])
        ->middleware('can:update,timetable');
    Route::patch('/timetables/{timetable}', [TimetableController::class, 'update'])
        ->middleware('can:update,timetable');
    Route::delete('/timetables/{timetable}', [TimetableController::class, 'destroy'])
        ->middleware('can:delete,timetable');
});

Route::middleware(['auth:sanctum', 'verified', 'role:super-admin,admin', 'admin.ip'])->group(function (): void {
    Route::get('/media', [MediaController::class, 'index']);
    Route::delete('/media/{media}', [MediaController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'verified', 'role:super-admin,admin,teacher', 'admin.ip'])->group(function (): void {
    Route::get('/attendance/tracking-context', [AttendanceController::class, 'trackingContext'])
        ->middleware('can:create,'.\App\Models\Attendance::class);
    Route::get('/substitute-assignments', [SubstituteTeacherAssignmentController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'verified', 'role:super-admin,admin,teacher,student,parent', 'admin.ip'])->group(function (): void {
    Route::get('/attendance', [AttendanceController::class, 'index']);
    Route::get('/attendance/monthly-report', [AttendanceController::class, 'monthlyReport']);
    Route::get('/attendance/export/csv', [AttendanceController::class, 'exportCsv']);
    Route::get('/attendance/export/pdf', [AttendanceController::class, 'exportPdf']);
    Route::get('/attendance/{attendance}', [AttendanceController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'verified', 'role:super-admin,admin,teacher,student', 'admin.ip'])->group(function (): void {
    Route::post('/attendance', [AttendanceController::class, 'store'])
        ->middleware('can:create,'.\App\Models\Attendance::class);
    Route::post('/attendance/daily-sheet', [AttendanceController::class, 'storeDailySheet'])
        ->middleware('can:create,'.\App\Models\Attendance::class);
    Route::post('/attendance/import/csv', [AttendanceController::class, 'importCsv']);
    Route::put('/attendance/{attendance}', [AttendanceController::class, 'update'])
        ->middleware('can:update,attendance');
    Route::patch('/attendance/{attendance}', [AttendanceController::class, 'update'])
        ->middleware('can:update,attendance');
    Route::delete('/attendance/{attendance}', [AttendanceController::class, 'destroy'])
        ->middleware('can:delete,attendance');
});

Route::middleware(['auth:sanctum', 'verified', 'role:super-admin,admin,teacher,student,parent', 'admin.ip'])->group(function (): void {
    Route::get('/homeworks', [HomeworkController::class, 'index']);
    Route::get('/homeworks/export/pdf', [HomeworkController::class, 'exportPdf']);
    Route::get('/homeworks/{homework}', [HomeworkController::class, 'show']);
    Route::get('/scores', [ScoreController::class, 'index']);
    Route::get('/scores/export/csv', [ScoreController::class, 'exportCsv']);
    Route::get('/scores/export/pdf', [ScoreController::class, 'exportPdf']);
    Route::get('/scores/{score}', [ScoreController::class, 'show']);
    Route::get('/report-cards/{student}', [ReportCardController::class, 'show']);
    Route::get('/report-cards/{student}/pdf', [ReportCardController::class, 'exportPdf']);
    Route::get('/leave-requests', [LeaveRequestController::class, 'index']);
    Route::put('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'update'])
        ->middleware('can:update,leaveRequest');
    Route::patch('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'update'])
        ->middleware('can:update,leaveRequest');
    Route::delete('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'destroy'])
        ->middleware('can:delete,leaveRequest');
    Route::get('/leave-requests/{leaveRequest}', [LeaveRequestController::class, 'show']);
    Route::get('/announcements', [AnnouncementController::class, 'index']);
    Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/{notification}', [NotificationController::class, 'show']);
    Route::get('/messages', [MessageController::class, 'index']);
    Route::get('/messages/{message}', [MessageController::class, 'show']);
    Route::get('/incident-reports', [IncidentReportController::class, 'index']);
    Route::get('/incident-reports/{incidentReport}', [IncidentReportController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'verified', 'role:student,parent', 'admin.ip'])->group(function (): void {
    Route::post('/leave-requests', [LeaveRequestController::class, 'store'])
        ->middleware('can:create,'.\App\Models\LeaveRequest::class);
});

Route::middleware(['auth:sanctum', 'verified', 'role:super-admin,admin,teacher', 'admin.ip'])->group(function (): void {
    Route::post('/homeworks', [HomeworkController::class, 'store'])
        ->middleware('role:teacher')
        ->middleware('can:create,'.\App\Models\Homework::class);
    Route::put('/homeworks/{homework}', [HomeworkController::class, 'update'])
        ->middleware('role:teacher')
        ->middleware('can:update,homework');
    Route::patch('/homeworks/{homework}', [HomeworkController::class, 'update'])
        ->middleware('role:teacher')
        ->middleware('can:update,homework');
    Route::delete('/homeworks/{homework}', [HomeworkController::class, 'destroy'])
        ->middleware('role:teacher')
        ->middleware('can:delete,homework');
    Route::post('/homeworks/{homework}/submissions/{submission}/grade', [HomeworkController::class, 'gradeSubmission'])
        ->middleware('can:update,homework');

    Route::post('/scores', [ScoreController::class, 'store'])
        ->middleware('can:create,'.\App\Models\Score::class);
    Route::post('/scores/import/csv', [ScoreController::class, 'importCsv']);
    Route::put('/scores/{score}', [ScoreController::class, 'update'])
        ->middleware('can:update,score');
    Route::patch('/scores/{score}', [ScoreController::class, 'update'])
        ->middleware('can:update,score');
    Route::delete('/scores/{score}', [ScoreController::class, 'destroy'])
        ->middleware('can:delete,score');

    Route::patch('/leave-requests/{leaveRequest}/status', [LeaveRequestController::class, 'updateStatus'])
        ->middleware('can:updateStatus,leaveRequest');

    Route::post('/announcements', [AnnouncementController::class, 'store'])
        ->middleware('role:super-admin,admin')
        ->middleware('can:create,'.\App\Models\Announcement::class);
    Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])
        ->middleware('role:super-admin,admin')
        ->middleware('can:update,announcement');
    Route::patch('/announcements/{announcement}', [AnnouncementController::class, 'update'])
        ->middleware('role:super-admin,admin')
        ->middleware('can:update,announcement');
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])
        ->middleware('role:super-admin,admin')
        ->middleware('can:delete,announcement');

    Route::post('/notifications', [NotificationController::class, 'store'])
        ->middleware('role:super-admin,admin')
        ->middleware('can:create,'.\App\Models\Notification::class);
    Route::post('/notifications/broadcast', [NotificationController::class, 'broadcast'])
        ->middleware('role:super-admin,admin');
    Route::put('/notifications/{notification}', [NotificationController::class, 'update'])
        ->middleware('can:update,notification');
    Route::patch('/notifications/{notification}', [NotificationController::class, 'update'])
        ->middleware('can:update,notification');

    Route::post('/incident-reports', [IncidentReportController::class, 'store'])
        ->middleware('can:create,'.\App\Models\IncidentReport::class);
    Route::put('/incident-reports/{incidentReport}', [IncidentReportController::class, 'update'])
        ->middleware('can:update,incidentReport');
    Route::patch('/incident-reports/{incidentReport}', [IncidentReportController::class, 'update'])
        ->middleware('can:update,incidentReport');
    Route::delete('/incident-reports/{incidentReport}', [IncidentReportController::class, 'destroy'])
        ->middleware('can:delete,incidentReport');
});

Route::middleware(['auth:sanctum', 'verified', 'role:super-admin,admin,teacher,student', 'admin.ip'])->group(function (): void {
    Route::post('/messages', [MessageController::class, 'store'])
        ->middleware('can:create,'.\App\Models\Message::class);
});

Route::middleware(['auth:sanctum', 'verified', 'role:super-admin,admin', 'admin.ip'])->group(function (): void {
    Route::get('/students', [StudentManagementController::class, 'index']);
    Route::get('/students/export/csv', [StudentManagementController::class, 'exportCsv']);
    Route::post('/students/import/csv', [StudentManagementController::class, 'importCsv']);
    Route::get('/students/{student}', [StudentManagementController::class, 'show']);
    Route::post('/students', [StudentManagementController::class, 'store'])
        ->middleware('can:create,'.\App\Models\Student::class);
    Route::put('/students/{student}', [StudentManagementController::class, 'update'])
        ->middleware('can:update,student');
    Route::patch('/students/{student}', [StudentManagementController::class, 'update'])
        ->middleware('can:update,student');
    Route::delete('/students/{student}', [StudentManagementController::class, 'destroy'])
        ->middleware('can:delete,student');
    Route::post('/students/{studentId}/restore', [StudentManagementController::class, 'restore']);

    Route::post('/academic/promotions/promote-class', [AcademicPromotionController::class, 'promoteClass']);
});

Route::middleware(['auth:sanctum', 'verified', 'role:student,parent'])->group(function (): void {
    Route::post('/homeworks/{homework}/status', [HomeworkController::class, 'updateStatus'])
        ->middleware('can:updateStatus,homework');
    Route::patch('/incident-reports/{incidentReport}/acknowledgment', [IncidentReportController::class, 'updateAcknowledgment'])
        ->middleware('can:updateAcknowledgment,incidentReport');
});

Route::middleware(['auth:sanctum', 'verified', 'role:student'])->group(function (): void {
    Route::post('/homeworks/{homework}/submissions', [HomeworkController::class, 'submit'])
        ->middleware('can:submit,homework');
});

Route::middleware(['auth:sanctum', 'verified', 'role:super-admin,admin,teacher,student,parent', 'admin.ip'])->group(function (): void {
    Route::patch('/notifications/{notification}/read-status', [NotificationController::class, 'updateReadStatus'])
        ->middleware('can:updateReadStatus,notification');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])
        ->middleware('can:delete,notification');
});
