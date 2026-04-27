<?php

namespace App\Services;

use App\Models\HomeworkSubmission;
use App\Models\Score;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;

class HomeworkAutoScoreService
{
    public const AUTO_PERIOD = 'homework-auto';

    /**
     * @param  array<string, mixed>|null  $previousContext
     */
    public function syncFromSubmission(HomeworkSubmission $submission, ?array $previousContext = null): ?Score
    {
        $contexts = [];

        if ($previousContext !== null) {
            $contexts[$this->contextKey($previousContext)] = $previousContext;
        }

        $currentContext = $this->contextFromSubmission($submission);
        if ($currentContext !== null) {
            $contexts[$this->contextKey($currentContext)] = $currentContext;
        }

        $currentKey = $currentContext !== null ? $this->contextKey($currentContext) : null;
        $currentScore = null;

        foreach ($contexts as $key => $context) {
            $score = $this->syncScoreBucketFromContext($context);
            if ($currentKey !== null && $key === $currentKey) {
                $currentScore = $score;
            }
        }

        return $currentScore;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function syncScoreBucketFromContext(array $context): ?Score
    {
        $studentId = (int) ($context['student_id'] ?? 0);
        $classId = (int) ($context['class_id'] ?? 0);
        $subjectId = (int) ($context['subject_id'] ?? 0);
        $assessmentType = (string) ($context['assessment_type'] ?? '');
        $month = isset($context['month']) ? (int) $context['month'] : null;
        $semester = isset($context['semester']) ? (int) $context['semester'] : null;
        $academicYear = $this->normalizeAcademicYearValue($context['academic_year'] ?? null);

        if (
            $studentId <= 0
            || $classId <= 0
            || $subjectId <= 0
            || ! in_array($assessmentType, ['monthly', 'semester'], true)
        ) {
            return null;
        }

        if ($assessmentType === 'monthly' && ! in_array($month, range(1, 12), true)) {
            return null;
        }

        if ($assessmentType === 'semester' && ! in_array($semester, [1, 2], true)) {
            return null;
        }

        $gradedSubmissions = $this->gradedSubmissionsQuery(
            $studentId,
            $classId,
            $subjectId,
            $assessmentType,
            $month,
            $semester,
            $academicYear
        )->get(['teacher_score', 'teacher_score_max', 'score_weight_percent']);

        $bucket = $this->scoreBucket(
            classId: $classId,
            subjectId: $subjectId,
            assessmentType: $assessmentType,
            month: $assessmentType === 'monthly' ? $month : null,
            semester: $assessmentType === 'semester' ? $semester : null,
            academicYear: $academicYear,
            quarter: $assessmentType === 'monthly' && $month !== null ? (int) ceil($month / 3) : null,
            period: self::AUTO_PERIOD
        );

        if ($gradedSubmissions->isEmpty()) {
            $this->homeworkAutoScoreQuery(
                $studentId,
                $classId,
                $subjectId,
                $assessmentType,
                $month,
                $semester,
                $academicYear
            )->delete();
            $this->recomputeRankForBucketValues($bucket);
            $this->syncDerivedYearlyAverageForContext($studentId, $classId, $subjectId, $academicYear);

            return null;
        }

        $subjectFullScore = (float) (Subject::query()->find($subjectId)?->full_score ?? 100.0);
        if ($subjectFullScore <= 0) {
            $subjectFullScore = 100.0;
        }

        $rawPercents = [];
        $weightedPercents = [];
        foreach ($gradedSubmissions as $item) {
            $score = max(0.0, (float) ($item->teacher_score ?? 0));
            $maxScore = max(0.01, (float) ($item->teacher_score_max ?? 100));
            $weightPercent = min(100.0, max(0.0, (float) ($item->score_weight_percent ?? 0)));

            $rawPercent = min(100.0, ($score / $maxScore) * 100);
            $weightedPercent = ($rawPercent * $weightPercent) / 100;

            $rawPercents[] = $rawPercent;
            $weightedPercents[] = $weightedPercent;
        }

        $averageRawPercent = (float) (array_sum($rawPercents) / max(1, count($rawPercents)));
        $averageWeightedPercent = (float) (array_sum($weightedPercents) / max(1, count($weightedPercents)));

        $examScore = round(($averageRawPercent / 100) * $subjectFullScore, 2);
        $totalScore = round(($averageWeightedPercent / 100) * $subjectFullScore, 2);
        $grade = $this->resolveGrade($totalScore, $subjectFullScore);

        $score = Score::query()->updateOrCreate(
            [
                'student_id' => $studentId,
                'subject_id' => $subjectId,
                'class_id' => $classId,
                'assessment_type' => $assessmentType,
                'month' => $assessmentType === 'monthly' ? $month : null,
                'semester' => $assessmentType === 'semester' ? $semester : null,
                'academic_year' => $academicYear,
                'quarter' => $assessmentType === 'monthly' && $month !== null ? (int) ceil($month / 3) : null,
                'period' => self::AUTO_PERIOD,
            ],
            [
                'exam_score' => $examScore,
                'total_score' => $totalScore,
                'grade' => $grade,
            ]
        );

        $this->recomputeRankForBucketValues($bucket);
        $this->syncDerivedYearlyAverageForContext($studentId, $classId, $subjectId, $academicYear);

        return $score->fresh();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function contextFromSubmission(HomeworkSubmission $submission): ?array
    {
        $submission->loadMissing('homework');

        $assessmentType = trim((string) ($submission->score_assessment_type ?? ''));
        if (! in_array($assessmentType, ['monthly', 'semester'], true)) {
            return null;
        }

        $homework = $submission->homework;
        if (! $homework) {
            return null;
        }

        if (
            $submission->teacher_score === null
            || $submission->teacher_score_max === null
            || $submission->score_weight_percent === null
            || $submission->graded_at === null
        ) {
            return null;
        }

        return [
            'student_id' => (int) $submission->student_id,
            'class_id' => (int) $homework->class_id,
            'subject_id' => (int) $homework->subject_id,
            'assessment_type' => $assessmentType,
            'month' => $assessmentType === 'monthly' ? (int) ($submission->score_month ?? 0) : null,
            'semester' => $assessmentType === 'semester' ? (int) ($submission->score_semester ?? 0) : null,
            'academic_year' => $this->normalizeAcademicYearValue($submission->score_academic_year),
        ];
    }

    private function gradedSubmissionsQuery(
        int $studentId,
        int $classId,
        int $subjectId,
        string $assessmentType,
        ?int $month,
        ?int $semester,
        ?string $academicYear
    ): Builder {
        $query = HomeworkSubmission::query()
            ->where('student_id', $studentId)
            ->whereNotNull('teacher_score')
            ->whereNotNull('teacher_score_max')
            ->whereNotNull('score_weight_percent')
            ->whereNotNull('graded_at')
            ->where('score_assessment_type', $assessmentType)
            ->whereHas('homework', function (Builder $homeworkQuery) use ($classId, $subjectId): void {
                $homeworkQuery
                    ->where('class_id', $classId)
                    ->where('subject_id', $subjectId);
            });

        if ($assessmentType === 'monthly') {
            $query->where('score_month', $month)->whereNull('score_semester');
        } else {
            $query->where('score_semester', $semester)->whereNull('score_month');
        }

        if ($academicYear === null) {
            $query->whereNull('score_academic_year');
        } else {
            $query->where('score_academic_year', $academicYear);
        }

        return $query;
    }

    private function homeworkAutoScoreQuery(
        int $studentId,
        int $classId,
        int $subjectId,
        string $assessmentType,
        ?int $month,
        ?int $semester,
        ?string $academicYear
    ): Builder {
        $query = Score::query()
            ->where('student_id', $studentId)
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('assessment_type', $assessmentType)
            ->where('period', self::AUTO_PERIOD);

        if ($assessmentType === 'monthly') {
            $query->where('month', $month)->whereNull('semester');
        } else {
            $query->where('semester', $semester)->whereNull('month');
        }

        if ($academicYear === null) {
            $query->whereNull('academic_year');
        } else {
            $query->where('academic_year', $academicYear);
        }

        return $query;
    }

    /**
     * @return array<int, float>
     */
    private function yearlyComponents(
        int $studentId,
        int $classId,
        int $subjectId,
        ?string $academicYear
    ): array {
        $monthlyQuery = Score::query()
            ->where('student_id', $studentId)
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('assessment_type', 'monthly');
        $semesterQuery = Score::query()
            ->where('student_id', $studentId)
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('assessment_type', 'semester');

        $this->applyAcademicYearFilter($monthlyQuery, $academicYear);
        $this->applyAcademicYearFilter($semesterQuery, $academicYear);

        $monthlyTotals = $monthlyQuery->pluck('total_score')
            ->map(fn ($value): float => (float) $value)
            ->values()
            ->all();
        $semesterExamScores = $semesterQuery->pluck('exam_score')
            ->map(fn ($value): float => (float) $value)
            ->values()
            ->all();

        return array_merge($monthlyTotals, $semesterExamScores);
    }

    private function syncDerivedYearlyAverageForContext(
        int $studentId,
        int $classId,
        int $subjectId,
        ?string $academicYear
    ): void {
        if ($studentId <= 0 || $classId <= 0 || $subjectId <= 0) {
            return;
        }

        $components = $this->yearlyComponents($studentId, $classId, $subjectId, $academicYear);

        $yearlyQuery = Score::query()
            ->where('student_id', $studentId)
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('assessment_type', 'yearly');
        $this->applyAcademicYearFilter($yearlyQuery, $academicYear);

        $bucket = $this->scoreBucket(
            classId: $classId,
            subjectId: $subjectId,
            assessmentType: 'yearly',
            month: null,
            semester: null,
            academicYear: $academicYear,
            quarter: null,
            period: null
        );

        if ($components === []) {
            $yearlyQuery->delete();
            $this->recomputeRankForBucketValues($bucket);

            return;
        }

        $average = round(array_sum($components) / count($components), 2);

        $existingRows = $yearlyQuery->orderBy('id')->get();
        $primary = $existingRows->first();
        if ($primary === null) {
            $primary = Score::query()->create([
                'student_id' => $studentId,
                'subject_id' => $subjectId,
                'class_id' => $classId,
                'assessment_type' => 'yearly',
                'exam_score' => $average,
                'total_score' => $average,
                'month' => null,
                'semester' => null,
                'academic_year' => $academicYear,
                'quarter' => null,
                'period' => null,
                'grade' => $this->resolveGrade($average, 100.0),
                'rank_in_class' => null,
            ]);
        } else {
            $primary->fill([
                'exam_score' => $average,
                'total_score' => $average,
                'month' => null,
                'semester' => null,
                'academic_year' => $academicYear,
                'quarter' => null,
                'period' => null,
                'grade' => $this->resolveGrade($average, 100.0),
                'rank_in_class' => null,
            ])->save();

            $duplicateIds = $existingRows
                ->skip(1)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->values()
                ->all();

            if ($duplicateIds !== []) {
                Score::query()->whereIn('id', $duplicateIds)->delete();
            }
        }

        $this->recomputeRankForBucketValues($bucket);
    }

    private function applyAcademicYearFilter(Builder $query, ?string $academicYear): void
    {
        if ($academicYear === null) {
            $query->whereNull('academic_year');

            return;
        }

        $query->where('academic_year', $academicYear);
    }

    /**
     * @param  array<string, mixed>  $bucket
     */
    private function recomputeRankForBucketValues(array $bucket): void
    {
        if (empty($bucket['class_id']) || empty($bucket['subject_id'])) {
            return;
        }

        $query = Score::query()
            ->where('class_id', $bucket['class_id'])
            ->where('subject_id', $bucket['subject_id']);

        foreach (['assessment_type', 'academic_year', 'month', 'semester', 'quarter', 'period'] as $field) {
            $value = $bucket[$field] ?? null;
            if ($value === null || $value === '') {
                $query->whereNull($field);
            } else {
                $query->where($field, $value);
            }
        }

        $rows = $query
            ->orderByDesc('total_score')
            ->orderBy('id')
            ->get(['id', 'total_score']);

        $rank = 0;
        $position = 0;
        $lastScore = null;

        foreach ($rows as $row) {
            $position++;
            $currentScore = (float) $row->total_score;
            if ($lastScore === null || $currentScore < $lastScore) {
                $rank = $position;
                $lastScore = $currentScore;
            }

            Score::query()->whereKey($row->id)->update(['rank_in_class' => $rank]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function scoreBucket(
        int $classId,
        int $subjectId,
        string $assessmentType,
        ?int $month,
        ?int $semester,
        ?string $academicYear,
        ?int $quarter,
        ?string $period
    ): array {
        return [
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'assessment_type' => $assessmentType,
            'academic_year' => $academicYear,
            'month' => $month,
            'semester' => $semester,
            'quarter' => $quarter,
            'period' => $period,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function contextKey(array $context): string
    {
        return implode('|', [
            (int) ($context['student_id'] ?? 0),
            (int) ($context['class_id'] ?? 0),
            (int) ($context['subject_id'] ?? 0),
            (string) ($context['assessment_type'] ?? ''),
            (string) ($context['month'] ?? ''),
            (string) ($context['semester'] ?? ''),
            (string) ($context['academic_year'] ?? ''),
        ]);
    }

    private function normalizeAcademicYearValue(mixed $academicYear): ?string
    {
        $year = trim((string) ($academicYear ?? ''));

        return $year !== '' ? $year : null;
    }

    private function resolveGrade(float $totalScore, float $fullScore): string
    {
        $percent = $fullScore > 0 ? ($totalScore / $fullScore) * 100 : 0;

        return match (true) {
            $percent >= 90 => 'A',
            $percent >= 80 => 'B',
            $percent >= 70 => 'C',
            $percent >= 60 => 'D',
            $percent >= 50 => 'E',
            default => 'F',
        };
    }
}
