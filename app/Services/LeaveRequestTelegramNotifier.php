<?php

namespace App\Services;

use App\Jobs\SendTelegramMessageJob;
use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestTelegramNotifier
{
    /**
     * @param  array<int, int>  $recipientIds
     */
    public function sendPendingApprovalNotifications(
        LeaveRequest $leaveRequest,
        array $recipientIds,
        string $title,
        string $content,
    ): void {
        if (! (bool) config('services.telegram.enabled', false)) {
            return;
        }

        $users = $this->resolveTelegramUsers($recipientIds);
        $leaveRequest->loadMissing(['student.user', 'student.class.school', 'submitter']);
        $actionButtonsEnabled = (bool) config('services.telegram.leave_action_buttons_enabled', true);

        $reviewUrl = rtrim((string) config('app.url', ''), '/').'/panel/leave-requests';
        $leaveRequestId = (int) $leaveRequest->id;

        if ($users->isNotEmpty()) {
            foreach ($users as $user) {
                $text = $this->buildPendingApprovalTelegramText(
                    leaveRequest: $leaveRequest,
                    title: $title,
                    content: $content,
                    recipientName: (string) ($user->name ?? ''),
                    reviewUrl: $reviewUrl,
                );
                $replyMarkup = null;

                if ($actionButtonsEnabled) {
                    $approveData = $this->buildActionCallbackData($leaveRequestId, (int) $user->id, 'approved');
                    $rejectData = $this->buildActionCallbackData($leaveRequestId, (int) $user->id, 'rejected');
                    if ($approveData === '' || $rejectData === '') {
                        continue;
                    }

                    $replyMarkup = [
                        'inline_keyboard' => [
                            [
                                ['text' => '✅ Approve', 'callback_data' => $approveData],
                                ['text' => '❌ Not Approve', 'callback_data' => $rejectData],
                            ],
                        ],
                    ];
                }

                $this->dispatchTelegramMessage(
                    chatId: (string) $user->telegram_chat_id,
                    text: $text,
                    meta: [
                        'source' => 'leave-request',
                        'event_type' => 'pending-approval',
                        'leave_request_id' => $leaveRequestId,
                        'recipient_user_id' => (int) $user->id,
                    ],
                    replyMarkup: $replyMarkup,
                );
            }
        }

        $this->sendPendingApprovalGroupNotifications(
            leaveRequest: $leaveRequest,
            title: $title,
            content: $content,
            reviewUrl: $reviewUrl,
            leaveRequestId: $leaveRequestId,
        );
    }

    /**
     * @param  array<int, int>  $recipientIds
     */
    public function sendStatusUpdateNotifications(
        array $recipientIds,
        string $title,
        string $content,
        int $leaveRequestId,
    ): void {
        if (! (bool) config('services.telegram.enabled', false)) {
            return;
        }

        $reviewUrl = rtrim((string) config('app.url', ''), '/').'/panel/leave-requests';
        $leaveRequest = LeaveRequest::query()
            ->with(['student.user', 'student.class.school', 'submitter', 'approver'])
            ->find($leaveRequestId);

        $users = $this->resolveTelegramUsers($recipientIds);
        foreach ($users as $user) {
            $text = $leaveRequest
                ? $this->buildStatusUpdateTelegramText($leaveRequest, $title, $content)
                : $this->buildLeaveTelegramText(
                    title: $title,
                    content: $content,
                    recipientName: (string) ($user->name ?? ''),
                    reviewUrl: $reviewUrl,
                    leaveRequestId: $leaveRequestId,
                );

            $this->dispatchTelegramMessage(
                chatId: (string) $user->telegram_chat_id,
                text: $text,
                meta: [
                    'source' => 'leave-request',
                    'event_type' => 'status-updated',
                    'leave_request_id' => $leaveRequestId,
                    'recipient_user_id' => (int) $user->id,
                ],
            );
        }

        if ($leaveRequest) {
            $this->sendStatusUpdateGroupNotifications($leaveRequest, $title, $content, $leaveRequestId);
        }
    }

    /**
     * @param  array<int, int>  $recipientIds
     */
    public function sendParentSubmissionNotifications(
        LeaveRequest $leaveRequest,
        array $recipientIds,
    ): void {
        if (! (bool) config('services.telegram.enabled', false)) {
            return;
        }

        $users = $this->resolveTelegramUsers($recipientIds);
        if ($users->isEmpty()) {
            return;
        }

        $leaveRequest->loadMissing(['student.user', 'student.class.school', 'submitter']);
        foreach ($users as $user) {
            $text = $this->buildPendingApprovalTelegramText(
                leaveRequest: $leaveRequest,
                title: 'Leave request submitted',
                content: 'Parent notification',
                recipientName: (string) ($user->name ?? ''),
                reviewUrl: '',
            );

            $this->dispatchTelegramMessage(
                chatId: (string) $user->telegram_chat_id,
                text: $text,
                meta: [
                    'source' => 'leave-request',
                    'event_type' => 'submitted-parent-private',
                    'leave_request_id' => (int) $leaveRequest->id,
                    'recipient_user_id' => (int) $user->id,
                ],
            );
        }
    }

    public function buildActionCallbackData(int $leaveRequestId, int $recipientUserId, string $status): string
    {
        $actionCode = $this->actionCodeForStatus($status);
        if ($actionCode === null) {
            return '';
        }

        if ($leaveRequestId <= 0 || $recipientUserId <= 0) {
            return '';
        }

        $expiresAt = now()
            ->addMinutes($this->actionTtlMinutes())
            ->timestamp;
        $signature = $this->actionSignature(
            actionCode: $actionCode,
            leaveRequestId: $leaveRequestId,
            recipientUserId: $recipientUserId,
            expiresAt: $expiresAt,
        );

        return implode(':', [
            'lr',
            $actionCode,
            (string) $leaveRequestId,
            (string) $recipientUserId,
            (string) $expiresAt,
            $signature,
        ]);
    }

    public function buildGroupActionCallbackData(int $leaveRequestId, string $status): string
    {
        $actionCode = $this->actionCodeForStatus($status);
        if ($actionCode === null || $leaveRequestId <= 0) {
            return '';
        }

        $expiresAt = now()
            ->addMinutes($this->actionTtlMinutes())
            ->timestamp;
        $signature = $this->actionSignature(
            actionCode: $actionCode,
            leaveRequestId: $leaveRequestId,
            recipientUserId: null,
            expiresAt: $expiresAt,
        );

        return implode(':', [
            'lrg',
            $actionCode,
            (string) $leaveRequestId,
            (string) $expiresAt,
            $signature,
        ]);
    }

    /**
     * @return array{
     *   leave_request_id:int,
     *   recipient_user_id:int|null,
     *   status:string,
     *   expires_at:int,
     *   action_scope:string
     * }|null
     */
    public function parseActionCallbackData(string $callbackData): ?array
    {
        $parts = explode(':', trim($callbackData));
        if (count($parts) === 6 && $parts[0] === 'lr') {
            [, $actionCode, $leaveRequestRaw, $recipientRaw, $expiresRaw, $signature] = $parts;

            if (
                ! ctype_digit($leaveRequestRaw)
                || ! ctype_digit($recipientRaw)
                || ! ctype_digit($expiresRaw)
                || trim($signature) === ''
            ) {
                return null;
            }

            $leaveRequestId = (int) $leaveRequestRaw;
            $recipientUserId = (int) $recipientRaw;
            $expiresAt = (int) $expiresRaw;

            if ($leaveRequestId <= 0 || $recipientUserId <= 0 || $expiresAt < now()->timestamp) {
                return null;
            }

            $expected = $this->actionSignature(
                actionCode: $actionCode,
                leaveRequestId: $leaveRequestId,
                recipientUserId: $recipientUserId,
                expiresAt: $expiresAt,
            );

            if (! hash_equals($expected, $signature)) {
                return null;
            }

            $status = $this->statusForActionCode($actionCode);
            if ($status === null) {
                return null;
            }

            return [
                'leave_request_id' => $leaveRequestId,
                'recipient_user_id' => $recipientUserId,
                'status' => $status,
                'expires_at' => $expiresAt,
                'action_scope' => 'direct',
            ];
        }

        if (count($parts) !== 5 || $parts[0] !== 'lrg') {
            return null;
        }

        [, $actionCode, $leaveRequestRaw, $expiresRaw, $signature] = $parts;

        if (
            ! ctype_digit($leaveRequestRaw)
            || ! ctype_digit($expiresRaw)
            || trim($signature) === ''
        ) {
            return null;
        }

        $leaveRequestId = (int) $leaveRequestRaw;
        $expiresAt = (int) $expiresRaw;

        if ($leaveRequestId <= 0 || $expiresAt < now()->timestamp) {
            return null;
        }

        $expected = $this->actionSignature(
            actionCode: $actionCode,
            leaveRequestId: $leaveRequestId,
            recipientUserId: null,
            expiresAt: $expiresAt,
        );

        if (! hash_equals($expected, $signature)) {
            return null;
        }

        $status = $this->statusForActionCode($actionCode);
        if ($status === null) {
            return null;
        }

        return [
            'leave_request_id' => $leaveRequestId,
            'recipient_user_id' => null,
            'status' => $status,
            'expires_at' => $expiresAt,
            'action_scope' => 'group',
        ];
    }

    private function buildLeaveTelegramText(
        string $title,
        string $content,
        string $recipientName,
        string $reviewUrl,
        int $leaveRequestId,
    ): string {
        $lines = [
            '[Sala Digital]',
            'Leave request update',
            'Leave ID: #'.$leaveRequestId,
            'Title: '.trim($title),
            'Content: '.trim(mb_strimwidth($content, 0, 800, '...')),
        ];

        if (trim($recipientName) !== '') {
            $lines[] = 'To: '.trim($recipientName);
        }

        if (trim($reviewUrl) !== '') {
            $lines[] = 'Review: '.$reviewUrl;
        }

        return implode("\n", $lines);
    }

    private function buildPendingApprovalTelegramText(
        LeaveRequest $leaveRequest,
        string $title,
        string $content,
        string $recipientName,
        string $reviewUrl,
    ): string {
        $studentName = trim((string) data_get($leaveRequest, 'student.user.name', ''));
        $className = trim((string) data_get($leaveRequest, 'student.class.class_name', ''));
        if ($className === '') {
            $className = trim((string) data_get($leaveRequest, 'student.class.name', ''));
        }
        if ($className === '') {
            $className = trim((string) data_get($leaveRequest, 'student.class.grade_level', ''));
        }
        $schoolName = trim((string) data_get($leaveRequest, 'student.class.school.name', ''));
        $submitterName = trim((string) data_get($leaveRequest, 'submitter.name', ''));
        $submitterPhone = trim((string) data_get($leaveRequest, 'submitter.phone', ''));
        $reason = trim((string) ($leaveRequest->reason ?? ''));
        $startDate = $this->displayDate($leaveRequest->start_date);
        $endDate = $this->displayDate($leaveRequest->end_date ?: $leaveRequest->start_date);
        $days = max(1, (int) ($leaveRequest->total_days ?? 1));
        $timeRange = $this->displayTimeRange($leaveRequest->start_time, $leaveRequest->end_time);

        $lines = [
            'សិស្ស : '.($studentName !== '' ? $studentName : '-'),
            'ថ្នាក់ : '.($className !== '' ? $className : '-'),
            'សាលា : '.($schoolName !== '' ? $schoolName : '-'),
            'អ្នកស្នើសុំ : '.($submitterName !== '' ? $submitterName : '-'),
            'ចាប់ផ្តើមថ្ងៃ : '.$startDate,
            'បញ្ចប់ថ្ងៃ : '.$endDate,
            'ម៉ោង : '.($timeRange !== '' ? $timeRange : '-'),
            'លេខទូរស័ព្ទ : '.($submitterPhone !== '' ? $submitterPhone : '-'),
            'មូលហេតុ : '.($reason !== '' ? mb_strimwidth($reason, 0, 800, '...') : trim(mb_strimwidth($content, 0, 800, '...'))),
            'ថ្ងៃ : '.$days.'ថ្ងៃ',
        ];

        return implode("\n", $lines);
    }

    private function sendPendingApprovalGroupNotifications(
        LeaveRequest $leaveRequest,
        string $title,
        string $content,
        string $reviewUrl,
        int $leaveRequestId,
    ): void {
        $groupChatIds = $this->resolveTelegramGroupChatIds();
        if ($groupChatIds === []) {
            return;
        }

        $actionButtonsEnabled = (bool) config('services.telegram.leave_action_buttons_enabled', true);
        $replyMarkup = null;
        if ($actionButtonsEnabled) {
            $approveData = $this->buildGroupActionCallbackData($leaveRequestId, 'approved');
            $rejectData = $this->buildGroupActionCallbackData($leaveRequestId, 'rejected');
            if ($approveData === '' || $rejectData === '') {
                return;
            }

            $replyMarkup = [
                'inline_keyboard' => [
                    [
                        ['text' => '✅ Approve', 'callback_data' => $approveData],
                        ['text' => '❌ Not Approve', 'callback_data' => $rejectData],
                    ],
                ],
            ];
        }

        $text = $this->buildPendingApprovalTelegramText(
            leaveRequest: $leaveRequest,
            title: $title,
            content: $content,
            recipientName: 'Telegram Group',
            reviewUrl: $reviewUrl,
        );

        foreach ($groupChatIds as $groupChatId) {
            $this->dispatchTelegramMessage(
                chatId: $groupChatId,
                text: $text,
                meta: [
                    'source' => 'leave-request',
                    'event_type' => 'pending-approval-group',
                    'leave_request_id' => $leaveRequestId,
                    'group_chat_id' => $groupChatId,
                ],
                replyMarkup: $replyMarkup,
            );
        }
    }

    private function sendStatusUpdateGroupNotifications(
        LeaveRequest $leaveRequest,
        string $title,
        string $content,
        int $leaveRequestId,
    ): void {
        $groupChatIds = $this->resolveTelegramGroupChatIds();
        if ($groupChatIds === []) {
            return;
        }

        $text = $this->buildStatusUpdateTelegramText($leaveRequest, $title, $content);

        foreach ($groupChatIds as $groupChatId) {
            $this->dispatchTelegramMessage(
                chatId: $groupChatId,
                text: $text,
                meta: [
                    'source' => 'leave-request',
                    'event_type' => 'status-updated-group',
                    'leave_request_id' => $leaveRequestId,
                    'group_chat_id' => $groupChatId,
                ],
            );
        }
    }

    private function buildStatusUpdateTelegramText(
        LeaveRequest $leaveRequest,
        string $title,
        string $content,
    ): string {
        $studentName = trim((string) data_get($leaveRequest, 'student.user.name', ''));
        $className = trim((string) data_get($leaveRequest, 'student.class.class_name', ''));
        if ($className === '') {
            $className = trim((string) data_get($leaveRequest, 'student.class.name', ''));
        }
        if ($className === '') {
            $className = trim((string) data_get($leaveRequest, 'student.class.grade_level', ''));
        }
        $schoolName = trim((string) data_get($leaveRequest, 'student.class.school.name', ''));
        $submitterName = trim((string) data_get($leaveRequest, 'submitter.name', ''));
        $submitterPhone = trim((string) data_get($leaveRequest, 'submitter.phone', ''));
        $reason = trim((string) ($leaveRequest->reason ?? ''));
        $approverName = trim((string) data_get($leaveRequest, 'approver.name', ''));
        $startDate = $this->displayDate($leaveRequest->start_date);
        $endDate = $this->displayDate($leaveRequest->end_date ?: $leaveRequest->start_date);
        $days = max(1, (int) ($leaveRequest->total_days ?? 1));
        $timeRange = $this->displayTimeRange($leaveRequest->start_time, $leaveRequest->end_time);
        $statusValue = trim(strtolower((string) ($leaveRequest->status ?? '')));
        $statusText = match ($statusValue) {
            'approved' => 'ស្ថានភាព : ✅ បានអនុម័ត',
            'rejected' => 'ស្ថានភាព : ❌ មិនបានអនុម័ត',
            default => 'ស្ថានភាព : '.($statusValue !== '' ? ucfirst($statusValue) : '-'),
        };

        $lines = [
            trim($title) !== '' ? trim($title) : 'Leave request updated',
            $statusText,
            'សិស្ស : '.($studentName !== '' ? $studentName : '-'),
            'ថ្នាក់ : '.($className !== '' ? $className : '-'),
            'សាលា : '.($schoolName !== '' ? $schoolName : '-'),
            'អ្នកស្នើសុំ : '.($submitterName !== '' ? $submitterName : '-'),
            'អ្នកសម្រេច : '.($approverName !== '' ? $approverName : '-'),
            'ចាប់ផ្តើមថ្ងៃ : '.$startDate,
            'បញ្ចប់ថ្ងៃ : '.$endDate,
            'ម៉ោង : '.($timeRange !== '' ? $timeRange : '-'),
            'លេខទូរស័ព្ទ : '.($submitterPhone !== '' ? $submitterPhone : '-'),
            'មូលហេតុ : '.($reason !== '' ? mb_strimwidth($reason, 0, 800, '...') : trim(mb_strimwidth($content, 0, 800, '...'))),
            'ថ្ងៃ : '.$days.'ថ្ងៃ',
        ];

        return implode("\n", $lines);
    }

    /**
     * Leave approval notifications should be delivered immediately, because
     * many local deployments do not run a queue worker continuously.
     *
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>|null  $replyMarkup
     */
    private function dispatchTelegramMessage(
        string $chatId,
        string $text,
        array $meta = [],
        ?array $replyMarkup = null,
    ): void {
        SendTelegramMessageJob::dispatchSync(
            chatId: $chatId,
            text: $text,
            meta: $meta,
            replyMarkup: $replyMarkup,
        );
    }

    /**
     * @return array<int, string>
     */
    private function resolveTelegramGroupChatIds(): array
    {
        $configured = config('services.telegram.group_chat_ids', []);
        if (! is_array($configured)) {
            return [];
        }

        return collect($configured)
            ->map(fn ($chatId): string => trim((string) $chatId))
            ->filter(fn (string $chatId): bool => $chatId !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $recipientIds
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    private function resolveTelegramUsers(array $recipientIds)
    {
        $normalizedIds = collect($recipientIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($normalizedIds === []) {
            return User::query()->whereRaw('1 = 0')->get();
        }

        return User::query()
            ->whereIn('id', $normalizedIds)
            ->whereNotNull('telegram_chat_id')
            ->where('telegram_chat_id', '!=', '')
            ->get(['id', 'name', 'telegram_chat_id']);
    }

    private function actionCodeForStatus(string $status): ?string
    {
        return match (trim(strtolower($status))) {
            'approved' => 'a',
            'rejected' => 'r',
            default => null,
        };
    }

    private function statusForActionCode(string $actionCode): ?string
    {
        return match (trim(strtolower($actionCode))) {
            'a' => 'approved',
            'r' => 'rejected',
            default => null,
        };
    }

    private function actionTtlMinutes(): int
    {
        $minutes = (int) config('services.telegram.leave_action_ttl_minutes', 1440);

        return max(5, min($minutes, 10080));
    }

    private function actionSignature(
        string $actionCode,
        int $leaveRequestId,
        ?int $recipientUserId,
        int $expiresAt,
    ): string {
        $recipientValue = $recipientUserId !== null ? (string) $recipientUserId : 'group';
        $payload = implode('|', [
            trim(strtolower($actionCode)),
            (string) $leaveRequestId,
            $recipientValue,
            (string) $expiresAt,
        ]);

        return substr(hash_hmac('sha256', $payload, $this->signingKey()), 0, 16);
    }

    private function signingKey(): string
    {
        $appKey = trim((string) config('app.key', ''));
        $botToken = trim((string) config('services.telegram.bot_token', ''));

        return hash('sha256', $appKey.'|'.$botToken.'|leave-request-telegram-action');
    }

    private function displayDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $text = trim((string) $value);
        if ($text === '') {
            return '-';
        }

        return mb_substr($text, 0, 10);
    }

    private function displayTimeRange(mixed $start, mixed $end): string
    {
        $startTime = $this->displayTime($start);
        $endTime = $this->displayTime($end);

        if ($startTime === '' || $endTime === '') {
            return '';
        }

        return $startTime.' - '.$endTime;
    }

    private function displayTime(mixed $value): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        if (strlen($text) >= 5) {
            return substr($text, 0, 5);
        }

        return $text;
    }
}
