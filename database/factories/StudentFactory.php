<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        $class = SchoolClass::query()->inRandomOrder()->first();

        if (! $class) {
            $school = School::query()->inRandomOrder()->first() ?? SchoolFactory::new()->create();
            $class = SchoolClassFactory::new()->create(['school_id' => $school->id]);
        }

        $studentUserId = User::query()
            ->where('role', 'student')
            ->where('school_id', $class->school_id)
            ->whereNotIn('id', Student::query()->pluck('user_id'))
            ->inRandomOrder()
            ->value('id');

        if (! $studentUserId) {
            $studentUserId = UserFactory::new()->student((int) $class->school_id)->create()->id;
        }

        return [
            'user_id' => $studentUserId,
            'grade' => fake()->randomElement(['7', '8', '9', '10']),
            'class_id' => $class->id,
        ];
    }
}
