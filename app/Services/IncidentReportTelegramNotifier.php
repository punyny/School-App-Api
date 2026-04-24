<?php

namespace App\Services;

use App\Jobs\SendTelegramMessageJob;
use App\Models\IncidentReport;
use App\Models\User;

class IncidentReportTelegramNotifier
{
    public function sendToStudentParents(IncidentReport $incidentReport): void
    {
        if (! (bool) config('services.telegram.enabled', false)) {
            return;
        }

        $incidentReport->loadMissing([
            'student.user',
            'student.class.school',
            'student.parents',
            'reporter',
        ]);

        $parentUsers = $incidentReport->student?->parents
            ?->filter(function (User $user): bool {
                $chatId = trim((string) ($user->telegram_chat_id ?? ''));

                return $chatId !== '';
            })
            ->values();

        if (! $parentUsers || $parentUsers->isEmpty()) {
            return;
        }

        $text = $this->buildTelegramText($incidentReport);

        foreach ($parentUsers as $parentUser) {
            SendTelegramMessageJob::dispatchSync(
                chatId: (string) $parentUser->telegram_chat_id,
                text: $text,
                meta: [
                    'source' => 'incident-report',
                    'event_type' => 'created-parent-private',
                    'incident_id' => (int) $incidentReport->id,
                    'student_id' => (int) $incidentReport->student_id,
                    'recipient_user_id' => (int) $parentUser->id,
                ],
            );
        }
    }

    private function buildTelegramText(IncidentReport $incidentReport): string
    {
        $studentName = trim((string) data_get($incidentReport, 'student.user.name', ''));
        $className = trim((string) data_get($incidentReport, 'student.class.class_name', ''));
        if ($className === '') {
            $className = trim((string) data_get($incidentReport, 'student.class.name', ''));
        }
        if ($className === '') {
            $className = trim((string) data_get($incidentReport, 'student.class.grade_level', ''));
        }
        $schoolName = trim((string) data_get($incidentReport, 'student.class.school.name', ''));
        $reporterName = trim((string) data_get($incidentReport, 'reporter.name', ''));
        $type = trim((string) ($incidentReport->type ?? ''));
        $date = $this->displayDate($incidentReport->date);
        $description = trim((string) ($incidentReport->description ?? ''));
        $acknowledgedText = (bool) ($incidentReport->acknowledged ?? false)
            ? '✅ បានទទួលស្គាល់'
            : '⚠️ មិនទាន់ទទួលស្គាល់';

        $lines = [
            'របាយការណ៍បញ្ហាសិស្ស',
            'សិស្ស : '.($studentName !== '' ? $studentName : '-'),
            'ថ្នាក់ : '.($className !== '' ? $className : '-'),
            'សាលា : '.($schoolName !== '' ? $schoolName : '-'),
            'ប្រភេទ : '.($type !== '' ? $type : '-'),
            'ថ្ងៃ : '.$date,
            'រាយការណ៍ដោយ : '.($reporterName !== '' ? $reporterName : '-'),
            'ស្ថានភាព : '.$acknowledgedText,
            'ពិពណ៌នា : '.($description !== '' ? mb_strimwidth($description, 0, 800, '...') : '-'),
        ];

        return implode("\n", $lines);
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
}
