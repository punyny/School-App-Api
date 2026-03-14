<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Services\InternalApiClient;
use App\Support\AcademicScoreSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

trait BuildsAcademicDashboardMetrics
{
    /**
     * @param  array<string, mixed>  $filters
     */
    protected function fetchPaginatedTotal(
        Request $request,
        InternalApiClient $api,
        string $uri,
        array $filters = []
    ): int {
        $result = $api->get($request, $uri, array_merge($filters, ['per_page' => 1]));
        if (($result['status'] ?? 0) !== 200) {
            return 0;
        }

        $payload = $result['data'] ?? [];
        if (! is_array($payload)) {
            return 0;
        }

        return (int) ($payload['total'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    protected function fetchPaginatedItems(
        Request $request,
        InternalApiClient $api,
        string $uri,
        array $filters = [],
        int $maxPages = 5
    ): array {
        $items = [];
        $page = 1;
        $lastPage = 1;

        do {
            $result = $api->get($request, $uri, array_merge($filters, [
                'per_page' => 100,
                'page' => $page,
            ]));

            if (($result['status'] ?? 0) !== 200) {
                break;
            }

            $payload = $result['data'] ?? [];
            if (! is_array($payload)) {
                break;
            }

            $data = $payload['data'] ?? [];
            if (is_array($data)) {
                foreach ($data as $item) {
                    if (is_array($item)) {
                        $items[] = $item;
                    }
                }
            }

            $lastPage = max(1, (int) ($payload['last_page'] ?? 1));
            $page++;
        } while ($page <= $lastPage && $page <= $maxPages);

        return $items;
    }

    /**
     * @param  array<int, array<string, mixed>>  $homeworks
     * @return array{done:int,not_done:int,overdue:int}
     */
    protected function summarizeHomeworkStatuses(array $homeworks, int $studentId): array
    {
        $summary = [
            'done' => 0,
            'not_done' => 0,
            'overdue' => 0,
        ];

        foreach ($homeworks as $homework) {
            $status = $this->resolveHomeworkStatus($homework, $studentId);

            if ($status === 'Done') {
                $summary['done']++;

                continue;
            }

            if ($status === 'Overdue') {
                $summary['overdue']++;

                continue;
            }

            $summary['not_done']++;
        }

        return $summary;
    }

    /**
     * @param  array<int, array<string, mixed>>  $homeworks
     * @return array<int, array<int, string>>
     */
    protected function buildHomeworkRows(array $homeworks, int $studentId, int $limit = 20): array
    {
        $rows = [];

        foreach (array_slice($homeworks, 0, $limit) as $homework) {
            $rows[] = [
                (string) ($homework['title'] ?? '-'),
                (string) ($homework['subject']['name'] ?? ($homework['subject_id'] ?? '-')),
                (string) ($homework['due_date'] ?? '-'),
                $this->resolveHomeworkStatus($homework, $studentId),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $scores
     * @return array{gpa:string,average_score:string}
     */
    protected function summarizeGpa(array $scores, ?int $classId = null): array
    {
        /** @var AcademicScoreSummary $calculator */
        $calculator = app(AcademicScoreSummary::class);
        $summary = $calculator->summarizeArrayScores($scores, $classId);

        return [
            'gpa' => $summary['gpa'],
            'average_score' => $summary['average_score'],
        ];
    }

    /**
     * @param  array<string, mixed>  $homework
     */
    private function resolveHomeworkStatus(array $homework, int $studentId): string
    {
        $statuses = $homework['statuses'] ?? [];
        if (is_array($statuses)) {
            foreach ($statuses as $statusRow) {
                if (! is_array($statusRow)) {
                    continue;
                }

                $rowStudentId = (int) ($statusRow['student_id'] ?? $statusRow['student']['id'] ?? 0);
                if ($rowStudentId !== $studentId) {
                    continue;
                }

                $statusValue = (string) ($statusRow['status'] ?? '');
                if ($statusValue === 'Done') {
                    return 'Done';
                }

                return $this->isHomeworkOverdue($homework) ? 'Overdue' : 'Not Done';
            }
        }

        return $this->isHomeworkOverdue($homework) ? 'Overdue' : 'Not Done';
    }

    /**
     * @param  array<string, mixed>  $homework
     */
    private function isHomeworkOverdue(array $homework): bool
    {
        $dueDate = (string) ($homework['due_date'] ?? '');
        if ($dueDate === '') {
            return false;
        }

        try {
            return Carbon::parse($dueDate)->isBefore(now()->startOfDay());
        } catch (\Throwable) {
            return false;
        }
    }

}
