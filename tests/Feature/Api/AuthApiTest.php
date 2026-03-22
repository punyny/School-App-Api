<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\MobileMagicLoginLinkNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
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

    public function test_unverified_user_cannot_login(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $teacher->forceFill(['email_verified_at' => null])->save();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'teacher@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Please verify your email address before logging in.');
    }

    public function test_verification_link_marks_user_verified_and_allows_login(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $teacher->forceFill(['email_verified_at' => null])->save();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $teacher->id,
                'hash' => sha1((string) $teacher->getEmailForVerification()),
            ]
        );

        $this->get($verificationUrl)->assertRedirect('/dashboard');

        $teacher->refresh();
        $this->assertNotNull($teacher->email_verified_at);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'teacher@example.com',
            'password' => 'password123',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk();
    }

    public function test_admin_creating_user_sends_verification_email(): void
    {
        Notification::fake();
        $this->seed();
        config()->set('app.url', 'http://192.168.1.4:8001');

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $token = $admin->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/users', [
            'role' => 'teacher',
            'name' => 'New Teacher',
            'email' => 'new-teacher@example.com',
            'phone' => '012345678',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'User created successfully. Verification email sent.');

        $user = User::query()->where('email', 'new-teacher@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification, array $channels) use ($user): bool {
            $mail = $notification->toMail($user);

            return str_starts_with((string) $mail->actionUrl, 'http://192.168.1.4:8001/email/verify/');
        });
    }

    public function test_admin_changing_email_resets_verification_and_sends_new_email(): void
    {
        Notification::fake();
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $token = $admin->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->putJson('/api/users/'.$teacher->id, [
            'email' => 'teacher.updated@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'User updated successfully. Email changed, verification reset, and a new verification email was sent.');

        $teacher->refresh();
        $this->assertSame('teacher.updated@example.com', $teacher->email);
        $this->assertNull($teacher->email_verified_at);

        Notification::assertSentTo($teacher, VerifyEmail::class);
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

    public function test_mobile_magic_link_request_sends_email_for_supported_role(): void
    {
        Notification::fake();
        $this->seed();
        config()->set('app.mobile_magic_link_base', 'schoolmobile://login');

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();

        $response = $this->postJson('/api/auth/magic-link/request', [
            'email' => 'teacher@example.com',
            'device_name' => 'iphone-15',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'If that account exists, we sent a sign-in link to its email address.');

        Notification::assertSentTo($teacher, MobileMagicLoginLinkNotification::class, function (MobileMagicLoginLinkNotification $notification): bool {
            return str_contains($notification->bridgeLoginUrl(), '/login/mobile/')
                && str_starts_with($notification->mobileLoginUrl(), 'schoolmobile://login?')
                && str_contains($notification->mobileLoginUrl(), 'device_name=iphone-15');
        });
    }

    public function test_mobile_magic_link_verify_returns_token(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $token = str_repeat('a', 64);
        $cacheKey = 'mobile-magic-login:'.$teacher->id.':'.hash('sha256', $token);
        Cache::put($cacheKey, $token, now()->addMinutes(15));

        $response = $this->postJson('/api/auth/magic-link/verify', [
            'id' => $teacher->id,
            'token' => $token,
            'device_name' => 'pixel-9',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'token',
                'token_type',
                'user' => ['id', 'role', 'email'],
            ])
            ->assertJsonPath('message', 'Magic link verified successfully.')
            ->assertJsonPath('user.email', 'teacher@example.com');

        $this->assertNull(Cache::get($cacheKey));
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
