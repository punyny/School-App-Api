<?php

namespace Database\Factories;

use App\Models\Homework;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Homework>
 */
class HomeworkFactory extends Factory
{
    protected $model = Homework::class;

    public function definition(): array
    {
        $class = SchoolClass::query()->inRandomOrder()->first() ?? SchoolClassFactory::new()->create();
        $subject = Subject::query()->where('school_id', $class->school_id)->inRandomOrder()->first();

        if (! $subject) {
            $subject = SubjectFactory::new()->create(['school_id' => $class->school_id]);
        }

        return [
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'title' => fake()->sentence(4),
            'question' => fake()->paragraph(),
            'due_date' => fake()->dateTimeBetween('now', '+14 days')->format('Y-m-d'),
            'file_attachments' => [
                fake()->url().'/worksheet.pdf',
            ],
        ];
    }
}
