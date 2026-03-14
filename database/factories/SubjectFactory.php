<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subject>
 */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->bothify('Subject-????-####'),
            'full_score' => 100,
            'school_id' => School::query()->inRandomOrder()->value('id') ?? SchoolFactory::new()->create()->id,
        ];
    }
}
