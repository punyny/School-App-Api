<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        $sender = User::query()
            ->whereIn('role', ['super-admin', 'admin', 'teacher'])
            ->inRandomOrder()
            ->first() ?? UserFactory::new()->teacher()->create();

        $receiverId = User::query()
            ->whereKeyNot($sender->id)
            ->when($sender->school_id, fn ($q) => $q->where('school_id', $sender->school_id))
            ->inRandomOrder()
            ->value('id');

        $classId = null;
        if ($sender->school_id) {
            $classId = SchoolClass::query()
                ->where('school_id', $sender->school_id)
                ->inRandomOrder()
                ->value('id');
        }

        return [
            'sender_id' => $sender->id,
            'receiver_id' => fake()->boolean(70) ? $receiverId : null,
            'class_id' => fake()->boolean(50) ? $classId : null,
            'content' => fake()->sentence(10),
            'date' => fake()->dateTimeBetween('-7 days', 'now')->format('Y-m-d H:i:s'),
        ];
    }
}
