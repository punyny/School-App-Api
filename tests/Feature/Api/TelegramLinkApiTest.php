<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TelegramLinkApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.webhook_secret' => '',
        ]);
    }

    public function test_authenticated_user_can_generate_telegram_link_code(): void
    {
        $this->seed();

        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => 'test-bot-token',
        ]);

        $student = User::query()->where('email', 'student@example.com')->firstOrFail();
        Sanctum::actingAs($student);

        $response = $this->postJson('/api/integrations/telegram/link-code');

        $response->assertCreated()
            ->assertJsonPath('message', 'Telegram link code generated.')
            ->assertJsonPath('data.expires_in_minutes', 15);

        $code = (string) $response->json('data.code');
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $code);
        $this->assertSame('/link '.$code, (string) $response->json('data.command'));

        $this->assertDatabaseHas('telegram_link_tokens', [
            'user_id' => (int) $student->id,
            'consumed_at' => null,
        ]);
    }

    public function test_telegram_webhook_link_command_can_bind_chat_id_to_user(): void
    {
        $this->seed();

        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.base_url' => 'https://api.telegram.org',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 999]], 200),
        ]);

        $student = User::query()->where('email', 'student@example.com')->firstOrFail();
        Sanctum::actingAs($student);
        $issue = $this->postJson('/api/integrations/telegram/link-code')->assertCreated();
        $code = (string) $issue->json('data.code');

        $this->postJson('/api/integrations/telegram/webhook', [
            'update_id' => 2001,
            'message' => [
                'message_id' => 1,
                'chat' => [
                    'id' => 7469476859,
                    'type' => 'private',
                ],
                'text' => '/link '.$code,
            ],
        ])->assertOk()->assertJsonPath('ok', true);

        $student->refresh();
        $this->assertSame('7469476859', (string) $student->telegram_chat_id);

        $this->assertTrue(
            DB::table('telegram_link_tokens')
                ->where('user_id', (int) $student->id)
                ->where('consumed_chat_id', '7469476859')
                ->whereNotNull('consumed_at')
                ->exists()
        );

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            $data = $request->data();

            return str_contains($request->url(), '/bottest-bot-token/sendMessage')
                && (string) ($data['chat_id'] ?? '') === '7469476859'
                && str_contains((string) ($data['text'] ?? ''), 'linked successfully');
        });
    }

    public function test_telegram_webhook_link_command_rejects_invalid_code(): void
    {
        $this->seed();

        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.base_url' => 'https://api.telegram.org',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1000]], 200),
        ]);

        $this->postJson('/api/integrations/telegram/webhook', [
            'update_id' => 2002,
            'message' => [
                'message_id' => 2,
                'chat' => [
                    'id' => 7469476859,
                    'type' => 'private',
                ],
                'text' => '/link WRONGCODE',
            ],
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertFalse(
            User::query()->whereNotNull('telegram_chat_id')->exists()
        );

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            $data = $request->data();

            return str_contains($request->url(), '/bottest-bot-token/sendMessage')
                && str_contains((string) ($data['text'] ?? ''), 'Invalid link code');
        });
    }

    public function test_telegram_webhook_link_command_rejects_when_chat_id_belongs_to_other_user(): void
    {
        $this->seed();

        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.base_url' => 'https://api.telegram.org',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1001]], 200),
        ]);

        $teacher = User::query()->where('email', 'teacher@example.com')->firstOrFail();
        $teacher->forceFill(['telegram_chat_id' => '555555'])->save();

        $student = User::query()->where('email', 'student@example.com')->firstOrFail();
        Sanctum::actingAs($student);
        $issue = $this->postJson('/api/integrations/telegram/link-code')->assertCreated();
        $code = (string) $issue->json('data.code');

        $this->postJson('/api/integrations/telegram/webhook', [
            'update_id' => 2003,
            'message' => [
                'message_id' => 3,
                'chat' => [
                    'id' => 555555,
                    'type' => 'private',
                ],
                'text' => '/link '.$code,
            ],
        ])->assertOk()->assertJsonPath('ok', true);

        $student->refresh();
        $this->assertNull($student->telegram_chat_id);

        $this->assertTrue(
            DB::table('telegram_link_tokens')
                ->where('user_id', (int) $student->id)
                ->whereNull('consumed_at')
                ->exists()
        );

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            $data = $request->data();

            return str_contains($request->url(), '/bottest-bot-token/sendMessage')
                && str_contains((string) ($data['text'] ?? ''), 'already linked to another user');
        });
    }

    public function test_telegram_webhook_start_command_returns_chat_id_for_parent(): void
    {
        $this->seed();

        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.base_url' => 'https://api.telegram.org',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1002]], 200),
        ]);

        $this->postJson('/api/integrations/telegram/webhook', [
            'update_id' => 2004,
            'message' => [
                'message_id' => 4,
                'chat' => [
                    'id' => 7469476859,
                    'type' => 'private',
                ],
                'text' => '/start@school_managementbot',
            ],
        ])->assertOk()->assertJsonPath('ok', true);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            $data = $request->data();

            return str_contains($request->url(), '/bottest-bot-token/sendMessage')
                && (string) ($data['chat_id'] ?? '') === '7469476859'
                && str_contains((string) ($data['text'] ?? ''), 'Your Chat ID: 7469476859');
        });
    }

    public function test_telegram_webhook_plain_text_message_returns_chat_id_help(): void
    {
        $this->seed();

        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.base_url' => 'https://api.telegram.org',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1003]], 200),
        ]);

        $this->postJson('/api/integrations/telegram/webhook', [
            'update_id' => 2005,
            'message' => [
                'message_id' => 5,
                'chat' => [
                    'id' => 7469476859,
                    'type' => 'private',
                ],
                'text' => 'hi',
            ],
        ])->assertOk()->assertJsonPath('ok', true);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            $data = $request->data();

            return str_contains($request->url(), '/bottest-bot-token/sendMessage')
                && (string) ($data['chat_id'] ?? '') === '7469476859'
                && str_contains((string) ($data['text'] ?? ''), 'Your Chat ID: 7469476859');
        });
    }

    public function test_telegram_webhook_profile_command_returns_parent_profile_and_children(): void
    {
        $this->seed();

        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.base_url' => 'https://api.telegram.org',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1004]], 200),
        ]);

        $parent = User::query()->where('email', 'parent@example.com')->firstOrFail();
        $child = $parent->children()->with('user')->firstOrFail();
        $parent->forceFill([
            'telegram_chat_id' => '7469476859',
            'user_code' => '156B33351',
            'phone' => '070747151',
        ])->save();

        $this->postJson('/api/integrations/telegram/webhook', [
            'update_id' => 2006,
            'message' => [
                'message_id' => 6,
                'chat' => [
                    'id' => 7469476859,
                    'type' => 'private',
                ],
                'text' => '/profile',
            ],
        ])->assertOk()->assertJsonPath('ok', true);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($parent, $child): bool {
            $data = $request->data();
            $text = (string) ($data['text'] ?? '');

            return str_contains($request->url(), '/bottest-bot-token/sendMessage')
                && (string) ($data['chat_id'] ?? '') === '7469476859'
                && str_contains($text, 'គណនី: '.(string) $parent->name)
                && str_contains($text, 'លេខកូដ: 156B33351')
                && str_contains($text, '• '.(string) ($child->user?->name ?? ''));
        });
    }

    public function test_telegram_webhook_start_command_shows_linked_parent_summary(): void
    {
        $this->seed();

        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.base_url' => 'https://api.telegram.org',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1005]], 200),
        ]);

        $parent = User::query()->where('email', 'parent@example.com')->firstOrFail();
        $parent->forceFill([
            'telegram_chat_id' => '7469476859',
            'user_code' => '156B33351',
        ])->save();

        $this->postJson('/api/integrations/telegram/webhook', [
            'update_id' => 2007,
            'message' => [
                'message_id' => 7,
                'chat' => [
                    'id' => 7469476859,
                    'type' => 'private',
                ],
                'text' => '/start',
            ],
        ])->assertOk()->assertJsonPath('ok', true);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($parent): bool {
            $data = $request->data();
            $text = (string) ($data['text'] ?? '');

            return str_contains($request->url(), '/bottest-bot-token/sendMessage')
                && (string) ($data['chat_id'] ?? '') === '7469476859'
                && str_contains($text, 'សួស្តី: '.(string) $parent->name)
                && str_contains($text, 'លេខសម្គាល់គណនី: 156B33351')
                && str_contains($text, 'តួនាទី: Parent');
        });
    }
}
