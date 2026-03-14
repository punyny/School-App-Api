<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Homework;
use App\Models\HomeworkStatus;
use App\Models\IncidentReport;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\SchoolClass;
use App\Models\Score;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Support\AcademicScoreSummary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardSummaryController extends Controller
{
    public function __construct(private readonly AcademicScoreSummary $scoreSummary)
    {
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
        ]);

        if ($user->role === 'student') {
            return response()->json([
                'data' => $this->buildStudentSummary($user, $user->studentProfile?->id),
            ]);
        }

        if ($user->role === 'parent') {
            $studentId = isset($filters['student_id']) ? (int) $filters['student_id'] : null;

            return response()->json([
                'data' => $this->buildParentSummary($user, $studentId),
            ]);
        }

        return response()->json([
            'data' => $this->buildManagementSummary($user, $filters['school_id'] ?? null),
        ]);
    }

    private function buildStudentSummary(User $user, ?int $studentId): array
    {
        $targetStudentId = (int) ($studentId ?? 0);
        if ($targetStudentId <= 0) {
            return [
                'role' => 'student',
                'attendance' => ['P' => 0, 'A' => 0, 'L' => 0],
                'homework' => ['done' => 0, 'not_done' => 0, 'overdue' => 0],
                'gpa' => '0.00',
                'average_score' => '0.00',
                'incidents' => ['acknowledged' => 0, 'unacknowledged' => 0],
                'notifications_unread' => 0,
            ];
        }

        $attendance = [
            'P' => Attendance::query()->where('student_id', $targetStudentId)->where('status', 'P')->count(),
            'A' => Attendance::query()->where('student_id', $targetStudentId)->where('status', 'A')->count(),
            'L' => Attendance::query()->where('student_id', $targetStudentId)->where('status', 'L')->count(),
        ];

        $homework = $this->homeworkSummaryForStudent($targetStudentId);
        $gpa = $this->gpaSummary($targetStudentId);

        return [
            'role' => 'student',
            'attendance' => $attendance,
            'homework' => $homework,
            'gpa' => $gpa['gpa'],
            'average_score' => $gpa['average_score'],
            'incidents' => [
                'acknowledged' => IncidentReport::query()
                    ->where('student_id', $targetStudentId)
                    ->where('acknowledged', true)
                    ->count(),
                'unacknowledged' => IncidentReport::query()
                    ->where('student_id', $targetStudentId)
                    ->where('acknowledged', false)
                    ->count(),
            ],
            'notifications_unread' => Notification::query()
                ->where('user_id', $user->id)
                ->where('read_status', false)
                ->count(),
        ];
    }

    private function buildParentSummary(User $user, ?int $requestedStudentId): array
    {
        $children = $user->children()->pluck('students.id')->all();
        $studentId = $requestedStudentId && in_array($requestedStudentId, $children, true)
            ? $requestedStudentId
            : (int) ($children[0] ?? 0);

        $summary = $this->buildStudentSummary($user, $studentId > 0 ? $studentId : null);
        $summary['role'] = 'parent';
        $summary['selected_student_id'] = $studentId > 0 ? $studentId : null;
        $summary['children_count'] = count($children);

        return $summary;
    }

    private function buildManagementSummary(User $user, mixed $requestedSchoolId): array
    {
        $schoolId = $this->resolveSchoolScope($user, $requestedSchoolId);
        $today = now()->toDateString();

        $classQuery = SchoolClass::query();
        $studentQuery = Student::query();
        $subjectQuery = Subject::query();
        $attendanceTodayQuery = Attendance::query()->whereDate('date', $today);
        $incidentQuery = IncidentReport::query();
        $leavePendingQuery = LeaveRequest::query()->where('status', 'Pending');
        $homeworkQuery = Homework::query()->whereDate('due_date', '>=', $today);

        if ($user->role === 'teacher') {
            $classIds = $user->teachingClasses()->pluck('classes.id')->all();
            $subjectIds = DB::table('teacher_class')
                ->where('teacher_id', $user->id)
                ->pluck('subject_id')
                ->all();
            $classIds = $classIds === [] ? [-1] : $classIds;
            $classQuery->whereIn('id', $classIds);
            $studentQuery->whereIn('class_id', $classIds);
            $subjectQuery->whereIn('id', $subjectIds === [] ? [-1] : $subjectIds);
            $attendanceTodayQuery->whereIn('class_id', $classIds);
            $homeworkQuery->whereIn('class_id', $classIds);
            $incidentQuery->whereIn('student_id', Student::query()->whereIn('class_id', $classIds)->pluck('id')->all() ?: [-1]);
            $leavePendingQuery->whereIn('student_id', Student::query()->whereIn('class_id', $classIds)->pluck('id')->all() ?: [-1]);
        } elseif ($schoolId) {
            $classQuery->where('school_id', $schoolId);
            $studentQuery->whereHas('user', fn (Builder $q) => $q->where('school_id', $schoolId));
            $subjectQuery->where('school_id', $schoolId);
            $classIds = SchoolClass::query()->where('school_id', $schoolId)->pluck('id')->all();
            $studentIds = Student::query()
                ->whereHas('user', fn (Builder $q) => $q->where('school_id', $schoolId))
                ->pluck('id')
                ->all();
            $attendanceTodayQuery->whereIn('class_id', $classIds === [] ? [-1] : $classIds);
            $homeworkQuery->whereIn('class_id', $classIds === [] ? [-1] : $classIds);
            $incidentQuery->whereIn('student_id', $studentIds === [] ? [-1] : $studentIds);
            $leavePendingQuery->whereIn('student_id', $studentIds === [] ? [-1] : $studentIds);
        }

        return [
            'role' => $user->role,
            'school_id' => $schoolId,
            'classes_total' => $classQuery->count(),
            'students_total' => $studentQuery->count(),
            'subjects_total' => $subjectQuery->count(),
            'attendance_today' => [
                'P' => (clone $attendanceTodayQuery)->where('status', 'P')->count(),
                'A' => (clone $attendanceTodayQuery)->where('status', 'A')->count(),
                'L' => (clone $attendanceTodayQuery)->where('status', 'L')->count(),
            ],
            'pending_leave_requests' => $leavePendingQuery->count(),
            'upcoming_homeworks' => $homeworkQuery->count(),
            'incidents_unacknowledged' => (clone $incidentQuery)->where('acknowledged', false)->count(),
        ];
    }

    private function homeworkSummaryForStudent(int $studentId): array
    {
        $student = Student::query()->find($studentId);
        if (! $student) {
            return ['done' => 0, 'not_done' => 0, 'overdue' => 0];
        }

        $homeworks = Homework::query()->where('class_id', $student->class_id)->get(['id', 'due_date']);
        $statuses = HomeworkStatus::query()
            ->whereIn('homework_id', $homeworks->pluck('id')->all())
            ->where('student_id', $studentId)
            ->get()
            ->keyBy('homework_id');

        $done = 0;
        $notDone = 0;
        $overdue = 0;

        foreach ($homeworks as $homework) {
            $status = $statuses->get($homework->id)?->status;
            if ($status === 'Done') {
                $done++;

                continue;
            }

            $dueDate = $homework->due_date?->toDateString();
            if ($dueDate && $dueDate < now()->toDateString()) {
                $overdue++;

                continue;
            }

            $notDone++;
        }

        return [
            'done' => $done,
            'not_done' => $notDone,
            'overdue' => $overdue,
        ];
    }

    private function gpaSummary(int $studentId): array
    {
        $student = Student::query()->find($studentId);
        if (! $student) {
            return ['gpa' => '0.00', 'average_score' => '0.00'];
        }

        $summary = $this->scoreSummary->summarizeModelScores(
            Score::query()->where('student_id', $studentId)->get(),
            (int) ($student->class_id ?? 0)
        );

        return [
            'gpa' => $summary['gpa'],
            'average_score' => $summary['average_score'],
        ];
    }

    private function resolveSchoolScope(User $user, mixed $requestedSchoolId): ?int
    {
        if ($user->role === 'super-admin') {
            return $requestedSchoolId ? (int) $requestedSchoolId : null;
        }

        return $user->school_id ? (int) $user->school_id : null;
    }
}
