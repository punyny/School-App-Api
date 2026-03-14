<?php

namespace Database\Factories;

use App\Models\Homework;
use App\Models\HomeworkStatus;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HomeworkStatus>
 */
class HomeworkStatusFactory extends Factory
{
    protected $model = HomeworkStatus::class;

    public function definition(): array
    {
        $homework = Homework::query()->inRandomOrder()->first() ?? HomeworkFactory::new()->create();

        $student = Student::query()->where('class_id', $homework->class_id)->inRandomOrder()->first();
        if (! $student) {
            $student = StudentFactory::new()->create(['class_id' => $homework->class_id]);
        }

        $status = fake()->randomElement(['Done', 'Not Done', 'Overdue']);

        return [
            'homework_id' => $homework->id,
            'student_id' => $student->id,
            'status' => $status,
            'completion_date' => $status === 'Done' ? now()->toDateString() : null,
        ];
    }
}
