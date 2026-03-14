<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\Score;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Score>
 */
class ScoreFactory extends Factory
{
    protected $model = Score::class;

    public function definition(): array
    {
        $student = Student::query()->inRandomOrder()->first() ?? StudentFactory::new()->create();
        $class = SchoolClass::query()->find($student->class_id) ?? SchoolClass::query()->inRandomOrder()->first() ?? SchoolClassFactory::new()->create();

        $subject = Subject::query()->where('school_id', $class->school_id)->inRandomOrder()->first();
        if (! $subject) {
            $subject = SubjectFactory::new()->create(['school_id' => $class->school_id]);
        }

        $examScore = fake()->randomFloat(2, 40, 100);
        $totalScore = min(100, $examScore + fake()->randomFloat(2, 0, 10));

        return [
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'exam_score' => $examScore,
            'total_score' => $totalScore,
            'month' => fake()->numberBetween(1, 12),
            'quarter' => fake()->numberBetween(1, 4),
            'period' => fake()->randomElement(['Midterm', 'Final', 'Monthly']),
            'grade' => match (true) {
                $totalScore >= 90 => 'A',
                $totalScore >= 80 => 'B',
                $totalScore >= 70 => 'C',
                $totalScore >= 60 => 'D',
                $totalScore >= 50 => 'E',
                default => 'F',
            },
        ];
    }
}
