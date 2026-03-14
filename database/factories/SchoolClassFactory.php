<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SchoolClass>
 */
class SchoolClassFactory extends Factory
{
    protected $model = SchoolClass::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->bothify('A#'),
            'grade_level' => fake()->randomElement(['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10']),
            'room' => fake()->bothify('R-###'),
            'school_id' => School::query()->inRandomOrder()->value('id') ?? SchoolFactory::new()->create()->id,
        ];
    }
}
