<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\Notification as UserNotification;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveRequestStatusService
{
    public function __construct(
        private readonly LeaveRequestTelegramNotifier $telegramNotifier,
    ) {}

    public function updateStatus(LeaveRequest $leaveRequest, string $status, User $actor): LeaveRequest
    {
        $statusValue = trim(strtolower($status));

        DB::transaction(function () use ($leaveRequest, $statusValue, $actor): void {
            $managedLeaveRequest = LeaveRequest::query()
                ->lockForUpdate()
                ->findOrFail((int) $leaveRequest->id);

            $managedLeaveRequest->status = $statusValue;

            if ($statusValue === 'pending') {
                $managedLeaveRequest->approved_by = null;
                $managedLeaveRequest->approved_at = null;
            } else {
                $managedLeaveRequest->approved_by = $actor->id;
                $managedLeaveRequest->approved_at = now();
            }

            $managedLeaveRequest->save();

            if ($statusValue === 'approved') {
                $this->syncApprovedLeaveToAttendance($managedLeaveRequest->fresh());
            }

            $this->notifySubmitterAndFamily($managedLeaveRequest->fresh(), $statusValue);
        });

        return $leaveRequest->fresh()->load(['student.user', 'student.class', 'subject', 'submitter', 'approver', 'recipients']);
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

        $this->telegramNotifier->sendStatusUpdateNotifications(
            recipientIds: $recipientIds,
            title: $title,
            content: $content,
            leaveRequestId: (int) $leaveRequest->id,
        );
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

        return null;
    }
}
