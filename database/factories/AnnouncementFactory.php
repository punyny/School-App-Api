<?php

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\School;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    public function definition(): array
    {
        $school = School::query()->inRandomOrder()->first() ?? SchoolFactory::new()->create();
        $class = SchoolClass::query()->where('school_id', $school->id)->inRandomOrder()->first();

        return [
            'title' => fake()->sentence(5),
            'content' => fake()->paragraph(),
            'date' => fake()->dateTimeBetween('-7 days', '+7 days')->format('Y-m-d'),
            'school_id' => $school->id,
            'class_id' => fake()->boolean(60) ? ($class?->id) : null,
        ];
    }
}
