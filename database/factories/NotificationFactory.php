<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::query()->inRandomOrder()->value('id') ?? UserFactory::new()->create()->id,
            'title' => fake()->sentence(4),
            'content' => fake()->sentence(12),
            'date' => fake()->dateTimeBetween('-10 days', 'now')->format('Y-m-d H:i:s'),
            'read_status' => fake()->boolean(40),
        ];
    }
}
