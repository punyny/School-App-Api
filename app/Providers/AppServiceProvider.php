<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Homework;
use App\Models\IncidentReport;
use App\Models\LeaveRequest;
use App\Models\Message;
use App\Models\Notification;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Score;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Timetable;
use App\Models\User;
use App\Policies\AnnouncementPolicy;
use App\Policies\AttendancePolicy;
use App\Policies\HomeworkPolicy;
use App\Policies\IncidentReportPolicy;
use App\Policies\LeaveRequestPolicy;
use App\Policies\MessagePolicy;
use App\Policies\NotificationPolicy;
use App\Policies\SchoolClassPolicy;
use App\Policies\SchoolPolicy;
use App\Policies\ScorePolicy;
use App\Policies\StudentPolicy;
use App\Policies\SubjectPolicy;
use App\Policies\TimetablePolicy;
use App\Policies\UserPolicy;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        VerifyEmail::createUrlUsing(function (User $notifiable): string {
            $rootUrl = rtrim((string) config('app.url'), '/');

            $relativeUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes((int) config('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1((string) $notifiable->getEmailForVerification()),
                ],
                false
            );

            if ($rootUrl === '') {
                return url($relativeUrl);
            }

            return $rootUrl.$relativeUrl;
        });

        $roleOf = static fn (User $user): string => $user->normalizedRole();
        $isRole = static fn (User $user, string $role): bool => $roleOf($user) === $role;
        $inRoles = static fn (User $user, array $roles): bool => in_array($roleOf($user), $roles, true);

        Gate::before(fn (User $user): ?bool => $isRole($user, 'super-admin') ? true : null);

        Gate::policy(Attendance::class, AttendancePolicy::class);
        Gate::policy(Homework::class, HomeworkPolicy::class);
        Gate::policy(Score::class, ScorePolicy::class);
        Gate::policy(LeaveRequest::class, LeaveRequestPolicy::class);
        Gate::policy(Announcement::class, AnnouncementPolicy::class);
        Gate::policy(Notification::class, NotificationPolicy::class);
        Gate::policy(Message::class, MessagePolicy::class);
        Gate::policy(IncidentReport::class, IncidentReportPolicy::class);
        Gate::policy(School::class, SchoolPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(SchoolClass::class, SchoolClassPolicy::class);
        Gate::policy(Subject::class, SubjectPolicy::class);
        Gate::policy(Timetable::class, TimetablePolicy::class);
        Gate::policy(Student::class, StudentPolicy::class);

        Gate::define('manage-schools', fn (User $user): bool => $isRole($user, 'super-admin'));
        Gate::define(
            'manage-school-users',
            fn (User $user): bool => $inRoles($user, ['super-admin', 'admin'])
        );

        Gate::define('web-manage-schools', fn (User $user): bool => $isRole($user, 'super-admin'));
        Gate::define('web-manage-users', fn (User $user): bool => $inRoles($user, ['super-admin', 'admin']));
        Gate::define('web-manage-classes', fn (User $user): bool => $inRoles($user, ['super-admin', 'admin']));
        Gate::define('web-manage-subjects', fn (User $user): bool => $inRoles($user, ['super-admin', 'admin']));
        Gate::define('web-manage-timetables', fn (User $user): bool => $inRoles($user, ['super-admin', 'admin', 'teacher']));
        Gate::define('web-manage-students', fn (User $user): bool => $inRoles($user, ['super-admin', 'admin']));
        Gate::define('web-manage-attendance', fn (User $user): bool => $inRoles($user, ['super-admin', 'admin', 'teacher']));
        Gate::define('web-manage-homeworks', fn (User $user): bool => $isRole($user, 'teacher'));
        Gate::define('web-manage-scores', fn (User $user): bool => $inRoles($user, ['super-admin', 'admin', 'teacher']));
        Gate::define('web-view-leave-requests', fn (User $user): bool => $inRoles($user, ['super-admin', 'admin', 'teacher', 'student', 'parent']));
        Gate::define('web-create-leave-requests', fn (User $user): bool => $inRoles($user, ['student', 'parent']));
        Gate::define('web-approve-leave-requests', fn (User $user): bool => $inRoles($user, ['super-admin', 'admin', 'teacher']));
        Gate::define('web-view-messages', fn (User $user): bool => $inRoles($user, ['super-admin', 'admin', 'teacher', 'student', 'parent']));
        Gate::define('web-create-messages', fn (User $user): bool => $inRoles($user, ['super-admin', 'admin', 'teacher', 'student']));
        Gate::define('web-manage-messages', fn (User $user): bool => $inRoles($user, ['super-admin', 'admin', 'teacher']));
        Gate::define('web-manage-notifications', fn (User $user): bool => $inRoles($user, ['super-admin', 'admin', 'teacher']));
        Gate::define('web-manage-incident-reports', fn (User $user): bool => $inRoles($user, ['super-admin', 'admin', 'teacher']));
        Gate::define('web-view-announcements', fn (User $user): bool => $inRoles($user, ['super-admin', 'admin', 'teacher', 'student', 'parent']));
        Gate::define('web-manage-announcements', fn (User $user): bool => $inRoles($user, ['super-admin', 'admin']));
        Gate::define('web-view-audit-logs', fn (User $user): bool => $inRoles($user, ['super-admin', 'admin']));
    }
}
