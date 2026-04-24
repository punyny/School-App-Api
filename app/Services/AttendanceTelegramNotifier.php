<?php

namespace App\Services;

use App\Jobs\SendTelegramMessageJob;
use App\Models\Attendance;
use App\Models\User;

class AttendanceTelegramNotifier
{
    public function sendToStudentParents(
        Attendance $attendance,
        User $actor,
        string $eventType = 'created',
    ): void {
        if (! (bool) config('services.telegram.enabled', false)) {
            return;
        }

        $attendance->loadMissing([
            'student.user',
            'student.parents',
            'class.school',
            'subject',
        ]);

        $parentUsers = $attendance->student?->parents
            ?->filter(function (User $user): bool {
                $chatId = trim((string) ($user->telegram_chat_id ?? ''));

                return $chatId !== '';
            })
            ->values();

        if (! $parentUsers || $parentUsers->isEmpty()) {
            return;
        }

        $text = $this->buildTelegramText(
            attendance: $attendance,
            actor: $actor,
            eventType: $eventType,
        );

        foreach ($parentUsers as $parentUser) {
            SendTelegramMessageJob::dispatchSync(
                chatId: (string) $parentUser->telegram_chat_id,
                text: $text,
                meta: [
                    'source' => 'attendance',
                    'event_type' => $eventType,
                    'attendance_id' => (int) $attendance->id,
                    'student_id' => (int) $attendance->student_id,
                    'recipient_user_id' => (int) $parentUser->id,
                ],
            );
        }
    }

    private function buildTelegramText(
        Attendance $attendance,
        User $actor,
        string $eventType,
    ): string {
        $studentName = trim((string) data_get($attendance, 'student.user.name', ''));
        $className = trim((string) data_get($attendance, 'class.class_name', ''));
        if ($className === '') {
            $className = trim((string) data_get($attendance, 'class.name', ''));
        }
        if ($className === '') {
            $className = trim((string) data_get($attendance, 'class.grade_level', ''));
        }
        $schoolName = trim((string) data_get($attendance, 'class.school.name', ''));
        $subjectName = trim((string) data_get($attendance, 'subject.name', ''));
        $date = $this->displayDate($attendance->date);
        $timeRange = $this->displayTimeRange($attendance->time_start, $attendance->time_end);
        $statusLabel = $this->statusLabel((string) ($attendance->status ?? ''));
        $statusCode = trim(strtoupper((string) ($attendance->status ?? '')));
        $remarks = trim((string) ($attendance->remarks ?? ''));
        $actorName = trim((string) ($actor->name ?? ''));
        $actorRole = $this->actorRoleLabel((string) ($actor->role ?? ''));

        $title = match (trim(strtolower($eventType))) {
            'updated' => 'បច្ចុប្បន្នភាពវត្តមានសិស្ស',
            default => 'វត្តមានសិស្ស',
        };
        $intro = $this->buildStatusGreeting(
            status: $statusCode,
            studentName: $studentName !== '' ? $studentName : 'Student',
            timeRange: $timeRange,
            date: $date,
            eventType: $eventType,
        );

        $lines = [
            $title,
            $intro,
            'សិស្ស : '.($studentName !== '' ? $studentName : '-'),
            'ថ្នាក់ : '.($className !== '' ? $className : '-'),
            'សាលា : '.($schoolName !== '' ? $schoolName : '-'),
            'មុខវិជ្ជា : '.($subjectName !== '' ? $subjectName : '-'),
            'ថ្ងៃ : '.$date,
            'ម៉ោង : '.($timeRange !== '' ? $timeRange : '-'),
            'ស្ថានភាព : '.$statusLabel,
            'កត់ត្រាដោយ : '.($actorName !== '' ? $actorName : '-').($actorRole !== '' ? ' ('.$actorRole.')' : ''),
        ];

        if ($remarks !== '') {
            $lines[] = 'កំណត់សម្គាល់ : '.mb_strimwidth($remarks, 0, 800, '...');
        }

        return implode("\n", $lines);
    }

    private function statusLabel(string $status): string
    {
        return match (trim(strtoupper($status))) {
            'P' => '✅ មានវត្តមាន',
            'A' => '❌ អវត្តមាន',
            'L' => '🟨 សុំច្បាប់',
            default => trim($status) !== '' ? trim($status) : '-',
        };
    }

    private function buildStatusGreeting(
        string $status,
        string $studentName,
        string $timeRange,
        string $date,
        string $eventType,
    ): string {
        $statusKey = trim(strtoupper($status));
        $isUpdated = trim(strtolower($eventType)) === 'updated';

        return match ($statusKey) {
            'P' => $isUpdated
                ? 'Greeting: Your child, '.$studentName.', attendance was updated to checked in on '.$date.' '.($timeRange !== '' ? 'at '.$timeRange : '').'.'
                : 'Greeting: Your child, '.$studentName.', has successfully checked into our system '.($timeRange !== '' ? 'at '.$timeRange.' ' : '').'on '.$date.'.',
            'A' => 'Alert: Your child, '.$studentName.', was marked absent '.($timeRange !== '' ? 'for '.$timeRange.' ' : '').'on '.$date.'.',
            'L' => 'Notice: Your child, '.$studentName.', was marked on leave '.($timeRange !== '' ? 'for '.$timeRange.' ' : '').'on '.$date.'.',
            default => 'Notice: Attendance for '.$studentName.' was recorded on '.$date.'.',
        };
    }

    private function actorRoleLabel(string $role): string
    {
        return match (trim(strtolower($role))) {
            'teacher' => 'Teacher',
            'admin' => 'Admin',
            'super-admin' => 'Super Admin',
            default => '',
        };
    }

    private function displayDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $text = trim((string) $value);
        if ($text === '') {
            return '-';
        }

        return mb_substr($text, 0, 10);
    }

    private function displayTimeRange(mixed $start, mixed $end): string
    {
        $startTime = $this->displayTime($start);
        $endTime = $this->displayTime($end);

        if ($startTime === '' || $endTime === '') {
            return '';
        }

        return $startTime.' - '.$endTime;
    }

    private function displayTime(mixed $value): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        if (preg_match('/^\d{2}:\d{2}$/', $text) === 1) {
            return $text;
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $text) === 1) {
            return mb_substr($text, 0, 5);
        }

        return '';
    }
}
