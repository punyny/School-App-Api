<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_receive_token(): void
    {
        $this->seed();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'teacher@example.com',
            'password' => 'password123',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'token',
                'token_type',
                'user' => ['id', 'role', 'email'],
            ]);
    }

    public function test_user_can_login_with_username_and_receive_token(): void
    {
        $this->seed();

        $response = $this->postJson('/api/auth/login', [
            'login' => 'teacher',
            'password' => 'password123',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'token',
                'token_type',
                'user' => ['id', 'role', 'email', 'username'],
            ])
            ->assertJsonPath('user.username', 'teacher');
    }

    public function test_login_is_rate_limited_after_too_many_failed_attempts(): void
    {
        $this->seed();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this->postJson('/api/auth/login', [
                'email' => 'teacher@example.com',
                'password' => 'wrong-password',
            ]);

            $response->assertStatus(422);
        }

        $locked = $this->postJson('/api/auth/login', [
            'email' => 'teacher@example.com',
            'password' => 'wrong-password',
        ]);

        $locked->assertStatus(429)
            ->assertJsonStructure(['message', 'retry_after']);
    }

    public function test_successful_login_clears_rate_limit_counter(): void
    {
        $this->seed();

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $response = $this->postJson('/api/auth/login', [
                'email' => 'teacher@example.com',
                'password' => 'wrong-password',
            ]);

            $response->assertStatus(422);
        }

        $success = $this->postJson('/api/auth/login', [
            'email' => 'teacher@example.com',
            'password' => 'password123',
            'device_name' => 'phpunit',
        ]);
        $success->assertOk();

        $firstFailureAfterSuccess = $this->postJson('/api/auth/login', [
            'email' => 'teacher@example.com',
            'password' => 'wrong-password',
        ]);
        $firstFailureAfterSuccess->assertStatus(422);

        $secondFailureAfterSuccess = $this->postJson('/api/auth/login', [
            'email' => 'teacher@example.com',
            'password' => 'wrong-password',
        ]);
        $secondFailureAfterSuccess->assertStatus(422);
    }

    public function test_user_can_update_own_profile_with_image_upload(): void
    {
        $this->seed();
        Storage::fake('public');

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $token = $teacher->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->patch('/api/auth/profile', [
            'name' => 'Teacher Updated',
            'phone' => '012345678',
            'image' => UploadedFile::fake()->image('teacher.png'),
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('user.name', 'Teacher Updated');

        $teacher->refresh();
        $this->assertNotNull($teacher->image_url);
        $this->assertStringStartsWith('/storage/profiles/', (string) $teacher->image_url);
        $this->assertDatabaseHas('media', [
            'mediable_type' => User::class,
            'mediable_id' => $teacher->id,
            'category' => 'profile',
            'url' => $teacher->image_url,
            'is_primary' => true,
        ]);

        $storedPath = ltrim(str_replace('/storage/', '', (string) $teacher->image_url), '/');
        Storage::disk('public')->assertExists($storedPath);
    }
}
