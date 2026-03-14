<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\School>
 */
class SchoolFactory extends Factory
{
    protected $model = School::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' School',
            'school_code' => strtoupper(fake()->unique()->bothify('SCH-###??')),
            'location' => fake()->city(),
            'config_details' => [
                'timezone' => 'Asia/Phnom_Penh',
                'academic_year' => '2025-2026',
                'language' => 'km',
            ],
        ];
    }
}
