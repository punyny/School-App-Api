<?php

use App\Http\Controllers\Web\AdminController;
use App\Http\Controllers\Web\AnnouncementCrudController;
use App\Http\Controllers\Web\AuditLogCrudController;
use App\Http\Controllers\Web\AttendanceCrudController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\ClassCrudController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\EmailVerificationController;
use App\Http\Controllers\Web\HomeworkCrudController;
use App\Http\Controllers\Web\IncidentReportCrudController;
use App\Http\Controllers\Web\LeaveRequestCrudController;
use App\Http\Controllers\Web\MediaCrudController;
use App\Http\Controllers\Web\MessageCrudController;
use App\Http\Controllers\Web\NotificationCrudController;
use App\Http\Controllers\Web\ParentController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\SchoolCrudController;
use App\Http\Controllers\Web\ScoreCrudController;
use App\Http\Controllers\Web\StudentController;
use App\Http\Controllers\Web\StudentCrudController;
use App\Http\Controllers\Web\SubjectCrudController;
use App\Http\Controllers\Web\SuperAdminController;
use App\Http\Controllers\Web\TeacherController;
use App\Http\Controllers\Web\TimetableCrudController;
use App\Http\Controllers\Web\UserCrudController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->away(route('login', [], false));
})->name('home');

Route::post('/locale', function (Request $request) {
    $validated = $request->validate([
        'locale' => ['required', 'in:en,km'],
    ]);

    $request->session()->put('locale', $validated['locale']);

    return back();
})->name('locale.switch');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
    Route::get('/login/magic/{id}/{token}', [AuthController::class, 'magicLogin'])->middleware('signed:relative')->name('login.magic');
    Route::post('/login/magic/{id}/{token}', [AuthController::class, 'consumeMagicLogin'])->middleware('signed:relative')->name('login.magic.consume');
    Route::get('/login/mobile/{id}/{token}', [AuthController::class, 'mobileLogin'])->middleware('signed:relative')->name('login.mobile');
});

Route::middleware('auth')->post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::middleware('auth')->get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
Route::middleware(['auth', 'throttle:6,1'])->post('/email/verification-notification', [EmailVerificationController::class, 'send'])->name('verification.send');
Route::middleware(['signed:relative', 'throttle:6,1'])->get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->name('verification.verify');

Route::middleware(['auth', 'verified', 'role:super-admin,admin,teacher,student,parent'])
    ->get('/dashboard', [DashboardController::class, 'index'])
    ->name('dashboard');

Route::middleware(['auth', 'verified', 'role:super-admin,admin,teacher,student,parent'])
    ->group(function (): void {
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/change-password', [ProfileController::class, 'changePassword'])->name('profile.change-password');
    });

Route::prefix('super-admin')
    ->name('super-admin.')
    ->middleware(['auth', 'verified', 'role:super-admin', 'admin.ip'])
    ->group(function (): void {
        Route::get('/dashboard', [SuperAdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/schools', [SuperAdminController::class, 'schools'])->name('schools.index');
        Route::get('/schools/{school}/manage', [SuperAdminController::class, 'manageSchool'])->name('schools.manage');
        Route::get('/users', [SuperAdminController::class, 'users'])->name('users.index');
        Route::get('/settings', [SuperAdminController::class, 'settings'])->name('settings');
    });

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'verified', 'role:admin', 'admin.ip'])
    ->group(function (): void {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/users', [AdminController::class, 'users'])->name('users.index');
        Route::get('/classes', [AdminController::class, 'classes'])->name('classes.index');
        Route::get('/subjects', [AdminController::class, 'subjects'])->name('subjects.index');
        Route::get('/students', [AdminController::class, 'students'])->name('students.index');
        Route::get('/reports', [AdminController::class, 'reports'])->name('reports.index');
    });

Route::prefix('teacher')
    ->name('teacher.')
    ->middleware(['auth', 'verified', 'role:teacher'])
    ->group(function (): void {
        Route::get('/dashboard', [TeacherController::class, 'dashboard'])->name('dashboard');
        Route::get('/attendance', [TeacherController::class, 'attendance'])->name('attendance.index');
        Route::get('/homeworks', [TeacherController::class, 'homeworks'])->name('homeworks.index');
        Route::get('/scores', [TeacherController::class, 'scores'])->name('scores.index');
        Route::get('/messages', [TeacherController::class, 'messages'])->name('messages.index');
        Route::get('/incidents', [TeacherController::class, 'incidents'])->name('incidents.index');
    });

Route::prefix('student')
    ->name('student.')
    ->middleware(['auth', 'verified', 'role:student'])
    ->group(function (): void {
        Route::get('/dashboard', [StudentController::class, 'dashboard'])->name('dashboard');
    });

Route::prefix('parent')
    ->name('parent.')
    ->middleware(['auth', 'verified', 'role:parent'])
    ->group(function (): void {
        Route::get('/dashboard', [ParentController::class, 'dashboard'])->name('dashboard');
    });

Route::prefix('panel/announcements')
    ->name('panel.announcements.')
    ->middleware(['auth', 'verified', 'role:super-admin,admin,teacher,student,parent', 'admin.ip', 'can:web-view-announcements'])
    ->group(function (): void {
        Route::get('/', [AnnouncementCrudController::class, 'index'])->name('index');
    });

Route::prefix('panel/announcements')
    ->name('panel.announcements.')
    ->middleware(['auth', 'verified', 'role:super-admin,admin', 'admin.ip', 'can:web-manage-announcements'])
    ->group(function (): void {
        Route::get('/create', [AnnouncementCrudController::class, 'create'])->name('create');
        Route::post('/', [AnnouncementCrudController::class, 'store'])->name('store');
        Route::get('/{announcement}/edit', [AnnouncementCrudController::class, 'edit'])->name('edit');
        Route::put('/{announcement}', [AnnouncementCrudController::class, 'update'])->name('update');
        Route::delete('/{announcement}', [AnnouncementCrudController::class, 'destroy'])->name('destroy');
    });

Route::prefix('panel/schools')
    ->name('panel.schools.')
    ->middleware(['auth', 'verified', 'role:super-admin,admin', 'admin.ip', 'can:web-manage-schools'])
    ->group(function (): void {
        Route::get('/', [SchoolCrudController::class, 'index'])->name('index');
        Route::get('/create', [SchoolCrudController::class, 'create'])->name('create');
        Route::post('/', [SchoolCrudController::class, 'store'])->name('store');
        Route::get('/enrollment-date', [SchoolCrudController::class, 'enrollmentDate'])->name('enrollment-date');
        Route::put('/enrollment-date', [SchoolCrudController::class, 'updateEnrollmentDate'])->name('enrollment-date.update');
        Route::get('/{school}/edit', [SchoolCrudController::class, 'edit'])->name('edit');
        Route::put('/{school}', [SchoolCrudController::class, 'update'])->name('update');
        Route::delete('/{school}', [SchoolCrudController::class, 'destroy'])->name('destroy');
    });

Route::prefix('panel/users')
    ->name('panel.users.')
    ->middleware(['auth', 'verified', 'role:super-admin,admin', 'admin.ip', 'can:web-manage-users'])
    ->group(function (): void {
        Route::get('/', [UserCrudController::class, 'index'])->name('index');
        Route::post('/import-csv', [UserCrudController::class, 'importCsv'])->name('import-csv');
        Route::post('/bulk-delete', [UserCrudController::class, 'bulkDestroy'])->name('bulk-delete');
        Route::get('/create', [UserCrudController::class, 'create'])->name('create');
        Route::post('/', [UserCrudController::class, 'store'])->name('store');
        Route::post('/{user}/resend-verification-email', [UserCrudController::class, 'resendVerification'])->name('resend-verification');
        Route::get('/{user}/edit', [UserCrudController::class, 'edit'])->name('edit');
        Route::get('/{user}', [UserCrudController::class, 'show'])->whereNumber('user')->name('show');
        Route::put('/{user}', [UserCrudController::class, 'update'])->name('update');
        Route::delete('/{user}', [UserCrudController::class, 'destroy'])->name('destroy');
    });

Route::prefix('panel/classes')
    ->name('panel.classes.')
    ->middleware(['auth', 'verified', 'role:super-admin,admin', 'admin.ip', 'can:web-manage-classes'])
    ->group(function (): void {
        Route::get('/', [ClassCrudController::class, 'index'])->name('index');
        Route::get('/create', [ClassCrudController::class, 'create'])->name('create');
        Route::post('/', [ClassCrudController::class, 'store'])->name('store');
        Route::get('/{schoolClass}', [ClassCrudController::class, 'show'])->whereNumber('schoolClass')->name('show');
        Route::post('/{schoolClass}/students', [ClassCrudController::class, 'syncStudents'])->whereNumber('schoolClass')->name('sync-students');
        Route::get('/{schoolClass}/edit', [ClassCrudController::class, 'edit'])->name('edit');
        Route::put('/{schoolClass}', [ClassCrudController::class, 'update'])->name('update');
        Route::delete('/{schoolClass}', [ClassCrudController::class, 'destroy'])->name('destroy');
    });

Route::prefix('panel/subjects')
    ->name('panel.subjects.')
    ->middleware(['auth', 'verified', 'role:super-admin,admin', 'admin.ip', 'can:web-manage-subjects'])
    ->group(function (): void {
        Route::get('/', [SubjectCrudController::class, 'index'])->name('index');
        Route::post('/install-khmer-core', [SubjectCrudController::class, 'installKhmerCore'])->name('install-khmer-core');
        Route::get('/create', [SubjectCrudController::class, 'create'])->name('create');
        Route::post('/', [SubjectCrudController::class, 'store'])->name('store');
        Route::get('/{subject}/edit', [SubjectCrudController::class, 'edit'])->name('edit');
        Route::put('/{subject}', [SubjectCrudController::class, 'update'])->name('update');
        Route::delete('/{subject}', [SubjectCrudController::class, 'destroy'])->name('destroy');
    });

Route::prefix('panel/timetables')
    ->name('panel.timetables.')
    ->middleware(['auth', 'verified', 'role:super-admin,admin,teacher', 'admin.ip', 'can:web-manage-timetables'])
    ->group(function (): void {
        Route::get('/', [TimetableCrudController::class, 'index'])->name('index');
        Route::get('/create', [TimetableCrudController::class, 'create'])->name('create');
        Route::post('/', [TimetableCrudController::class, 'store'])->name('store');
        Route::get('/{timetable}/edit', [TimetableCrudController::class, 'edit'])->name('edit');
        Route::put('/{timetable}', [TimetableCrudController::class, 'update'])->name('update');
        Route::delete('/{timetable}', [TimetableCrudController::class, 'destroy'])->name('destroy');
    });

Route::prefix('panel/students')
    ->name('panel.students.')
    ->middleware(['auth', 'verified', 'role:super-admin,admin', 'admin.ip', 'can:web-manage-students'])
    ->group(function (): void {
        Route::get('/', [StudentCrudController::class, 'index'])->name('index');
        Route::post('/import-csv', [StudentCrudController::class, 'importCsv'])->name('import-csv');
        Route::get('/create', [StudentCrudController::class, 'create'])->name('create');
        Route::post('/', [StudentCrudController::class, 'store'])->name('store');
        Route::get('/{student}/edit', [StudentCrudController::class, 'edit'])->name('edit');
        Route::get('/{student}', [StudentCrudController::class, 'show'])->whereNumber('student')->name('show');
        Route::put('/{student}', [StudentCrudController::class, 'update'])->name('update');
        Route::delete('/{student}', [StudentCrudController::class, 'destroy'])->name('destroy');
    });

Route::prefix('panel/attendance')
    ->name('panel.attendance.')
    ->middleware(['auth', 'verified', 'role:super-admin,admin,teacher', 'admin.ip', 'can:web-manage-attendance'])
    ->group(function (): void {
        Route::get('/', [AttendanceCrudController::class, 'index'])->name('index');
        Route::get('/create', [AttendanceCrudController::class, 'create'])->name('create');
        Route::post('/', [AttendanceCrudController::class, 'store'])->name('store');
        Route::get('/{attendance}/edit', [AttendanceCrudController::class, 'edit'])->name('edit');
        Route::put('/{attendance}', [AttendanceCrudController::class, 'update'])->name('update');
        Route::delete('/{attendance}', [AttendanceCrudController::class, 'destroy'])->name('destroy');
    });

Route::prefix('panel/homeworks')
    ->name('panel.homeworks.')
    ->middleware(['auth', 'verified', 'role:teacher', 'admin.ip', 'can:web-manage-homeworks'])
    ->group(function (): void {
        Route::get('/', [HomeworkCrudController::class, 'index'])->name('index');
        Route::get('/create', [HomeworkCrudController::class, 'create'])->name('create');
        Route::post('/', [HomeworkCrudController::class, 'store'])->name('store');
        Route::get('/{homework}/edit', [HomeworkCrudController::class, 'edit'])->name('edit');
        Route::put('/{homework}', [HomeworkCrudController::class, 'update'])->name('update');
        Route::delete('/{homework}', [HomeworkCrudController::class, 'destroy'])->name('destroy');
    });

Route::prefix('panel/media')
    ->name('panel.media.')
    ->middleware(['auth', 'verified', 'role:super-admin,admin,teacher', 'admin.ip'])
    ->group(function (): void {
        Route::get('/', [MediaCrudController::class, 'index'])->name('index');
        Route::delete('/{media}', [MediaCrudController::class, 'destroy'])->name('destroy');
    });

Route::prefix('panel/scores')
    ->name('panel.scores.')
    ->middleware(['auth', 'verified', 'role:super-admin,admin,teacher', 'admin.ip', 'can:web-manage-scores'])
    ->group(function (): void {
        Route::get('/', [ScoreCrudController::class, 'index'])->name('index');
        Route::get('/create', [ScoreCrudController::class, 'create'])->name('create');
        Route::post('/', [ScoreCrudController::class, 'store'])->name('store');
        Route::get('/{score}/edit', [ScoreCrudController::class, 'edit'])->name('edit');
        Route::put('/{score}', [ScoreCrudController::class, 'update'])->name('update');
        Route::delete('/{score}', [ScoreCrudController::class, 'destroy'])->name('destroy');
    });

Route::prefix('panel/leave-requests')
    ->name('panel.leave-requests.')
    ->middleware(['auth', 'verified', 'role:super-admin,admin,teacher,student,parent', 'admin.ip', 'can:web-view-leave-requests'])
    ->group(function (): void {
        Route::get('/', [LeaveRequestCrudController::class, 'index'])->name('index');
        Route::get('/{leaveRequest}/edit', [LeaveRequestCrudController::class, 'edit'])->name('edit');
        Route::put('/{leaveRequest}', [LeaveRequestCrudController::class, 'update'])->name('update');
        Route::delete('/{leaveRequest}', [LeaveRequestCrudController::class, 'destroy'])->name('destroy');
    });

Route::prefix('panel/leave-requests')
    ->name('panel.leave-requests.')
    ->middleware(['auth', 'verified', 'role:student,parent', 'admin.ip', 'can:web-create-leave-requests'])
    ->group(function (): void {
        Route::get('/create', [LeaveRequestCrudController::class, 'create'])->name('create');
        Route::post('/', [LeaveRequestCrudController::class, 'store'])->name('store');
    });

Route::prefix('panel/messages')
    ->name('panel.messages.')
    ->middleware(['auth', 'verified', 'role:super-admin,admin,teacher,student,parent', 'admin.ip', 'can:web-view-messages'])
    ->group(function (): void {
        Route::get('/', [MessageCrudController::class, 'index'])->name('index');
        Route::get('/{message}', [MessageCrudController::class, 'show'])->whereNumber('message')->name('show');
    });

Route::prefix('panel/messages')
    ->name('panel.messages.')
    ->middleware(['auth', 'verified', 'role:super-admin,admin,teacher,student', 'admin.ip', 'can:web-create-messages'])
    ->group(function (): void {
        Route::get('/create', [MessageCrudController::class, 'create'])->name('create');
        Route::post('/', [MessageCrudController::class, 'store'])->name('store');
    });

Route::prefix('panel/notifications')
    ->name('panel.notifications.')
    ->middleware(['auth', 'verified', 'role:super-admin,admin,teacher', 'admin.ip', 'can:web-manage-notifications'])
    ->group(function (): void {
        Route::get('/', [NotificationCrudController::class, 'index'])->name('index');
        Route::post('/broadcast', [NotificationCrudController::class, 'broadcast'])->name('broadcast');
        Route::get('/create', [NotificationCrudController::class, 'create'])->name('create');
        Route::post('/', [NotificationCrudController::class, 'store'])->name('store');
        Route::get('/{notification}/edit', [NotificationCrudController::class, 'edit'])->name('edit');
        Route::put('/{notification}', [NotificationCrudController::class, 'update'])->name('update');
        Route::delete('/{notification}', [NotificationCrudController::class, 'destroy'])->name('destroy');
    });

Route::prefix('panel/incident-reports')
    ->name('panel.incident-reports.')
    ->middleware(['auth', 'verified', 'role:super-admin,admin,teacher', 'admin.ip', 'can:web-manage-incident-reports'])
    ->group(function (): void {
        Route::get('/', [IncidentReportCrudController::class, 'index'])->name('index');
        Route::get('/create', [IncidentReportCrudController::class, 'create'])->name('create');
        Route::post('/', [IncidentReportCrudController::class, 'store'])->name('store');
        Route::get('/{incidentReport}/edit', [IncidentReportCrudController::class, 'edit'])->name('edit');
        Route::put('/{incidentReport}', [IncidentReportCrudController::class, 'update'])->name('update');
        Route::delete('/{incidentReport}', [IncidentReportCrudController::class, 'destroy'])->name('destroy');
    });

Route::prefix('panel/audit-logs')
    ->name('panel.audit-logs.')
    ->middleware(['auth', 'verified', 'role:super-admin,admin', 'admin.ip', 'can:web-view-audit-logs'])
    ->group(function (): void {
        Route::get('/', [AuditLogCrudController::class, 'index'])->name('index');
    });
