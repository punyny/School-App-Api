<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $schoolId = School::query()->inRandomOrder()->value('id');
        $password = static::$password ??= Hash::make('password123');

        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'role' => fake()->randomElement(['admin', 'teacher', 'student', 'parent']),
            'school_id' => $schoolId,
            'phone' => fake()->phoneNumber(),
            'password_hash' => $password,
            'address' => fake()->address(),
            'bio' => fake()->sentence(),
            'image_url' => fake()->imageUrl(),
            'active' => true,
            'is_active' => true,
            'last_login' => now()->subDays(fake()->numberBetween(0, 30)),
            'email_verified_at' => now(),
            'password' => $password,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => [
            'role' => 'super-admin',
            'school_id' => null,
        ]);
    }

    public function admin(?int $schoolId = null): static
    {
        return $this->state(fn () => [
            'role' => 'admin',
            'school_id' => $schoolId ?? School::query()->inRandomOrder()->value('id'),
        ]);
    }

    public function teacher(?int $schoolId = null): static
    {
        return $this->state(fn () => [
            'role' => 'teacher',
            'school_id' => $schoolId ?? School::query()->inRandomOrder()->value('id'),
        ]);
    }

    public function student(?int $schoolId = null): static
    {
        return $this->state(fn () => [
            'role' => 'student',
            'school_id' => $schoolId ?? School::query()->inRandomOrder()->value('id'),
        ]);
    }

    public function parent(?int $schoolId = null): static
    {
        return $this->state(fn () => [
            'role' => 'parent',
            'school_id' => $schoolId ?? School::query()->inRandomOrder()->value('id'),
        ]);
    }
}
