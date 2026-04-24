<?php

namespace Tests\Feature\Api;

use App\Models\Student;
use App\Models\User;
use App\Services\LeaveRequestTelegramNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaveRequestApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.leave_action_buttons_enabled' => true,
        ]);
    }

    public function test_student_can_submit_hourly_leave_request(): void
    {
        $this->seed();

        $studentUser = User::query()->where('email', 'student@example.com')->firstOrFail();
        $student = Student::query()->where('user_id', $studentUser->id)->firstOrFail();
        $subjectId = (int) DB::table('teacher_class')
            ->where('class_id', $student->class_id)
            ->value('subject_id');
        Sanctum::actingAs($studentUser);

        $response = $this->postJson('/api/leave-requests', [
            'subject_ids' => [$subjectId],
            'request_type' => 'hourly',
            'start_date' => '2026-03-12',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'reason' => 'Medical appointment',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.request_type', 'hourly');
    }

    public function test_student_submission_sends_private_telegram_to_parent_with_leave_details(): void
    {
        $this->seed();

        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.base_url' => 'https://api.telegram.org',
            'services.telegram.parse_mode' => '',
            'services.telegram.webhook_secret' => '',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 901]], 200),
        ]);

        $studentUser = User::query()->where('email', 'student@example.com')->firstOrFail();
        $student = Student::query()->where('user_id', $studentUser->id)->firstOrFail();
        $parent = $student->parents()->firstOrFail();
        $parent->forceFill(['telegram_chat_id' => '7469476859'])->save();

        $subjectId = (int) DB::table('teacher_class')
            ->where('class_id', $student->class_id)
            ->value('subject_id');

        Sanctum::actingAs($studentUser);
        $response = $this->postJson('/api/leave-requests', [
            'subject_ids' => [$subjectId],
            'request_type' => 'hourly',
            'start_date' => '2026-04-20',
            'start_time' => '17:44',
            'end_time' => '23:33',
            'reason' => 'dek',
            'phone' => '070747151',
        ]);

        $response->assertCreated();

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($studentUser): bool {
            $data = $request->data();
            $text = (string) ($data['text'] ?? '');

            return str_contains($request->url(), '/bottest-bot-token/sendMessage')
                && (string) ($data['chat_id'] ?? '') === '7469476859'
                && str_contains($text, 'សិស្ស : '.(string) $studentUser->name)
                && str_contains($text, 'អ្នកស្នើសុំ : '.(string) $studentUser->name)
                && str_contains($text, 'ចាប់ផ្តើមថ្ងៃ : 2026-04-20')
                && str_contains($text, 'បញ្ចប់ថ្ងៃ : 2026-04-20')
                && str_contains($text, 'ម៉ោង : 17:44 - 23:33')
                && str_contains($text, 'មូលហេតុ : dek')
                && str_contains($text, 'ថ្ងៃ : 1ថ្ងៃ')
                && ! str_contains((string) ($data['reply_markup'] ?? ''), 'callback_data');
        });
    }

    public function test_parent_can_submit_multi_day_leave_and_teacher_can_approve(): void
    {
        $this->seed();

        $parent = User::query()->where('email', 'parent@example.com')->firstOrFail();
        $student = $parent->children()->firstOrFail();
        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $this->assertSame('teacher', $teacher->role);

        $subjectId = (int) DB::table('teacher_class')
            ->where('class_id', $student->class_id)
            ->where('teacher_id', $teacher->id)
            ->value('subject_id');
        $this->assertGreaterThan(0, $subjectId);

        Sanctum::actingAs($parent);
        $submit = $this->postJson('/api/leave-requests', [
            'student_id' => $student->id,
            'subject_ids' => [$subjectId],
            'request_type' => 'multi_day',
            'start_date' => '2026-03-15',
            'end_date' => '2026-03-16',
            'return_date' => '2026-03-17',
            'total_days' => 2,
            'reason' => 'Family event',
        ]);

        $submit->assertCreated();
        $leaveRequestId = (int) $submit->json('data.id');
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->assertTrue(
            $teacher->teachingClasses()
                ->where('classes.id', $student->class_id)
                ->whereIn('teacher_class.subject_id', [$subjectId])
                ->exists()
        );
        $this->assertDatabaseHas('leave_request_recipients', [
            'leave_request_id' => $leaveRequestId,
            'user_id' => $teacher->id,
        ]);
        $this->assertDatabaseHas('leave_request_recipients', [
            'leave_request_id' => $leaveRequestId,
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseMissing('leave_request_recipients', [
            'leave_request_id' => $leaveRequestId,
            'user_id' => $parent->id,
        ]);

        Sanctum::actingAs($teacher);
        $approve = $this->patchJson("/api/leave-requests/{$leaveRequestId}/status", [
            'status' => 'approved',
        ]);

        $approve->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('notification', [
            'user_id' => $student->user_id,
            'title' => 'Leave request approved',
            'read_status' => 0,
        ]);

        $this->assertDatabaseHas('attendance', [
            'student_id' => $student->id,
            'class_id' => $student->class_id,
            'date' => '2026-03-15',
            'status' => 'L',
        ]);
        $this->assertDatabaseHas('attendance', [
            'student_id' => $student->id,
            'class_id' => $student->class_id,
            'date' => '2026-03-16',
            'status' => 'L',
        ]);
    }

    public function test_teacher_cannot_submit_leave_request(): void
    {
        $this->seed();

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        Sanctum::actingAs($teacher);

        $response = $this->postJson('/api/leave-requests', [
            'student_id' => Student::query()->value('id'),
            'subject_ids' => [DB::table('subjects')->value('id')],
            'request_type' => 'hourly',
            'start_date' => '2026-03-12',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'reason' => 'Not allowed',
        ]);

        $response->assertForbidden();
    }

    public function test_teacher_can_reject_leave_request_and_rejection_keeps_approver_identity(): void
    {
        $this->seed();

        $parent = User::query()->where('email', 'parent@example.com')->firstOrFail();
        $student = $parent->children()->firstOrFail();
        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();

        $subjectId = (int) DB::table('teacher_class')
            ->where('class_id', $student->class_id)
            ->where('teacher_id', $teacher->id)
            ->value('subject_id');
        $this->assertGreaterThan(0, $subjectId);

        Sanctum::actingAs($parent);
        $submit = $this->postJson('/api/leave-requests', [
            'student_id' => $student->id,
            'subject_ids' => [$subjectId],
            'request_type' => 'hourly',
            'start_date' => '2026-03-20',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'reason' => 'Clinic visit',
        ]);

        $submit->assertCreated();
        $leaveRequestId = (int) $submit->json('data.id');

        Sanctum::actingAs($teacher);
        $reject = $this->patchJson("/api/leave-requests/{$leaveRequestId}/status", [
            'status' => 'rejected',
        ]);

        $reject->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.approved_by', $teacher->id);

        $this->assertDatabaseHas('leaverequest', [
            'id' => $leaveRequestId,
            'status' => 'rejected',
            'approved_by' => $teacher->id,
        ]);

        $this->assertTrue(
            DB::table('notification')
                ->where('user_id', $student->user_id)
                ->where('title', 'Leave request rejected')
                ->where('content', 'like', '%'.$teacher->name.'%')
                ->exists()
        );
    }

    public function test_leave_request_submission_can_send_telegram_to_teacher_and_admin(): void
    {
        $this->seed();

        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.base_url' => 'https://api.telegram.org',
            'services.telegram.parse_mode' => '',
            'services.telegram.webhook_secret' => '',
            'services.telegram.group_chat_ids' => ['-1009876543210'],
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 345]], 200),
        ]);

        $parent = User::query()->where('email', 'parent@example.com')->firstOrFail();
        $student = $parent->children()->firstOrFail();
        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $teacher->forceFill(['telegram_chat_id' => '111111'])->save();
        $admin->forceFill(['telegram_chat_id' => '222222'])->save();

        $subjectId = (int) DB::table('teacher_class')
            ->where('class_id', $student->class_id)
            ->where('teacher_id', $teacher->id)
            ->value('subject_id');

        Sanctum::actingAs($parent);
        $this->postJson('/api/leave-requests', [
            'student_id' => $student->id,
            'subject_ids' => [$subjectId],
            'request_type' => 'hourly',
            'start_date' => '2026-03-25',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'reason' => 'Family reason',
        ])->assertCreated();

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            $data = $request->data();

            return str_contains($request->url(), '/bottest-bot-token/sendMessage')
                && (string) ($data['chat_id'] ?? '') === '111111'
                && str_contains((string) ($data['text'] ?? ''), 'សិស្ស : ')
                && str_contains((string) ($data['text'] ?? ''), 'អ្នកស្នើសុំ : ')
                && str_contains((string) ($data['reply_markup'] ?? ''), '"callback_data":"lr:')
                && str_contains((string) ($data['reply_markup'] ?? ''), '✅ Approve')
                && str_contains((string) ($data['reply_markup'] ?? ''), '❌ Not Approve');
        });

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            $data = $request->data();

            return str_contains($request->url(), '/bottest-bot-token/sendMessage')
                && (string) ($data['chat_id'] ?? '') === '222222'
                && str_contains((string) ($data['text'] ?? ''), 'មូលហេតុ : ');
        });

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            $data = $request->data();

            return str_contains($request->url(), '/bottest-bot-token/sendMessage')
                && (string) ($data['chat_id'] ?? '') === '-1009876543210'
                && str_contains((string) ($data['text'] ?? ''), 'ចាប់ផ្តើមថ្ងៃ : ')
                && str_contains((string) ($data['text'] ?? ''), 'ថ្ងៃ : ')
                && str_contains((string) ($data['reply_markup'] ?? ''), '"callback_data":"lrg:');
        });
    }

    public function test_leave_request_approval_can_send_telegram_to_submitter_and_student(): void
    {
        $this->seed();

        $parent = User::query()->where('email', 'parent@example.com')->firstOrFail();
        $student = $parent->children()->firstOrFail();
        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();

        $subjectId = (int) DB::table('teacher_class')
            ->where('class_id', $student->class_id)
            ->where('teacher_id', $teacher->id)
            ->value('subject_id');

        // Disable telegram during submission so we can assert approval stage only.
        config(['services.telegram.enabled' => false]);
        Sanctum::actingAs($parent);
        $submit = $this->postJson('/api/leave-requests', [
            'student_id' => $student->id,
            'subject_ids' => [$subjectId],
            'request_type' => 'hourly',
            'start_date' => '2026-03-26',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'reason' => 'Medical check',
        ])->assertCreated();

        $leaveRequestId = (int) $submit->json('data.id');

        $parent->forceFill(['telegram_chat_id' => '333333'])->save();
        $studentUser = User::query()->findOrFail((int) $student->user_id);
        $studentUser->forceFill(['telegram_chat_id' => '444444'])->save();

        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.base_url' => 'https://api.telegram.org',
            'services.telegram.parse_mode' => '',
            'services.telegram.webhook_secret' => '',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 567]], 200),
        ]);

        Sanctum::actingAs($teacher);
        $this->patchJson("/api/leave-requests/{$leaveRequestId}/status", [
            'status' => 'approved',
        ])->assertOk();

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            $data = $request->data();

            return str_contains($request->url(), '/bottest-bot-token/sendMessage')
                && (string) ($data['chat_id'] ?? '') === '333333'
                && str_contains((string) ($data['text'] ?? ''), 'Leave request approved');
        });

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            $data = $request->data();

            return str_contains($request->url(), '/bottest-bot-token/sendMessage')
                && (string) ($data['chat_id'] ?? '') === '444444'
                && str_contains((string) ($data['text'] ?? ''), 'Leave request approved');
        });
    }

    public function test_leave_request_approval_can_send_status_message_to_telegram_group(): void
    {
        $this->seed();

        $parent = User::query()->where('email', 'parent@example.com')->firstOrFail();
        $student = $parent->children()->firstOrFail();
        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();

        $subjectId = (int) DB::table('teacher_class')
            ->where('class_id', $student->class_id)
            ->where('teacher_id', $teacher->id)
            ->value('subject_id');

        config(['services.telegram.enabled' => false]);
        Sanctum::actingAs($parent);
        $submit = $this->postJson('/api/leave-requests', [
            'student_id' => $student->id,
            'subject_ids' => [$subjectId],
            'request_type' => 'hourly',
            'start_date' => '2026-03-26',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'reason' => 'Telegram group status update',
        ])->assertCreated();

        $leaveRequestId = (int) $submit->json('data.id');

        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.base_url' => 'https://api.telegram.org',
            'services.telegram.parse_mode' => '',
            'services.telegram.webhook_secret' => '',
            'services.telegram.group_chat_ids' => ['-1009876543210'],
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 568]], 200),
        ]);

        Sanctum::actingAs($teacher);
        $this->patchJson("/api/leave-requests/{$leaveRequestId}/status", [
            'status' => 'approved',
        ])->assertOk();

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            $data = $request->data();

            return str_contains($request->url(), '/bottest-bot-token/sendMessage')
                && (string) ($data['chat_id'] ?? '') === '-1009876543210'
                && str_contains((string) ($data['text'] ?? ''), 'Leave request approved')
                && str_contains((string) ($data['text'] ?? ''), 'ស្ថានភាព : ✅');
        });
    }

    public function test_telegram_webhook_callback_can_approve_leave_request(): void
    {
        $this->seed();

        $parent = User::query()->where('email', 'parent@example.com')->firstOrFail();
        $student = $parent->children()->firstOrFail();
        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $teacher->forceFill(['telegram_chat_id' => '111111'])->save();

        $subjectId = (int) DB::table('teacher_class')
            ->where('class_id', $student->class_id)
            ->where('teacher_id', $teacher->id)
            ->value('subject_id');

        config(['services.telegram.enabled' => false]);

        Sanctum::actingAs($parent);
        $submit = $this->postJson('/api/leave-requests', [
            'student_id' => $student->id,
            'subject_ids' => [$subjectId],
            'request_type' => 'hourly',
            'start_date' => '2026-03-27',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'reason' => 'Family matter',
        ])->assertCreated();

        $leaveRequestId = (int) $submit->json('data.id');

        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.base_url' => 'https://api.telegram.org',
            'services.telegram.parse_mode' => '',
            'services.telegram.webhook_secret' => '',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200),
        ]);

        $callbackData = app(LeaveRequestTelegramNotifier::class)
            ->buildActionCallbackData($leaveRequestId, (int) $teacher->id, 'approved');

        $this->postJson('/api/integrations/telegram/webhook', [
            'update_id' => 10001,
            'callback_query' => [
                'id' => 'cb-approve-1',
                'from' => [
                    'id' => 111111,
                    'is_bot' => false,
                    'first_name' => 'Teacher',
                ],
                'message' => [
                    'message_id' => 10,
                    'chat' => [
                        'id' => 111111,
                        'type' => 'private',
                    ],
                ],
                'data' => $callbackData,
            ],
        ])->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('leaverequest', [
            'id' => $leaveRequestId,
            'status' => 'approved',
            'approved_by' => $teacher->id,
        ]);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            $data = $request->data();

            return str_contains($request->url(), '/bottest-bot-token/answerCallbackQuery')
                && (string) ($data['callback_query_id'] ?? '') === 'cb-approve-1';
        });

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($leaveRequestId): bool {
            $data = $request->data();

            return str_contains($request->url(), '/bottest-bot-token/sendMessage')
                && (string) ($data['chat_id'] ?? '') === '111111'
                && str_contains((string) ($data['text'] ?? ''), '✅ Approved ID Request : '.$leaveRequestId);
        });
    }

    public function test_telegram_webhook_callback_rejects_when_chat_id_is_not_authorized(): void
    {
        $this->seed();

        $parent = User::query()->where('email', 'parent@example.com')->firstOrFail();
        $student = $parent->children()->firstOrFail();
        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $teacher->forceFill(['telegram_chat_id' => '111111'])->save();

        $subjectId = (int) DB::table('teacher_class')
            ->where('class_id', $student->class_id)
            ->where('teacher_id', $teacher->id)
            ->value('subject_id');

        config(['services.telegram.enabled' => false]);

        Sanctum::actingAs($parent);
        $submit = $this->postJson('/api/leave-requests', [
            'student_id' => $student->id,
            'subject_ids' => [$subjectId],
            'request_type' => 'hourly',
            'start_date' => '2026-03-28',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'reason' => 'Clinic',
        ])->assertCreated();

        $leaveRequestId = (int) $submit->json('data.id');

        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.base_url' => 'https://api.telegram.org',
            'services.telegram.webhook_secret' => '',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200),
        ]);

        $callbackData = app(LeaveRequestTelegramNotifier::class)
            ->buildActionCallbackData($leaveRequestId, (int) $teacher->id, 'rejected');

        $this->postJson('/api/integrations/telegram/webhook', [
            'update_id' => 10002,
            'callback_query' => [
                'id' => 'cb-reject-1',
                'from' => [
                    'id' => 999999,
                    'is_bot' => false,
                    'first_name' => 'Other User',
                ],
                'data' => $callbackData,
            ],
        ])->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('leaverequest', [
            'id' => $leaveRequestId,
            'status' => 'pending',
        ]);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            $data = $request->data();

            return str_contains($request->url(), '/bottest-bot-token/answerCallbackQuery')
                && (string) ($data['callback_query_id'] ?? '') === 'cb-reject-1'
                && str_contains((string) ($data['text'] ?? ''), 'Not authorized');
        });
    }

    public function test_telegram_group_callback_can_approve_leave_request_for_authorized_teacher(): void
    {
        $this->seed();

        $parent = User::query()->where('email', 'parent@example.com')->firstOrFail();
        $student = $parent->children()->firstOrFail();
        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $teacher->forceFill(['telegram_chat_id' => '111111'])->save();

        $subjectId = (int) DB::table('teacher_class')
            ->where('class_id', $student->class_id)
            ->where('teacher_id', $teacher->id)
            ->value('subject_id');

        config(['services.telegram.enabled' => false]);

        Sanctum::actingAs($parent);
        $submit = $this->postJson('/api/leave-requests', [
            'student_id' => $student->id,
            'subject_ids' => [$subjectId],
            'request_type' => 'hourly',
            'start_date' => '2026-03-29',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'reason' => 'Checkup',
        ])->assertCreated();

        $leaveRequestId = (int) $submit->json('data.id');

        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.base_url' => 'https://api.telegram.org',
            'services.telegram.parse_mode' => '',
            'services.telegram.webhook_secret' => '',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200),
        ]);

        $callbackData = app(LeaveRequestTelegramNotifier::class)
            ->buildGroupActionCallbackData($leaveRequestId, 'approved');

        $this->postJson('/api/integrations/telegram/webhook', [
            'update_id' => 10003,
            'callback_query' => [
                'id' => 'cb-group-approve-1',
                'from' => [
                    'id' => 111111,
                    'is_bot' => false,
                    'first_name' => 'Teacher',
                ],
                'message' => [
                    'message_id' => 11,
                    'chat' => [
                        'id' => -1003408032392,
                        'type' => 'supergroup',
                    ],
                ],
                'data' => $callbackData,
            ],
        ])->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('leaverequest', [
            'id' => $leaveRequestId,
            'status' => 'approved',
            'approved_by' => $teacher->id,
        ]);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            $data = $request->data();

            return str_contains($request->url(), '/bottest-bot-token/answerCallbackQuery')
                && (string) ($data['callback_query_id'] ?? '') === 'cb-group-approve-1';
        });

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            $data = $request->data();

            return str_contains($request->url(), '/bottest-bot-token/sendMessage')
                && (string) ($data['chat_id'] ?? '') === '-1003408032392'
                && str_contains((string) ($data['text'] ?? ''), '✅ Approved ID Request :');
        });
    }

    public function test_telegram_group_callback_can_approve_leave_request_when_any_approver_mode_is_enabled(): void
    {
        $this->seed();

        $parent = User::query()->where('email', 'parent@example.com')->firstOrFail();
        $student = $parent->children()->firstOrFail();
        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $teacher->forceFill(['telegram_chat_id' => null])->save();
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $admin->forceFill(['telegram_chat_id' => null])->save();

        $subjectId = (int) DB::table('teacher_class')
            ->where('class_id', $student->class_id)
            ->where('teacher_id', $teacher->id)
            ->value('subject_id');

        config(['services.telegram.enabled' => false]);

        Sanctum::actingAs($parent);
        $submit = $this->postJson('/api/leave-requests', [
            'student_id' => $student->id,
            'subject_ids' => [$subjectId],
            'request_type' => 'hourly',
            'start_date' => '2026-03-30',
            'start_time' => '11:00',
            'end_time' => '12:00',
            'reason' => 'Family event',
        ])->assertCreated();

        $leaveRequestId = (int) $submit->json('data.id');

        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.base_url' => 'https://api.telegram.org',
            'services.telegram.parse_mode' => '',
            'services.telegram.webhook_secret' => '',
            'services.telegram.group_allow_any_approver' => true,
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200),
        ]);

        $callbackData = app(LeaveRequestTelegramNotifier::class)
            ->buildGroupActionCallbackData($leaveRequestId, 'approved');

        $this->postJson('/api/integrations/telegram/webhook', [
            'update_id' => 10004,
            'callback_query' => [
                'id' => 'cb-group-approve-any-1',
                'from' => [
                    'id' => 7469476859,
                    'is_bot' => false,
                    'first_name' => 'GroupUser',
                ],
                'message' => [
                    'message_id' => 12,
                    'chat' => [
                        'id' => -1003408032392,
                        'type' => 'supergroup',
                    ],
                ],
                'data' => $callbackData,
            ],
        ])->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('leaverequest', [
            'id' => $leaveRequestId,
            'status' => 'approved',
            'approved_by' => $admin->id,
        ]);
    }

    public function test_legacy_group_button_payload_can_be_approved_when_any_approver_mode_is_enabled(): void
    {
        $this->seed();

        $parent = User::query()->where('email', 'parent@example.com')->firstOrFail();
        $student = $parent->children()->firstOrFail();
        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $teacher->forceFill(['telegram_chat_id' => null])->save();
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $admin->forceFill(['telegram_chat_id' => null])->save();

        $subjectId = (int) DB::table('teacher_class')
            ->where('class_id', $student->class_id)
            ->where('teacher_id', $teacher->id)
            ->value('subject_id');

        config(['services.telegram.enabled' => false]);

        Sanctum::actingAs($parent);
        $submit = $this->postJson('/api/leave-requests', [
            'student_id' => $student->id,
            'subject_ids' => [$subjectId],
            'request_type' => 'hourly',
            'start_date' => '2026-03-30',
            'start_time' => '13:00',
            'end_time' => '14:00',
            'reason' => 'Legacy callback data path',
        ])->assertCreated();

        $leaveRequestId = (int) $submit->json('data.id');

        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.base_url' => 'https://api.telegram.org',
            'services.telegram.parse_mode' => '',
            'services.telegram.webhook_secret' => '',
            'services.telegram.group_allow_any_approver' => true,
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200),
        ]);

        // Simulate an older group message that still carries direct callback format (lr:*).
        $legacyCallbackData = app(LeaveRequestTelegramNotifier::class)
            ->buildActionCallbackData($leaveRequestId, (int) $admin->id, 'approved');

        $this->postJson('/api/integrations/telegram/webhook', [
            'update_id' => 10005,
            'callback_query' => [
                'id' => 'cb-group-legacy-approve-any-1',
                'from' => [
                    'id' => 7469476859,
                    'is_bot' => false,
                    'first_name' => 'GroupUser',
                ],
                'message' => [
                    'message_id' => 13,
                    'chat' => [
                        'id' => -1003408032392,
                        'type' => 'supergroup',
                    ],
                ],
                'data' => $legacyCallbackData,
            ],
        ])->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('leaverequest', [
            'id' => $leaveRequestId,
            'status' => 'approved',
            'approved_by' => $admin->id,
        ]);
    }

    public function test_telegram_webhook_requires_secret_header_when_configured(): void
    {
        $this->seed();

        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.webhook_secret' => 'my-secret-token',
        ]);

        $this->postJson('/api/integrations/telegram/webhook', [
            'update_id' => 77,
        ])->assertForbidden();

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'my-secret-token')
            ->postJson('/api/integrations/telegram/webhook', [
                'update_id' => 78,
            ])->assertOk()
            ->assertJsonPath('ok', true);
    }
}
