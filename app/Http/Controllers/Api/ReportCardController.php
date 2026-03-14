<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Score;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Support\AcademicScoreSummary;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportCardController extends Controller
{
    public function __construct(private readonly AcademicScoreSummary $scoreSummary)
    {
    }

    public function show(Request $request, Student $student): JsonResponse
    {
        if (! $this->canViewReportCard($request->user(), $student)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $filters = $this->validateReportFilters($request);

        $scores = $this->scoreQuery($student, $filters)->get();

        return response()->json([
            'data' => [
                'student' => $student->loadMissing(['user', 'class']),
                'summary' => $this->buildSummary($student, $scores, $filters),
                'subjects' => $this->buildSubjectRows($student, $scores),
            ],
        ]);
    }

    public function exportPdf(Request $request, Student $student)
    {
        if (! $this->canViewReportCard($request->user(), $student)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $filters = $this->validateReportFilters($request);

        $scores = $this->scoreQuery($student, $filters)->get();
        $summary = $this->buildSummary($student, $scores, $filters);
        $subjects = $this->buildSubjectRows($student, $scores);
        $student->loadMissing(['user', 'class']);

        return Pdf::loadView('pdf.report-card', [
            'student' => $student,
            'summary' => $summary,
            'subjects' => $subjects,
            'filters' => $filters,
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait')
            ->download('report_card_student_'.$student->id.'.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateReportFilters(Request $request): array
    {
        return $request->validate([
            'assessment_type' => ['nullable', 'in:monthly,semester,yearly'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'semester' => ['nullable', 'integer', 'between:1,2'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'period' => ['nullable', 'string', 'max:50'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function scoreQuery(Student $student, array $filters)
    {
        $query = Score::query()
            ->with(['subject'])
            ->where('student_id', $student->id)
            ->orderBy('subject_id')
            ->orderByDesc('month')
            ->orderByDesc('quarter');

        foreach (['assessment_type', 'month', 'semester', 'academic_year', 'quarter', 'period'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return $query;
    }

    private function canViewReportCard(User $user, Student $student): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        if ($user->role === 'admin') {
            return $user->school_id && (int) $user->school_id === (int) $student->user?->school_id;
        }

        if ($user->role === 'teacher') {
            return $user->teachingClasses()->where('classes.id', $student->class_id)->exists();
        }

        if ($user->role === 'student') {
            return (int) $user->studentProfile?->id === (int) $student->id;
        }

        if ($user->role === 'parent') {
            return $user->children()->where('students.id', $student->id)->exists();
        }

        return false;
    }

    private function buildSummary(Student $student, $scores, array $filters): array
    {
        $summary = $this->scoreSummary->summarizeModelScores($scores, (int) ($student->class_id ?? 0));
        $expectedSubjectCount = (int) ($summary['subjects_count'] ?? 0);
        $annualAverage = $this->resolveStudentAnnualAverageAcrossSubjects($scores, $expectedSubjectCount);
        $summary['average_score'] = number_format($annualAverage, 2);
        $summary['gpa'] = number_format($this->scoreSummary->gpaFromAverage($annualAverage), 2);
        $summary['overall_grade'] = $scores->isNotEmpty() ? $this->scoreSummary->gradeFromAverage($annualAverage) : 'N/A';
        $summary['rank_in_class'] = $this->resolveClassRank($student, $filters);

        return $summary;
    }

    private function buildSubjectRows(Student $student, $scores): array
    {
        $groupedScores = $scores->groupBy('subject_id');
        $assignedSubjectIds = [];

        if ((int) ($student->class_id ?? 0) > 0) {
            $assignedSubjectIds = DB::table('teacher_class')
                ->where('class_id', (int) $student->class_id)
                ->pluck('subject_id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        $subjectIds = $assignedSubjectIds !== []
            ? $assignedSubjectIds
            : $groupedScores->keys()->map(fn ($id): int => (int) $id)->all();

        $subjects = Subject::query()
            ->whereIn('id', $subjectIds)
            ->get()
            ->keyBy('id');

        return collect($subjectIds)->map(function (int $subjectId) use ($groupedScores, $subjects): array {
            $group = $groupedScores->get($subjectId, collect());
            $avg = $this->resolveSubjectAnnualAverage($group);
            $subject = $subjects->get($subjectId);

            return [
                'subject_id' => $subjectId,
                'subject_name' => (string) ($subject?->name ?? 'Unknown'),
                'average_score' => number_format($avg, 2),
                'grade' => $group->isNotEmpty() ? $this->gradeFromAverage($avg) : '-',
                'entries' => $group->map(function (Score $score): array {
                    return [
                        'assessment_type' => $score->assessment_type,
                        'month' => $score->month,
                        'semester' => $score->semester,
                        'academic_year' => $score->academic_year,
                        'quarter' => $score->quarter,
                        'period' => $score->period,
                        'exam_score' => $score->exam_score,
                        'total_score' => $score->total_score,
                        'grade' => $score->grade,
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }

    private function resolveSubjectAnnualAverage($subjectScores): float
    {
        if (! method_exists($subjectScores, 'where')) {
            return 0.0;
        }

        $monthlyTotals = $subjectScores
            ->where('assessment_type', 'monthly')
            ->pluck('total_score')
            ->map(fn ($value): float => (float) $value)
            ->values()
            ->all();
        $semesterExamScores = $subjectScores
            ->where('assessment_type', 'semester')
            ->pluck('exam_score')
            ->map(fn ($value): float => (float) $value)
            ->values()
            ->all();

        $components = array_merge($monthlyTotals, $semesterExamScores);
        if ($components !== []) {
            return (float) (array_sum($components) / count($components));
        }

        if ($subjectScores->isNotEmpty()) {
            return (float) $subjectScores->avg('total_score');
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function resolveClassRank(Student $student, array $filters): ?int
    {
        $classId = (int) ($student->class_id ?? 0);
        if ($classId <= 0) {
            return null;
        }

        $classStudentIds = Student::query()
            ->where('class_id', $classId)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if ($classStudentIds === []) {
            return null;
        }

        $expectedSubjectCount = DB::table('teacher_class')
            ->where('class_id', $classId)
            ->distinct('subject_id')
            ->count('subject_id');

        $averages = [];
        foreach ($classStudentIds as $studentId) {
            $studentScoreQuery = Score::query()
                ->where('student_id', $studentId)
                ->where('class_id', $classId);
            foreach (['assessment_type', 'month', 'semester', 'academic_year', 'quarter', 'period'] as $field) {
                if (isset($filters[$field])) {
                    $studentScoreQuery->where($field, $filters[$field]);
                }
            }
            $studentScores = $studentScoreQuery->get();
            $averages[$studentId] = $this->resolveStudentAnnualAverageAcrossSubjects(
                $studentScores,
                $expectedSubjectCount > 0 ? (int) $expectedSubjectCount : null
            );
        }

        arsort($averages, SORT_NUMERIC);

        $rank = 0;
        $position = 0;
        $lastAverage = null;
        foreach ($averages as $studentId => $average) {
            $position++;
            if ($lastAverage === null || (float) $average < (float) $lastAverage) {
                $rank = $position;
                $lastAverage = (float) $average;
            }

            if ((int) $studentId === (int) $student->id) {
                return $rank;
            }
        }

        return null;
    }

    private function resolveStudentAnnualAverageAcrossSubjects($scores, ?int $expectedSubjectCount = null): float
    {
        if (! method_exists($scores, 'groupBy')) {
            return 0.0;
        }

        $subjectAverages = $scores
            ->groupBy('subject_id')
            ->map(fn ($group): float => $this->resolveSubjectAnnualAverage($group));

        if ($subjectAverages->isEmpty() && ($expectedSubjectCount === null || $expectedSubjectCount <= 0)) {
            return 0.0;
        }

        $divisor = $expectedSubjectCount !== null && $expectedSubjectCount > 0
            ? $expectedSubjectCount
            : $subjectAverages->count();

        return (float) ($subjectAverages->sum() / max(1, $divisor));
    }

    private function gradeFromAverage(float $average): string
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
}
