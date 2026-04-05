<?php

namespace Tests\Feature\Api;

use App\Models\LoginLinkToken;
use App\Models\User;
use App\Notifications\MobileMagicLoginLinkNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
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
                'auth_method',
                'user' => ['id', 'role', 'email'],
            ])
            ->assertJsonPath('auth_method', 'password');
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
                'auth_method',
                'user' => ['id', 'role', 'email', 'username'],
            ])
            ->assertJsonPath('auth_method', 'password')
            ->assertJsonPath('user.username', 'teacher');
    }

    public function test_admin_cannot_login_via_mobile_api_login_endpoint(): void
    {
        $this->seed();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
            'device_name' => 'phpunit',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath(
                'message',
                'This account must sign in on the web portal. Mobile login is available only for super-admin, teacher, student, and parent accounts.'
            );
    }

    public function test_user_can_login_with_explicit_password_auth_method(): void
    {
        $this->seed();

        $response = $this->postJson('/api/auth/login', [
            'auth_method' => 'password',
            'email' => 'teacher@example.com',
            'password' => 'password123',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonPath('auth_method', 'password')
            ->assertJsonPath('user.email', 'teacher@example.com');
    }

    public function test_super_admin_can_login_via_mobile_api_login_endpoint(): void
    {
        $this->seed();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'superadmin@example.com',
            'password' => 'password123',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonPath('auth_method', 'password')
            ->assertJsonPath('user.email', 'superadmin@example.com')
            ->assertJsonPath('user.role', 'super-admin');
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
            ],
            false
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
            'password' => 'password123',
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
        config()->set('app.url', 'http://school-api.test');

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();

        $response = $this
            ->withServerVariables([
                'HTTP_HOST' => '192.168.1.14:8001',
                'HTTPS' => 'off',
            ])
            ->postJson('http://192.168.1.14:8001/api/auth/magic-link/request', [
                'email' => 'teacher@example.com',
                'device_name' => 'iphone-15',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'If that account exists, we sent a sign-in link to its email address.');

        Notification::assertSentTo($teacher, MobileMagicLoginLinkNotification::class, function (MobileMagicLoginLinkNotification $notification): bool {
            return str_starts_with($notification->bridgeLoginUrl(), 'http://192.168.1.14:8001/login/mobile/')
                && str_starts_with($notification->webFallbackUrl(), 'http://192.168.1.14:8001/login/magic/')
                && str_contains($notification->bridgeLoginUrl(), '/login/mobile/')
                && str_starts_with($notification->mobileLoginUrl(), 'schoolmobile://login?')
                && str_contains($notification->mobileLoginUrl(), 'device_name=iphone-15');
        });
    }

    public function test_mobile_magic_link_request_rejects_web_portal_only_roles(): void
    {
        Notification::fake();
        $this->seed();

        $response = $this->postJson('/api/auth/magic-link/request', [
            'email' => 'admin@example.com',
            'device_name' => 'iphone-15',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath(
                'message',
                'This account must sign in on the web portal. Mobile login is available only for super-admin, teacher, student, and parent accounts.'
            );

        Notification::assertNothingSent();
    }

    public function test_mobile_magic_link_request_keeps_generic_message_for_unknown_email(): void
    {
        Notification::fake();
        $this->seed();

        $response = $this->postJson('/api/auth/magic-link/request', [
            'email' => 'missing-user@example.com',
            'device_name' => 'iphone-15',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'If that account exists, we sent a sign-in link to its email address.');

        Notification::assertNothingSent();
    }

    public function test_mobile_magic_link_verify_returns_token(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $token = str_repeat('a', 64);
        $loginLinkToken = LoginLinkToken::query()->create([
            'user_id' => $teacher->id,
            'channel' => 'mobile',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(15),
        ]);

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

        $this->assertNotNull($loginLinkToken->fresh()?->consumed_at);
    }

    public function test_login_endpoint_accepts_email_access_token_option(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $token = str_repeat('b', 64);
        $loginLinkToken = LoginLinkToken::query()->create([
            'user_id' => $teacher->id,
            'channel' => 'mobile',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'id' => $teacher->id,
            'token' => $token,
            'device_name' => 'iphone-16',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'token',
                'token_type',
                'auth_method',
                'user' => ['id', 'role', 'email'],
            ])
            ->assertJsonPath('auth_method', 'access_token')
            ->assertJsonPath('message', 'Magic link verified successfully.')
            ->assertJsonPath('user.email', 'teacher@example.com');

        $this->assertNotNull($loginLinkToken->fresh()?->consumed_at);
    }

    public function test_login_endpoint_rejects_mixing_password_and_access_token_options(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $token = str_repeat('c', 64);
        LoginLinkToken::query()->create([
            'user_id' => $teacher->id,
            'channel' => 'mobile',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'teacher@example.com',
            'password' => 'password123',
            'id' => $teacher->id,
            'token' => $token,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    public function test_login_endpoint_rejects_password_method_without_password(): void
    {
        $this->seed();

        $response = $this->postJson('/api/auth/login', [
            'auth_method' => 'password',
            'email' => 'teacher@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_login_endpoint_rejects_access_token_method_with_password_payload(): void
    {
        $this->seed();

        $response = $this->postJson('/api/auth/login', [
            'auth_method' => 'access_token',
            'email' => 'teacher@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token', 'id']);
    }

    public function test_login_endpoint_accepts_explicit_access_token_auth_method(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $token = str_repeat('d', 64);
        LoginLinkToken::query()->create([
            'user_id' => $teacher->id,
            'channel' => 'mobile',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'auth_method' => 'access_token',
            'id' => $teacher->id,
            'token' => $token,
            'device_name' => 'iphone-16',
        ]);

        $response->assertOk()
            ->assertJsonPath('auth_method', 'access_token')
            ->assertJsonPath('user.email', 'teacher@example.com');
    }

    public function test_login_endpoint_accepts_magic_link_auth_method_alias(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $token = str_repeat('e', 64);
        LoginLinkToken::query()->create([
            'user_id' => $teacher->id,
            'channel' => 'mobile',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'auth_method' => 'magic_link',
            'id' => $teacher->id,
            'token' => $token,
            'device_name' => 'iphone-16',
        ]);

        $response->assertOk()
            ->assertJsonPath('auth_method', 'access_token')
            ->assertJsonPath('user.email', 'teacher@example.com');
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

    public function test_user_can_change_password_without_current_password(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $oldToken = $teacher->createToken('phpunit-old')->plainTextToken;

        $response = $this->withToken($oldToken)->postJson('/api/auth/change-password', [
            'new_password' => 'NewPassword123!',
            'new_password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Password changed successfully.')
            ->assertJsonPath('token_type', 'Bearer');

        $teacher->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', (string) $teacher->password));
        $this->assertTrue(Hash::check('NewPassword123!', (string) $teacher->password_hash));

        $this->postJson('/api/auth/login', [
            'email' => 'teacher@example.com',
            'password' => 'password123',
        ])->assertStatus(422);

        $this->postJson('/api/auth/login', [
            'email' => 'teacher@example.com',
            'password' => 'NewPassword123!',
            'device_name' => 'phpunit',
        ])->assertOk();
    }

    public function test_change_password_fails_when_confirmation_does_not_match(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $token = $teacher->createToken('phpunit')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/auth/change-password', [
            'new_password' => 'NewPassword123!',
            'new_password_confirmation' => 'DifferentPassword123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);

        $teacher->refresh();
        $this->assertTrue(Hash::check('password123', (string) $teacher->password));
    }

    public function test_super_admin_can_change_password_for_any_user(): void
    {
        $this->seed();

        $superAdmin = User::query()->where('email', 'superadmin@example.com')->firstOrFail();
        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();

        $superToken = $superAdmin->createToken('phpunit-super')->plainTextToken;
        $teacher->createToken('phpunit-teacher')->plainTextToken;

        $response = $this->withToken($superToken)->postJson('/api/users/'.$teacher->id.'/change-password', [
            'new_password' => 'TeacherReset123!',
            'new_password_confirmation' => 'TeacherReset123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Password changed successfully.');

        $teacher->refresh();
        $this->assertTrue(Hash::check('TeacherReset123!', (string) $teacher->password));
        $this->assertTrue(Hash::check('TeacherReset123!', (string) $teacher->password_hash));

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $teacher->id,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'teacher@example.com',
            'password' => 'password123',
        ])->assertStatus(422);

        $this->postJson('/api/auth/login', [
            'email' => 'teacher@example.com',
            'password' => 'TeacherReset123!',
            'device_name' => 'phpunit',
        ])->assertOk();
    }

    public function test_admin_cannot_change_other_users_password_via_super_admin_endpoint(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $adminToken = $admin->createToken('phpunit-admin')->plainTextToken;

        $response = $this->withToken($adminToken)->postJson('/api/users/'.$teacher->id.'/change-password', [
            'new_password' => 'Denied123!',
            'new_password_confirmation' => 'Denied123!',
        ]);

        $response->assertStatus(403);
    }
}
