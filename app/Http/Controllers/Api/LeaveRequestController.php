<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LeaveRequestStatusUpdateRequest;
use App\Http\Requests\Api\LeaveRequestStoreRequest;
use App\Http\Requests\Api\LeaveRequestUpdateRequest;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Notification as UserNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $filters = $request->validate([
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'status' => ['nullable', 'in:pending,approved,rejected'],
            'start_date_from' => ['nullable', 'date'],
            'start_date_to' => ['nullable', 'date', 'after_or_equal:start_date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = LeaveRequest::query()
            ->with(['student.user', 'student.class', 'subject', 'submitter', 'approver', 'recipients'])
            ->orderByDesc('start_date')
            ->orderByDesc('created_at');

        $this->applyVisibilityScope($query, $request->user());

        if (isset($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (isset($filters['subject_id'])) {
            $subjectId = (int) $filters['subject_id'];
            $query->where(function (Builder $subjectScope) use ($subjectId): void {
                $subjectScope->where('subject_id', $subjectId)
                    ->orWhere('subject_ids', 'like', '%"'.$subjectId.'"%');
            });
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['start_date_from'])) {
            $query->whereDate('start_date', '>=', $filters['start_date_from']);
        }

        if (isset($filters['start_date_to'])) {
            $query->whereDate('start_date', '<=', $filters['start_date_to']);
        }

        return response()->json($query->paginate($filters['per_page'] ?? 20));
    }

    public function show(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('view', $leaveRequest);

        return response()->json([
            'data' => $leaveRequest->load(['student.user', 'student.class', 'subject', 'submitter', 'approver', 'recipients']),
        ]);
    }

    public function store(LeaveRequestStoreRequest $request): JsonResponse
    {
        $this->authorize('create', LeaveRequest::class);

        $user = $request->user();
        $payload = $request->validated();
        $studentId = $this->resolveStudentForSubmit($user, $payload['student_id'] ?? null);

        $this->ensureUserCanSubmitForStudent($user, $studentId);
        $prepared = $this->prepareLeavePayload($studentId, $payload);

        $leaveRequest = DB::transaction(function () use ($user, $prepared): LeaveRequest {
            $record = LeaveRequest::query()->create([
                'student_id' => $prepared['student']->id,
                'subject_id' => $prepared['subject_id'],
                'subject_ids' => $prepared['subject_ids'],
                'request_type' => $prepared['request_type'],
                'start_date' => $prepared['start_date'],
                'end_date' => $prepared['end_date'],
                'start_time' => $prepared['start_time'],
                'end_time' => $prepared['end_time'],
                'return_date' => $prepared['return_date'],
                'total_days' => $prepared['total_days'],
                'reason' => $prepared['reason'],
                'status' => 'pending',
                'submitted_by' => $user->id,
            ]);

            $this->syncRecipients($record, $prepared['student'], $prepared['subject_ids']);
            $this->notifyRecipients($record);

            return $record;
        });

        return response()->json([
            'message' => 'Leave request submitted.',
            'data' => $leaveRequest->load(['student.user', 'student.class', 'subject', 'submitter', 'approver', 'recipients']),
        ], 201);
    }

    public function update(LeaveRequestUpdateRequest $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('update', $leaveRequest);

        $payload = $request->validated();
        $mergedPayload = array_merge($leaveRequest->only([
            'subject_id',
            'subject_ids',
            'request_type',
            'start_date',
            'end_date',
            'start_time',
            'end_time',
            'return_date',
            'total_days',
            'reason',
        ]), $payload);

        $prepared = $this->prepareLeavePayload((int) $leaveRequest->student_id, $mergedPayload);

        DB::transaction(function () use ($leaveRequest, $prepared): void {
            $leaveRequest->fill([
                'subject_id' => $prepared['subject_id'],
                'subject_ids' => $prepared['subject_ids'],
                'request_type' => $prepared['request_type'],
                'start_date' => $prepared['start_date'],
                'end_date' => $prepared['end_date'],
                'start_time' => $prepared['start_time'],
                'end_time' => $prepared['end_time'],
                'return_date' => $prepared['return_date'],
                'total_days' => $prepared['total_days'],
                'reason' => $prepared['reason'],
            ])->save();

            $this->syncRecipients($leaveRequest, $prepared['student'], $prepared['subject_ids']);
        });

        return response()->json([
            'message' => 'Leave request updated.',
            'data' => $leaveRequest->fresh()->load(['student.user', 'student.class', 'subject', 'submitter', 'approver', 'recipients']),
        ]);
    }

    public function updateStatus(LeaveRequestStatusUpdateRequest $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('updateStatus', $leaveRequest);

        $status = $request->validated()['status'];
        $user = $request->user();

        DB::transaction(function () use ($leaveRequest, $status, $user): void {
            $leaveRequest->status = $status;

            if ($status === 'approved') {
                $leaveRequest->approved_by = $user->id;
                $leaveRequest->approved_at = now();
            } else {
                $leaveRequest->approved_by = null;
                $leaveRequest->approved_at = null;
            }

            $leaveRequest->save();

            if ($status === 'approved') {
                $this->syncApprovedLeaveToAttendance($leaveRequest->fresh());
            }

            $this->notifySubmitterAndFamily($leaveRequest->fresh(), $status);
        });

        return response()->json([
            'message' => 'Leave request status updated.',
            'data' => $leaveRequest->fresh()->load(['student.user', 'student.class', 'subject', 'submitter', 'approver', 'recipients']),
        ]);
    }

    public function destroy(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('delete', $leaveRequest);

        $leaveRequest->delete();

        return response()->json([
            'message' => 'Leave request deleted.',
        ]);
    }

    private function resolveStudentForSubmit(User $user, ?int $requestedStudentId): int
    {
        if ($user->role === 'student') {
            $studentId = $user->studentProfile?->id;
            if (! $studentId) {
                throw ValidationException::withMessages([
                    'student_id' => ['Student profile not found for this user.'],
                ]);
            }

            return $studentId;
        }

        if ($user->role === 'parent') {
            if (! $requestedStudentId) {
                throw ValidationException::withMessages([
                    'student_id' => ['student_id is required for parent role.'],
                ]);
            }

            return $requestedStudentId;
        }

        throw ValidationException::withMessages([
            'role' => ['This role cannot submit leave request.'],
        ]);
    }

    private function ensureUserCanSubmitForStudent(User $user, int $studentId): void
    {
        if ($user->role === 'student') {
            $ownStudentId = $user->studentProfile?->id;
            if ((int) $ownStudentId !== $studentId) {
                throw ValidationException::withMessages([
                    'student_id' => ['You can only submit for yourself.'],
                ]);
            }

            return;
        }

        if ($user->role === 'parent') {
            $allowed = $user->children()->where('students.id', $studentId)->exists();
            if (! $allowed) {
                throw ValidationException::withMessages([
                    'student_id' => ['You can only submit for your own child.'],
                ]);
            }
        }
    }

    private function applyVisibilityScope(Builder $query, User $user): void
    {
        if ($user->role === 'super-admin') {
            return;
        }

        if ($user->role === 'student') {
            $studentId = $user->studentProfile?->id;
            $query->whereIn('student_id', $studentId ? [$studentId] : [-1]);

            return;
        }

        if ($user->role === 'parent') {
            $studentIds = $user->children()->pluck('students.id')->all();
            $query->whereIn('student_id', $studentIds === [] ? [-1] : $studentIds);

            return;
        }

        if ($user->role === 'teacher') {
            $query->whereHas('recipients', fn (Builder $recipientQuery) => $recipientQuery->where('users.id', $user->id));

            return;
        }

        if (! $user->school_id) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('student.class', fn (Builder $classQuery) => $classQuery->where('school_id', $user->school_id));
    }

    private function canViewLeaveRequest(User $user, LeaveRequest $leaveRequest): bool
    {
        $query = LeaveRequest::query()->whereKey($leaveRequest->id);
        $this->applyVisibilityScope($query, $user);

        return $query->exists();
    }

    private function canManageStudent(User $user, int $studentId): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        if ($user->role !== 'admin') {
            return false;
        }

        if (! $user->school_id) {
            return false;
        }

        $student = Student::query()->find($studentId);
        if (! $student) {
            return false;
        }

        $class = SchoolClass::query()->find($student->class_id);
        if (! $class || (int) $class->school_id !== (int) $user->school_id) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *   student: Student,
     *   subject_id: int|null,
     *   subject_ids: array<int, int>,
     *   request_type: string,
     *   start_date: string,
     *   end_date: string,
     *   start_time: string|null,
     *   end_time: string|null,
     *   return_date: string,
     *   total_days: int,
     *   reason: string
     * }
     */
    private function prepareLeavePayload(int $studentId, array $payload): array
    {
        $student = Student::query()->with('class')->find($studentId);
        if (! $student || ! $student->class_id || ! $student->class) {
            throw ValidationException::withMessages([
                'student_id' => ['Student or class assignment not found.'],
            ]);
        }

        $subjectIds = $this->normalizeSubjectIds($payload['subject_ids'] ?? [], $payload['subject_id'] ?? null);
        $this->ensureSubjectsBelongToClass($student, $subjectIds);

        $requestType = (string) ($payload['request_type'] ?? 'hourly');
        if (! in_array($requestType, ['hourly', 'multi_day'], true)) {
            $requestType = ! empty($payload['start_time']) ? 'hourly' : 'multi_day';
        }
        $startDate = Carbon::parse((string) $payload['start_date'])->toDateString();
        $reason = trim((string) ($payload['reason'] ?? ''));

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => ['Reason is required.'],
            ]);
        }

        if ($requestType === 'hourly') {
            $startTime = $this->normalizeTime($payload['start_time'] ?? null);
            $endTime = $this->normalizeTime($payload['end_time'] ?? null);
            if (! $startTime || ! $endTime) {
                throw ValidationException::withMessages([
                    'start_time' => ['start_time and end_time are required for hourly leave.'],
                ]);
            }

            if (strtotime($endTime) <= strtotime($startTime)) {
                throw ValidationException::withMessages([
                    'end_time' => ['end_time must be after start_time.'],
                ]);
            }

            return [
                'student' => $student,
                'subject_id' => $subjectIds[0] ?? null,
                'subject_ids' => $subjectIds,
                'request_type' => 'hourly',
                'start_date' => $startDate,
                'end_date' => $startDate,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'return_date' => $startDate,
                'total_days' => 1,
                'reason' => $reason,
            ];
        }

        $endDate = Carbon::parse((string) ($payload['end_date'] ?? ''))->toDateString();
        $returnDate = Carbon::parse((string) ($payload['return_date'] ?? ''))->toDateString();
        $totalDays = (int) ($payload['total_days'] ?? 0);

        if ($totalDays <= 0) {
            throw ValidationException::withMessages([
                'total_days' => ['total_days must be at least 1.'],
            ]);
        }

        if (Carbon::parse($endDate)->lt(Carbon::parse($startDate))) {
            throw ValidationException::withMessages([
                'end_date' => ['end_date must be after or equal to start_date.'],
            ]);
        }

        if (! Carbon::parse($returnDate)->gt(Carbon::parse($endDate))) {
            throw ValidationException::withMessages([
                'return_date' => ['return_date must be after end_date.'],
            ]);
        }

        $expectedDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
        if ($totalDays !== $expectedDays) {
            $totalDays = $expectedDays;
        }

        return [
            'student' => $student,
            'subject_id' => $subjectIds[0] ?? null,
            'subject_ids' => $subjectIds,
            'request_type' => 'multi_day',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'start_time' => null,
            'end_time' => null,
            'return_date' => $returnDate,
            'total_days' => $totalDays,
            'reason' => $reason,
        ];
    }

    /**
     * @param  array<int, mixed>  $subjectIds
     * @return array<int, int>
     */
    private function normalizeSubjectIds(array $subjectIds, mixed $fallbackSubjectId): array
    {
        $normalized = collect($subjectIds)
            ->map(fn ($item): int => (int) $item)
            ->filter(fn (int $item): bool => $item > 0)
            ->unique()
            ->values()
            ->all();

        if ($normalized === [] && $fallbackSubjectId) {
            $subjectId = (int) $fallbackSubjectId;
            if ($subjectId > 0) {
                $normalized[] = $subjectId;
            }
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'subject_ids' => ['Please select at least one subject.'],
            ]);
        }

        return $normalized;
    }

    /**
     * @param  array<int, int>  $subjectIds
     */
    private function ensureSubjectsBelongToClass(Student $student, array $subjectIds): void
    {
        $schoolId = (int) ($student->class?->school_id ?? 0);
        if ($schoolId <= 0) {
            throw ValidationException::withMessages([
                'student_id' => ['Student school not found.'],
            ]);
        }

        $validIds = Subject::query()
            ->where('school_id', $schoolId)
            ->whereIn('id', $subjectIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if (count($validIds) !== count($subjectIds)) {
            throw ValidationException::withMessages([
                'subject_ids' => ['One or more selected subjects are invalid for this school.'],
            ]);
        }

        $classSubjectIds = DB::table('teacher_class')
            ->where('class_id', (int) $student->class_id)
            ->whereIn('subject_id', $subjectIds)
            ->distinct()
            ->pluck('subject_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if (count($classSubjectIds) !== count($subjectIds)) {
            throw ValidationException::withMessages([
                'subject_ids' => ['Please choose subjects assigned to the student class.'],
            ]);
        }
    }

    /**
     * @param  array<int, int>  $subjectIds
     */
    private function syncRecipients(LeaveRequest $leaveRequest, Student $student, array $subjectIds): void
    {
        $schoolId = (int) ($student->class?->school_id ?? 0);
        $classId = (int) ($student->class_id ?? 0);
        if ($schoolId <= 0 || $classId <= 0) {
            $leaveRequest->recipients()->sync([]);

            return;
        }

        $adminIds = User::query()
            ->where('school_id', $schoolId)
            ->where('role', 'admin')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $teacherIds = User::query()
            ->where('school_id', $schoolId)
            ->where('role', 'teacher')
            ->whereHas('teachingClasses', function (Builder $classQuery) use ($classId): void {
                $classQuery->where('classes.id', $classId);
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $syncPayload = [];
        foreach ($adminIds as $adminId) {
            $syncPayload[$adminId] = ['recipient_role' => 'admin'];
        }
        foreach ($teacherIds as $teacherId) {
            $syncPayload[$teacherId] = ['recipient_role' => 'teacher'];
        }

        $leaveRequest->recipients()->sync($syncPayload);
    }

    private function notifyRecipients(LeaveRequest $leaveRequest): void
    {
        $leaveRequest->loadMissing('student.user', 'recipients');

        $recipientIds = $leaveRequest->recipients
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0 && $id !== (int) $leaveRequest->submitted_by)
            ->unique()
            ->values()
            ->all();

        if ($recipientIds === []) {
            return;
        }

        $studentName = (string) ($leaveRequest->student?->user?->name ?? 'Student');
        $subjectName = $this->leaveSubjectSummary($leaveRequest);
        $rows = [];
        $now = now();
        foreach ($recipientIds as $recipientId) {
            $rows[] = [
                'user_id' => $recipientId,
                'title' => 'Leave request pending approval',
                'content' => $studentName.' requested leave for '.$subjectName.'.',
                'date' => $now,
                'read_status' => false,
            ];
        }

        UserNotification::query()->insert($rows);
    }

    private function leaveSubjectSummary(LeaveRequest $leaveRequest): string
    {
        $subjectIds = collect($leaveRequest->subject_ids ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($subjectIds === [] && $leaveRequest->subject_id) {
            $subjectIds = [(int) $leaveRequest->subject_id];
        }

        if ($subjectIds === []) {
            return 'selected subjects';
        }

        $names = Subject::query()
            ->whereIn('id', $subjectIds)
            ->pluck('name')
            ->filter(fn ($name): bool => is_string($name) && trim($name) !== '')
            ->values()
            ->all();

        if ($names === []) {
            return 'selected subjects';
        }

        return implode(', ', array_slice($names, 0, 3));
    }

    private function notifySubmitterAndFamily(LeaveRequest $leaveRequest, string $status): void
    {
        $leaveRequest->loadMissing('student.parents', 'student.user', 'approver');

        $recipientIds = collect([
            (int) $leaveRequest->submitted_by,
            (int) ($leaveRequest->student?->user_id ?? 0),
        ])->merge(
            $leaveRequest->student?->parents?->pluck('id') ?? []
        )
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($recipientIds === []) {
            return;
        }

        $studentName = (string) ($leaveRequest->student?->user?->name ?? 'Student');
        $approverName = (string) ($leaveRequest->approver?->name ?? 'Teacher/Admin');
        $title = match ($status) {
            'approved' => 'Leave request approved',
            'rejected' => 'Leave request rejected',
            default => 'Leave request updated',
        };
        $content = match ($status) {
            'approved' => 'Leave request for '.$studentName.' was approved by '.$approverName.'.',
            'rejected' => 'Leave request for '.$studentName.' was rejected by '.$approverName.'.',
            default => 'Leave request for '.$studentName.' is now '.$status.'.',
        };
        $rows = [];
        $now = now();
        foreach ($recipientIds as $recipientId) {
            $rows[] = [
                'user_id' => $recipientId,
                'title' => $title,
                'content' => $content,
                'date' => $now,
                'read_status' => false,
            ];
        }

        UserNotification::query()->insert($rows);
    }

    private function syncApprovedLeaveToAttendance(LeaveRequest $leaveRequest): void
    {
        $student = Student::query()->find($leaveRequest->student_id);
        if (! $student || ! $student->class_id) {
            return;
        }

        $startDate = Carbon::parse((string) $leaveRequest->start_date)->startOfDay();
        $endDate = Carbon::parse((string) ($leaveRequest->end_date ?? $leaveRequest->start_date))->startOfDay();
        $statusNote = 'Auto leave by approved request #'.$leaveRequest->id;

        if ($leaveRequest->request_type === 'hourly') {
            $date = $startDate->toDateString();
            $startTime = $this->normalizeTime($leaveRequest->start_time);
            $endTime = $this->normalizeTime($leaveRequest->end_time);
            if (! $startTime || ! $endTime) {
                return;
            }

            $records = Attendance::query()
                ->where('student_id', $student->id)
                ->where('class_id', $student->class_id)
                ->whereDate('date', $date)
                ->get();

            $updatedAny = false;
            foreach ($records as $record) {
                $recordStart = (string) $record->time_start;
                $recordEnd = (string) ($record->time_end ?: $record->time_start);
                if (! $this->timeRangesOverlap($startTime, $endTime, $recordStart, $recordEnd)) {
                    continue;
                }

                $record->update([
                    'status' => 'L',
                    'remarks' => $statusNote,
                ]);
                $updatedAny = true;
            }

            if (! $updatedAny) {
                Attendance::query()->updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'class_id' => $student->class_id,
                        'date' => $date,
                        'time_start' => $startTime,
                    ],
                    [
                        'time_end' => $endTime,
                        'status' => 'L',
                        'remarks' => $statusNote,
                    ]
                );
            }

            return;
        }

        for ($cursor = $startDate->copy(); $cursor->lte($endDate); $cursor->addDay()) {
            $date = $cursor->toDateString();
            $records = Attendance::query()
                ->where('student_id', $student->id)
                ->where('class_id', $student->class_id)
                ->whereDate('date', $date)
                ->get();

            if ($records->isEmpty()) {
                Attendance::query()->updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'class_id' => $student->class_id,
                        'date' => $date,
                        'time_start' => '00:00:00',
                    ],
                    [
                        'time_end' => null,
                        'status' => 'L',
                        'remarks' => $statusNote,
                    ]
                );

                continue;
            }

            foreach ($records as $record) {
                $record->update([
                    'status' => 'L',
                    'remarks' => $statusNote,
                ]);
            }
        }
    }

    private function timeRangesOverlap(string $startA, string $endA, string $startB, string $endB): bool
    {
        $aStart = strtotime($startA);
        $aEnd = strtotime($endA);
        $bStart = strtotime($startB);
        $bEnd = strtotime($endB);

        return $aStart < $bEnd && $aEnd > $bStart;
    }

    private function normalizeTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $time = trim((string) $value);
        if ($time === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
            return $time.':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time) === 1) {
            return $time;
        }

        throw ValidationException::withMessages([
            'time' => ['Invalid time format. Use HH:MM.'],
        ]);
    }
}
