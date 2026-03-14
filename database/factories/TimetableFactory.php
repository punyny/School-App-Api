<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Timetable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Timetable>
 */
class TimetableFactory extends Factory
{
    protected $model = Timetable::class;

    public function definition(): array
    {
        $class = SchoolClass::query()->inRandomOrder()->first() ?? SchoolClassFactory::new()->create();
        $subject = Subject::query()->where('school_id', $class->school_id)->inRandomOrder()->first();

        if (! $subject) {
            $subject = SubjectFactory::new()->create(['school_id' => $class->school_id]);
        }

        $teacherId = User::query()
            ->where('role', 'teacher')
            ->where('school_id', $class->school_id)
            ->inRandomOrder()
            ->value('id');

        if (! $teacherId) {
            $teacherId = UserFactory::new()->teacher((int) $class->school_id)->create()->id;
        }

        $startHour = fake()->numberBetween(7, 15);
        $start = sprintf('%02d:00:00', $startHour);
        $end = sprintf('%02d:00:00', $startHour + 1);

        return [
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacherId,
            'day_of_week' => fake()->randomElement(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
            'time_start' => $start,
            'time_end' => $end,
        ];
    }
}
