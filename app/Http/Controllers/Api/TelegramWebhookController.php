<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\LeaveRequestStatusService;
use App\Services\LeaveRequestTelegramNotifier;
use App\Services\TelegramAccountLinkService;
use App\Services\TelegramBotClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

class TelegramWebhookController extends Controller
{
    public function handle(
        Request $request,
        LeaveRequestTelegramNotifier $leaveTelegramNotifier,
        LeaveRequestStatusService $leaveRequestStatusService,
        TelegramAccountLinkService $telegramAccountLinkService,
        TelegramBotClient $telegram,
    ): JsonResponse {
        if (! (bool) config('services.telegram.enabled', false)) {
            return response()->json(['ok' => true]);
        }

        if (! $this->isValidWebhookSecret($request)) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid webhook secret.',
            ], 403);
        }

        $payload = $request->json()->all();
        if (! is_array($payload)) {
            return response()->json(['ok' => true]);
        }

        if (isset($payload['callback_query']) && is_array($payload['callback_query'])) {
            $this->handleCallbackQuery(
                callbackQuery: $payload['callback_query'],
                leaveTelegramNotifier: $leaveTelegramNotifier,
                leaveRequestStatusService: $leaveRequestStatusService,
                telegram: $telegram,
            );
        } elseif (isset($payload['message']) && is_array($payload['message'])) {
            $this->handleIncomingMessage(
                message: $payload['message'],
                telegramAccountLinkService: $telegramAccountLinkService,
                telegram: $telegram,
            );
        }

        return response()->json(['ok' => true]);
    }

    /**
     * @param  array<string, mixed>  $callbackQuery
     */
    private function handleCallbackQuery(
        array $callbackQuery,
        LeaveRequestTelegramNotifier $leaveTelegramNotifier,
        LeaveRequestStatusService $leaveRequestStatusService,
        TelegramBotClient $telegram,
    ): void {
        $callbackQueryId = trim((string) ($callbackQuery['id'] ?? ''));
        if ($callbackQueryId === '') {
            return;
        }

        $actorChatId = trim((string) data_get($callbackQuery, 'from.id', ''));
        if ($actorChatId === '') {
            $actorChatId = trim((string) data_get($callbackQuery, 'message.chat.id', ''));
        }
        $messageChatId = trim((string) data_get($callbackQuery, 'message.chat.id', ''));

        $callbackData = trim((string) ($callbackQuery['data'] ?? ''));
        if ($callbackData === '') {
            $telegram->answerCallbackQuery($callbackQueryId, 'No action payload was provided.', true);

            return;
        }

        $actionPayload = $leaveTelegramNotifier->parseActionCallbackData($callbackData);
        if ($actionPayload === null) {
            $telegram->answerCallbackQuery($callbackQueryId, 'This action is invalid or expired.', true);

            return;
        }

        $leaveRequest = LeaveRequest::query()
            ->with(['student.user', 'student.parents', 'approver', 'recipients'])
            ->find((int) $actionPayload['leave_request_id']);
        if (! $leaveRequest) {
            $telegram->answerCallbackQuery($callbackQueryId, 'Leave request was not found.', true);

            return;
        }

        $actionScope = (string) ($actionPayload['action_scope'] ?? 'direct');
        $isGroupMessage = str_starts_with($messageChatId, '-');
        $recipientUser = null;
        if ($actionScope === 'group') {
            $recipientUser = User::query()
                ->where('telegram_chat_id', $actorChatId)
                ->first();

            if (! $recipientUser && (bool) config('services.telegram.group_allow_any_approver', false)) {
                $recipientUser = $this->resolveFallbackGroupApprover($leaveRequest);
            }

            if (! $recipientUser) {
                $telegram->answerCallbackQuery($callbackQueryId, 'Not authorized for this action.', true);

                return;
            }
        } else {
            $recipientUser = User::query()
                ->where('id', (int) ($actionPayload['recipient_user_id'] ?? 0))
                ->first();
            if (! $recipientUser || trim((string) $recipientUser->telegram_chat_id) !== $actorChatId) {
                $recipientUser = null;

                // Backward-compatibility: old buttons in group may still carry direct payload format.
                // If group-any-approver mode is enabled, allow resolving an approver from recipients.
                if ($isGroupMessage && (bool) config('services.telegram.group_allow_any_approver', false)) {
                    $recipientUser = User::query()
                        ->where('telegram_chat_id', $actorChatId)
                        ->first();

                    if (! $recipientUser) {
                        $recipientUser = $this->resolveFallbackGroupApprover($leaveRequest);
                    }
                }

                if (! $recipientUser) {
                    $telegram->answerCallbackQuery($callbackQueryId, 'Not authorized for this action.', true);

                    return;
                }
            }
        }

        if (! Gate::forUser($recipientUser)->allows('updateStatus', $leaveRequest)) {
            $telegram->answerCallbackQuery($callbackQueryId, 'You do not have permission for this request.', true);

            return;
        }

        if ($leaveRequest->status !== 'pending') {
            $telegram->answerCallbackQuery(
                $callbackQueryId,
                'This leave request is already '.(string) $leaveRequest->status.'.',
                true,
            );

            return;
        }

        $targetStatus = (string) $actionPayload['status'];

        try {
            $updatedLeaveRequest = $leaveRequestStatusService->updateStatus($leaveRequest, $targetStatus, $recipientUser);
            $telegram->answerCallbackQuery(
                $callbackQueryId,
                $targetStatus === 'approved' ? 'Leave request approved.' : 'Leave request rejected.',
            );

            $studentName = trim((string) data_get($updatedLeaveRequest, 'student.user.name', 'Student'));
            $deliveryChatId = $messageChatId !== '' ? $messageChatId : $actorChatId;
            $statusLine = $targetStatus === 'approved'
                ? '✅ Approved ID Request : '.(int) $updatedLeaveRequest->id
                : '❌ Not Approved ID Request : '.(int) $updatedLeaveRequest->id;

            $telegram->sendMessage(
                chatId: $deliveryChatId,
                text: implode("\n", [
                    $statusLine,
                    'សិស្ស : '.$studentName,
                ]),
                meta: [
                    'source' => 'telegram-webhook',
                    'event_type' => 'leave-request-status-processed',
                    'leave_request_id' => (int) $updatedLeaveRequest->id,
                    'actor_user_id' => (int) $recipientUser->id,
                ],
            );
        } catch (Throwable) {
            $telegram->answerCallbackQuery($callbackQueryId, 'Failed to process. Please try again.', true);
        }
    }

    private function resolveFallbackGroupApprover(LeaveRequest $leaveRequest): ?User
    {
        $adminLike = $leaveRequest->recipients
            ->first(fn (User $user): bool => in_array((string) $user->role, ['super-admin', 'admin'], true));
        if ($adminLike instanceof User) {
            return $adminLike;
        }

        $teacher = $leaveRequest->recipients
            ->first(fn (User $user): bool => (string) $user->role === 'teacher');

        return $teacher instanceof User ? $teacher : null;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function handleIncomingMessage(
        array $message,
        TelegramAccountLinkService $telegramAccountLinkService,
        TelegramBotClient $telegram,
    ): void
    {
        $text = trim((string) ($message['text'] ?? ''));
        $chatId = trim((string) data_get($message, 'chat.id', ''));
        $chatType = trim((string) data_get($message, 'chat.type', ''));

        if ($chatId === '' || $text === '') {
            return;
        }

        if ($chatType !== 'private') {
            $telegram->sendMessage(
                chatId: $chatId,
                text: "[Sala Digital]\nPlease chat with this bot in private to link your account.",
                meta: [
                    'source' => 'telegram-webhook',
                    'event_type' => 'non-private-chat-warning',
                ],
            );

            return;
        }

        [$rawCommand, $argument] = $this->parseCommandPayload($text);
        $command = $this->normalizeTelegramCommand($rawCommand);

        if ($command === '/link') {
            $this->handleLinkCommand(
                chatId: $chatId,
                code: $argument,
                telegramAccountLinkService: $telegramAccountLinkService,
                telegram: $telegram,
            );

            return;
        }

        if ($command === '/start' && trim($argument) !== '') {
            $this->handleLinkCommand(
                chatId: $chatId,
                code: $argument,
                telegramAccountLinkService: $telegramAccountLinkService,
                telegram: $telegram,
            );

            return;
        }

        if ($command === '/profile') {
            $this->handleProfileCommand(
                chatId: $chatId,
                telegram: $telegram,
            );

            return;
        }

        if ($command === '/menu' || in_array($command, ['/start', '/chatid', '/help'], true) || $command === '') {
            $this->sendWelcomeMessage(
                chatId: $chatId,
                telegram: $telegram,
            );

            return;
        }

        if (str_starts_with($command, '/')) {
            $this->sendWelcomeMessage(
                chatId: $chatId,
                telegram: $telegram,
            );

            return;
        }

        $this->sendWelcomeMessage(
            chatId: $chatId,
            telegram: $telegram,
        );
    }

    private function sendWelcomeMessage(
        string $chatId,
        TelegramBotClient $telegram,
    ): void {
        $linkedUser = $this->resolveLinkedUserByChatId($chatId);
        $lines = [
            'សូមស្វាគមន៍មកកាន់ប្រព័ន្ធ Sala Digital!',
        ];

        if ($linkedUser) {
            $roleName = $this->roleLabel((string) $linkedUser->role);
            $accountCode = trim((string) ($linkedUser->user_code ?: $linkedUser->id));
            $lines[] = 'សួស្តី: '.trim((string) $linkedUser->name);
            $lines[] = 'តួនាទី: '.$roleName;
            $lines[] = 'លេខសម្គាល់គណនី: '.$accountCode;
            $lines[] = 'ថ្ងៃ/ម៉ោងចូល: '.now()->format('m/d/Y, g:i:s A');
            $lines[] = 'ប្រើ /profile ដើម្បីមើលព័ត៌មានលម្អិត។';
        } else {
            $lines[] = 'Telegram bot is connected.';
            $lines[] = 'Your Chat ID: '.$chatId;
            $lines[] = 'To link account automatically: /link YOUR_CODE';
            $lines[] = 'Tip: /start CODE also works.';
        }

        $lines[] = 'បញ្ជីមុខងារ: /menu, /profile, /chatid, /help';

        $telegram->sendMessage(
            chatId: $chatId,
            text: implode("\n", $lines),
            meta: [
                'source' => 'telegram-webhook',
                'event_type' => 'welcome-help',
            ],
            replyMarkup: $this->defaultReplyKeyboard(),
        );
    }

    private function handleProfileCommand(
        string $chatId,
        TelegramBotClient $telegram,
    ): void {
        $user = $this->resolveLinkedUserByChatId($chatId, true);
        if (! $user) {
            $telegram->sendMessage(
                chatId: $chatId,
                text: implode("\n", [
                    'គណនី Telegram នេះមិនទាន់បានភ្ជាប់ទេ។',
                    'សូមប្រើ: /link YOUR_CODE',
                ]),
                meta: [
                    'source' => 'telegram-webhook',
                    'event_type' => 'profile-link-required',
                ],
                replyMarkup: $this->defaultReplyKeyboard(),
            );

            return;
        }

        $roleName = $this->roleLabel((string) $user->role);
        $lines = [
            'ព័ត៌មានគណនី',
            'គណនី: '.trim((string) $user->name),
            'តួនាទី: '.$roleName,
            'លេខកូដ: '.trim((string) ($user->user_code ?: $user->id)),
            'ទូរស័ព្ទ: '.(trim((string) $user->phone) !== '' ? trim((string) $user->phone) : '-'),
        ];

        if (in_array((string) $user->role, ['parent', 'guardian'], true)) {
            $children = $user->children ?? collect();
            if ($children->isNotEmpty()) {
                $lines[] = 'កូន:';
                foreach ($children as $child) {
                    $childName = trim((string) data_get($child, 'user.name', 'Student'));
                    $className = trim((string) data_get($child, 'class.class_name', ''));
                    if ($className === '') {
                        $className = trim((string) data_get($child, 'class.name', ''));
                    }
                    if ($className === '') {
                        $className = '-';
                    }

                    $lines[] = '• '.$childName.' ('.$className.')';
                }
            } else {
                $lines[] = 'កូន: -';
            }
        }

        $lines[] = 'Your Chat ID: '.$chatId;

        $telegram->sendMessage(
            chatId: $chatId,
            text: implode("\n", $lines),
            meta: [
                'source' => 'telegram-webhook',
                'event_type' => 'profile',
                'user_id' => (int) $user->id,
            ],
            replyMarkup: $this->defaultReplyKeyboard(),
        );
    }

    private function handleLinkCommand(
        string $chatId,
        string $code,
        TelegramAccountLinkService $telegramAccountLinkService,
        TelegramBotClient $telegram,
    ): void {
        if (trim($code) === '') {
            $telegram->sendMessage(
                chatId: $chatId,
                text: implode("\n", [
                    '[Sala Digital]',
                    'Missing link code.',
                    'Use: /link YOUR_CODE',
                ]),
                meta: [
                    'source' => 'telegram-webhook',
                    'event_type' => 'link-code-missing',
                ],
            );

            return;
        }

        $result = $telegramAccountLinkService->consumeLinkCode($code, $chatId);
        $status = (string) ($result['status'] ?? 'invalid_code');

        if ($status === 'linked' && isset($result['user']) && $result['user'] instanceof User) {
            /** @var User $linkedUser */
            $linkedUser = $result['user'];

            $telegram->sendMessage(
                chatId: $chatId,
                text: implode("\n", [
                    '[Sala Digital]',
                    'Telegram account linked successfully.',
                    'Name: '.trim((string) $linkedUser->name),
                    'Role: '.trim((string) $linkedUser->role),
                ]),
                meta: [
                    'source' => 'telegram-webhook',
                    'event_type' => 'link-code-success',
                    'user_id' => (int) $linkedUser->id,
                ],
            );

            return;
        }

        $errorText = match ($status) {
            'already_used' => 'This link code was already used.',
            'expired' => 'This link code has expired. Please generate a new one in the app.',
            'chat_in_use' => 'This Telegram account is already linked to another user.',
            default => 'Invalid link code. Please check and try again.',
        };

        $telegram->sendMessage(
            chatId: $chatId,
            text: implode("\n", [
                '[Sala Digital]',
                $errorText,
            ]),
            meta: [
                'source' => 'telegram-webhook',
                'event_type' => 'link-code-failed',
                'status' => $status,
            ],
        );
    }

    /**
     * @return array{0:string,1:string}
     */
    private function parseCommandPayload(string $text): array
    {
        $parts = preg_split('/\s+/', trim($text), 2) ?: [];
        $command = strtolower(trim((string) ($parts[0] ?? '')));
        $argument = trim((string) ($parts[1] ?? ''));

        return [$command, $argument];
    }

    private function normalizeTelegramCommand(string $command): string
    {
        $normalized = strtolower(trim($command));
        if ($normalized === '') {
            return '';
        }

        if (! str_starts_with($normalized, '/')) {
            return '';
        }

        if (str_contains($normalized, '@')) {
            $normalized = (string) explode('@', $normalized, 2)[0];
        }

        return $normalized;
    }

    private function roleLabel(string $role): string
    {
        return match (trim(strtolower($role))) {
            'super-admin' => 'Super Admin',
            'admin' => 'Admin',
            'teacher' => 'Teacher',
            'student' => 'Student',
            'parent', 'guardian' => 'Parent',
            default => 'User',
        };
    }

    private function resolveLinkedUserByChatId(string $chatId, bool $loadChildren = false): ?User
    {
        $normalizedChatId = trim($chatId);
        if ($normalizedChatId === '') {
            return null;
        }

        $query = User::query()->where('telegram_chat_id', $normalizedChatId);
        if ($loadChildren) {
            $query->with([
                'children.user:id,name',
                'children.class:id,class_name,name',
            ]);
        }

        return $query->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultReplyKeyboard(): array
    {
        return [
            'keyboard' => [
                [
                    ['text' => '/menu'],
                    ['text' => '/profile'],
                ],
                [
                    ['text' => '/chatid'],
                    ['text' => '/help'],
                ],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    private function isValidWebhookSecret(Request $request): bool
    {
        $secret = trim((string) config('services.telegram.webhook_secret', ''));
        if ($secret === '') {
            return true;
        }

        $provided = trim((string) $request->header('X-Telegram-Bot-Api-Secret-Token', ''));

        return $provided !== '' && hash_equals($secret, $provided);
    }
}
