<?php

namespace App\Support;

use App\Models\Score;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AcademicScoreSummary
{
    /**
     * @param  Collection<int, Score>  $scores
     * @return array{subjects_count:int,scored_subjects_count:int,average_score:string,gpa:string,overall_grade:string}
     */
    public function summarizeModelScores(Collection $scores, ?int $classId = null): array
    {
        $resolvedClassId = $classId ?? (int) ($scores->first()?->class_id ?? 0);
        $subjectAverages = $scores
            ->groupBy('subject_id')
            ->map(fn (Collection $group): float => (float) $group->avg('total_score'))
            ->all();

        return $this->buildSummary($subjectAverages, $resolvedClassId, $scores->isNotEmpty());
    }

    /**
     * @param  array<int, array<string, mixed>>  $scores
     * @return array{subjects_count:int,scored_subjects_count:int,average_score:string,gpa:string,overall_grade:string}
     */
    public function summarizeArrayScores(array $scores, ?int $classId = null): array
    {
        $totals = [];
        $counts = [];
        $resolvedClassId = $classId ?? 0;

        foreach ($scores as $score) {
            if ($resolvedClassId <= 0) {
                $resolvedClassId = (int) ($score['class_id'] ?? 0);
            }

            $subjectId = (int) ($score['subject_id'] ?? 0);
            if ($subjectId <= 0 || ! is_numeric($score['total_score'] ?? null)) {
                continue;
            }

            $totals[$subjectId] = ($totals[$subjectId] ?? 0.0) + (float) $score['total_score'];
            $counts[$subjectId] = ($counts[$subjectId] ?? 0) + 1;
        }

        $subjectAverages = [];
        foreach ($totals as $subjectId => $total) {
            $subjectAverages[(int) $subjectId] = $counts[$subjectId] > 0 ? $total / $counts[$subjectId] : 0.0;
        }

        return $this->buildSummary($subjectAverages, $resolvedClassId, $subjectAverages !== []);
    }

    public function gradeFromAverage(float $average): string
    {
        return match (true) {
            $average >= 90 => 'A',
            $average >= 80 => 'B',
            $average >= 70 => 'C',
            $average >= 60 => 'D',
            $average >= 50 => 'E',
            default => 'F',
        };
    }

    public function gpaFromAverage(float $average): float
    {
        return match (true) {
            $average >= 90 => 4.0,
            $average >= 80 => 3.0,
            $average >= 70 => 2.0,
            $average >= 60 => 1.0,
            $average >= 50 => 0.5,
            default => 0.0,
        };
    }

    /**
     * @param  array<int, float>  $subjectAverages
     * @return array{subjects_count:int,scored_subjects_count:int,average_score:string,gpa:string,overall_grade:string}
     */
    private function buildSummary(array $subjectAverages, int $classId, bool $hasScores): array
    {
        $scoredSubjectCount = count($subjectAverages);
        $expectedSubjectCount = $this->expectedSubjectCount($classId, array_keys($subjectAverages));
        $average = $expectedSubjectCount > 0
            ? array_sum($subjectAverages) / $expectedSubjectCount
            : 0.0;

        return [
            'subjects_count' => $expectedSubjectCount,
            'scored_subjects_count' => $scoredSubjectCount,
            'average_score' => number_format($average, 2),
            'gpa' => number_format($this->gpaFromAverage($average), 2),
            'overall_grade' => $hasScores ? $this->gradeFromAverage($average) : 'N/A',
        ];
    }

    /**
     * @param  array<int, int|string>  $fallbackSubjectIds
     */
    private function expectedSubjectCount(int $classId, array $fallbackSubjectIds): int
    {
        if ($classId > 0) {
            $count = DB::table('teacher_class')
                ->where('class_id', $classId)
                ->distinct('subject_id')
                ->count('subject_id');

            if ($count > 0) {
                return (int) $count;
            }
        }

        return count(array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, $fallbackSubjectIds),
            static fn (int $id): bool => $id > 0
        ))));
    }
}
