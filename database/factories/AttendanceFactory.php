<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        $student = Student::query()->inRandomOrder()->first() ?? StudentFactory::new()->create();

        return [
            'student_id' => $student->id,
            'class_id' => $student->class_id ?? SchoolClass::query()->inRandomOrder()->value('id') ?? SchoolClassFactory::new()->create()->id,
            'date' => fake()->dateTimeBetween('-14 days', 'now')->format('Y-m-d'),
            'time_start' => fake()->randomElement(['07:00:00', '08:00:00', '13:00:00']),
            'time_end' => fake()->randomElement(['08:00:00', '09:00:00', '14:00:00']),
            'status' => fake()->randomElement(['P', 'A', 'L']),
        ];
    }
}
