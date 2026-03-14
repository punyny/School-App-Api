<?php

namespace Database\Factories;

use App\Models\IncidentReport;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\IncidentReport>
 */
class IncidentReportFactory extends Factory
{
    protected $model = IncidentReport::class;

    public function definition(): array
    {
        $student = Student::query()->inRandomOrder()->first() ?? StudentFactory::new()->create();
        $class = SchoolClass::query()->find($student->class_id);

        $reporterId = User::query()
            ->whereIn('role', ['teacher', 'admin'])
            ->when($class?->school_id, fn ($q, $schoolId) => $q->where('school_id', $schoolId))
            ->inRandomOrder()
            ->value('id');

        if (! $reporterId) {
            $reporterId = UserFactory::new()->teacher((int) ($class?->school_id))->create()->id;
        }

        return [
            'student_id' => $student->id,
            'description' => fake()->sentence(16),
            'date' => fake()->dateTimeBetween('-14 days', 'now')->format('Y-m-d'),
            'type' => fake()->randomElement(['Behavior', 'Health', 'Discipline', 'Safety']),
            'acknowledged' => fake()->boolean(35),
            'reporter_id' => $reporterId,
        ];
    }
}
