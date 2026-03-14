<?php

namespace Database\Factories;

use App\Models\LeaveRequest;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    public function definition(): array
    {
        $student = Student::query()->inRandomOrder()->first() ?? StudentFactory::new()->create();
        $class = SchoolClass::query()->find($student->class_id) ?? SchoolClassFactory::new()->create();
        $subject = Subject::query()->where('school_id', $class->school_id)->inRandomOrder()->first();

        if (! $subject) {
            $subject = SubjectFactory::new()->create(['school_id' => $class->school_id]);
        }

        $submittedBy = User::query()->whereKey($student->user_id)->value('id')
            ?? User::query()->where('role', 'parent')->inRandomOrder()->value('id')
            ?? UserFactory::new()->parent((int) $class->school_id)->create()->id;

        $requestType = fake()->randomElement(['hourly', 'multi_day']);
        $startDate = fake()->dateTimeBetween('-7 days', '+7 days')->format('Y-m-d');
        $endDate = $requestType === 'multi_day'
            ? fake()->dateTimeBetween($startDate, $startDate.' +2 days')->format('Y-m-d')
            : $startDate;
        $totalDays = (int) \Carbon\Carbon::parse($startDate)->diffInDays(\Carbon\Carbon::parse($endDate)) + 1;
        $returnDate = \Carbon\Carbon::parse($endDate)->addDay()->toDateString();

        return [
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'subject_ids' => [$subject->id],
            'request_type' => $requestType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'start_time' => $requestType === 'hourly' ? fake()->randomElement(['07:30:00', '13:00:00']) : null,
            'end_time' => $requestType === 'hourly' ? fake()->randomElement(['08:30:00', '14:00:00']) : null,
            'return_date' => $requestType === 'multi_day' ? $returnDate : $startDate,
            'total_days' => $totalDays,
            'reason' => fake()->sentence(),
            'status' => fake()->randomElement(['pending', 'approved', 'rejected']),
            'submitted_by' => $submittedBy,
        ];
    }
}
