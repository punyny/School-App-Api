<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Homework;
use App\Models\IncidentReport;
use App\Models\LeaveRequest;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Score;
use App\Models\Student;
use App\Models\Timetable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherController extends Controller
{
    public function dashboard(Request $request): View
    {
        $teacher = $request->user();
        $classIds = $teacher->teachingClasses()->pluck('classes.id')->all();
        $scopedClassIds = $classIds === [] ? [-1] : $classIds;

        $studentCount = Student::query()->whereIn('class_id', $scopedClassIds)->count();
        $pendingLeaveCount = LeaveRequest::query()
            ->where('status', 'pending')
            ->whereHas('student', fn ($query) => $query->whereIn('class_id', $scopedClassIds))
            ->count();
        $attendanceCount = Attendance::query()->whereIn('class_id', $scopedClassIds)->count();
        $homeworkCount = Homework::query()->whereIn('class_id', $scopedClassIds)->count();
        $messageCount = Message::query()
            ->where(fn ($query) => $query
                ->where('sender_id', $teacher->id)
                ->orWhere('receiver_id', $teacher->id)
                ->orWhereIn('class_id', $scopedClassIds))
            ->count();
        $unreadNotificationCount = Notification::query()
            ->where('user_id', $teacher->id)
            ->where('read_status', false)
            ->count();
        $incidentCount = IncidentReport::query()
            ->whereHas('student', fn ($query) => $query->whereIn('class_id', $scopedClassIds))
            ->count();
        $timetableCount = Timetable::query()
            ->where(function ($query) use ($teacher, $scopedClassIds): void {
                $query->where('teacher_id', $teacher->id)
                    ->orWhereIn('class_id', $scopedClassIds);
            })
            ->count();

        return $this->renderPage(
            title: 'Teacher Dashboard',
            subtitle: 'Daily class operations overview and teaching tools.',
            stats: [
                ['label' => 'Assigned Classes', 'value' => count($classIds)],
                ['label' => 'Students In My Classes', 'value' => $studentCount],
                ['label' => 'Pending Leave Requests', 'value' => $pendingLeaveCount],
                ['label' => 'Unread Notifications', 'value' => $unreadNotificationCount],
            ],
            tableTitle: 'Assigned Classes',
            columns: ['Class', 'Grade'],
            rows: $teacher->teachingClasses()
                ->select('classes.name', 'classes.grade_level')
                ->distinct()
                ->orderBy('classes.name')
                ->get()
                ->map(fn ($class) => [
                    $class->name,
                    $class->grade_level ?: '-',
                ])->all(),
            teacherDashboardModules: [
                [
                    'number' => 1,
                    'title' => 'View Timetable',
                    'description' => 'Check your class and subject schedule by day and period.',
                    'metric_label' => 'Timetable Rows',
                    'metric_value' => $timetableCount,
                    'links' => [
                        ['label' => 'Open Timetable', 'url' => route('panel.timetables.index')],
                    ],
                ],
                [
                    'number' => 2,
                    'title' => 'Approve or Reject Attendance Requests',
                    'description' => 'Review leave/attendance requests sent by students or parents.',
                    'metric_label' => 'Pending Requests',
                    'metric_value' => $pendingLeaveCount,
                    'links' => [
                        ['label' => 'Pending Requests', 'url' => route('panel.leave-requests.index', ['status' => 'pending'])],
                        ['label' => 'All Requests', 'url' => route('panel.leave-requests.index')],
                    ],
                ],
                [
                    'number' => 3,
                    'title' => 'Attendance Tracking (My Classes)',
                    'description' => 'Track attendance by class and subject for your assigned students.',
                    'metric_label' => 'Attendance Records',
                    'metric_value' => $attendanceCount,
                    'links' => [
                        ['label' => 'Attendance View', 'url' => route('teacher.attendance.index')],
                        ['label' => 'Mark Attendance', 'url' => route('panel.attendance.create')],
                    ],
                ],
                [
                    'number' => 4,
                    'title' => 'Homework To My Class',
                    'description' => 'Create homework and monitor assignment volume for your classes.',
                    'metric_label' => 'Homework Items',
                    'metric_value' => $homeworkCount,
                    'links' => [
                        ['label' => 'Homework View', 'url' => route('teacher.homeworks.index')],
                        ['label' => 'Assign Homework', 'url' => route('panel.homeworks.create')],
                    ],
                ],
                [
                    'number' => 5,
                    'title' => 'View Messages From Student or Parent',
                    'description' => 'Read incoming communication and reply directly.',
                    'metric_label' => 'Message Threads',
                    'metric_value' => $messageCount,
                    'links' => [
                        ['label' => 'Open Messages', 'url' => route('teacher.messages.index')],
                        ['label' => 'Send Message', 'url' => route('panel.messages.create')],
                    ],
                ],
                [
                    'number' => 6,
                    'title' => 'Notifications (View & Send)',
                    'description' => 'Send notices such as "Today we have no class" to students/parents.',
                    'metric_label' => 'Unread Notifications',
                    'metric_value' => $unreadNotificationCount,
                    'links' => [
                        ['label' => 'Notification Inbox', 'url' => route('panel.notifications.index')],
                        ['label' => 'Create Notification', 'url' => route('panel.notifications.create')],
                    ],
                ],
                [
                    'number' => 7,
                    'title' => 'Incident Report To Parent',
                    'description' => 'Record behavior incidents and share reports with families.',
                    'metric_label' => 'Incident Reports',
                    'metric_value' => $incidentCount,
                    'links' => [
                        ['label' => 'Incident List', 'url' => route('teacher.incidents.index')],
                        ['label' => 'Create Report', 'url' => route('panel.incident-reports.create')],
                    ],
                ],
            ]
        );
    }

    public function attendance(Request $request): View
    {
        $classIds = $request->user()->teachingClasses()->pluck('classes.id')->all();

        return $this->renderPage(
            title: 'Attendance',
            subtitle: 'Latest attendance records for your classes.',
            stats: [
                ['label' => 'Records', 'value' => Attendance::query()->whereIn('class_id', $classIds === [] ? [-1] : $classIds)->count()],
            ],
            tableTitle: 'Recent Attendance',
            columns: ['Date', 'Class', 'Student ID', 'Status'],
            rows: Attendance::query()
                ->whereIn('class_id', $classIds === [] ? [-1] : $classIds)
                ->latest('date')
                ->limit(20)
                ->get()
                ->map(fn (Attendance $attendance) => [
                    (string) $attendance->date,
                    (string) $attendance->class_id,
                    (string) $attendance->student_id,
                    $attendance->status,
                ])->all()
        );
    }

    public function homeworks(Request $request): View
    {
        $classIds = $request->user()->teachingClasses()->pluck('classes.id')->all();

        return $this->renderPage(
            title: 'Homeworks',
            subtitle: 'Assignments created for your classes.',
            stats: [
                ['label' => 'Total Homeworks', 'value' => Homework::query()->whereIn('class_id', $classIds === [] ? [-1] : $classIds)->count()],
            ],
            tableTitle: 'Recent Homeworks',
            columns: ['Title', 'Class ID', 'Subject ID', 'Due Date', 'Due Time'],
            rows: Homework::query()
                ->whereIn('class_id', $classIds === [] ? [-1] : $classIds)
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(fn (Homework $homework) => [
                    $homework->title,
                    (string) $homework->class_id,
                    (string) $homework->subject_id,
                    (string) $homework->due_date,
                    $homework->due_time ? substr((string) $homework->due_time, 0, 5) : '-',
                ])->all()
        );
    }

    public function scores(Request $request): View
    {
        $classIds = $request->user()->teachingClasses()->pluck('classes.id')->all();

        return $this->renderPage(
            title: 'Scores',
            subtitle: 'Recent score entries in your classes.',
            stats: [
                ['label' => 'Score Rows', 'value' => Score::query()->whereIn('class_id', $classIds === [] ? [-1] : $classIds)->count()],
            ],
            tableTitle: 'Latest Scores',
            columns: ['Student ID', 'Class ID', 'Total Score', 'Grade'],
            rows: Score::query()
                ->whereIn('class_id', $classIds === [] ? [-1] : $classIds)
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(fn (Score $score) => [
                    (string) $score->student_id,
                    (string) $score->class_id,
                    (string) $score->total_score,
                    $score->grade ?: '-',
                ])->all()
        );
    }

    public function messages(Request $request): View
    {
        $teacher = $request->user();
        $classIds = $teacher->teachingClasses()->pluck('classes.id')->all();

        return $this->renderPage(
            title: 'Messages',
            subtitle: 'Class and direct messages involving you.',
            stats: [
                ['label' => 'Messages', 'value' => Message::query()->where(fn ($query) => $query->where('sender_id', $teacher->id)->orWhere('receiver_id', $teacher->id)->orWhereIn('class_id', $classIds === [] ? [-1] : $classIds))->count()],
            ],
            tableTitle: 'Recent Messages',
            columns: ['Date', 'Sender', 'Receiver', 'Class', 'Content'],
            rows: Message::query()
                ->where(function ($query) use ($teacher, $classIds): void {
                    $query->where('sender_id', $teacher->id)
                        ->orWhere('receiver_id', $teacher->id)
                        ->orWhereIn('class_id', $classIds === [] ? [-1] : $classIds);
                })
                ->latest('date')
                ->limit(20)
                ->get()
                ->map(fn (Message $message) => [
                    (string) $message->date,
                    (string) $message->sender_id,
                    (string) ($message->receiver_id ?? '-'),
                    (string) ($message->class_id ?? '-'),
                    mb_strimwidth($message->content, 0, 50, '...'),
                ])->all()
        );
    }

    public function incidents(Request $request): View
    {
        $classIds = $request->user()->teachingClasses()->pluck('classes.id')->all();

        return $this->renderPage(
            title: 'Incident Reports',
            subtitle: 'Student incidents for your assigned classes.',
            stats: [
                ['label' => 'Incident Count', 'value' => IncidentReport::query()->whereHas('student', fn ($query) => $query->whereIn('class_id', $classIds === [] ? [-1] : $classIds))->count()],
            ],
            tableTitle: 'Recent Incidents',
            columns: ['Date', 'Student ID', 'Type', 'Acknowledged'],
            rows: IncidentReport::query()
                ->whereHas('student', fn ($query) => $query->whereIn('class_id', $classIds === [] ? [-1] : $classIds))
                ->latest('date')
                ->limit(20)
                ->get()
                ->map(fn (IncidentReport $incident) => [
                    (string) $incident->date,
                    (string) $incident->student_id,
                    $incident->type ?: '-',
                    $incident->acknowledged ? 'Yes' : 'No',
                ])->all()
        );
    }

    /**
     * @param  array<int, array{label: string, value: string|int}>  $stats
     * @param  array<int, string>  $columns
     * @param  array<int, array<int, string>>  $rows
     * @param  array<int, array{
     *     number:int,
     *     title:string,
     *     description:string,
     *     metric_label:string,
     *     metric_value:int,
     *     links:array<int, array{label:string,url:string}>
     * }>  $teacherDashboardModules
     */
    private function renderPage(
        string $title,
        string $subtitle,
        array $stats,
        string $tableTitle,
        array $columns,
        array $rows,
        array $teacherDashboardModules = []
    ): View {
        return view('web.panel', [
            'title' => $title,
            'subtitle' => $subtitle,
            'stats' => $stats,
            'tableTitle' => $tableTitle,
            'columns' => $columns,
            'rows' => $rows,
            'panel' => 'teacher',
            'teacherDashboardModules' => $teacherDashboardModules,
        ]);
    }
}
