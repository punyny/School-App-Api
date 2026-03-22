<?php

namespace App\Http\Controllers\Api;

use App\Events\RealtimeNotificationBroadcasted;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MessageStoreRequest;
use App\Models\Message;
use App\Models\MessageRead;
use App\Models\Notification as UserNotification;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MessageController extends Controller
{
    /**
     * @var array<int, array<int, int>>
     */
    private array $classRecipientCache = [];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Message::class);

        $filters = $request->validate([
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'sender_id' => ['nullable', 'integer', 'exists:users,id'],
            'receiver_id' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Message::query()
            ->with(['sender', 'receiver', 'class'])
            ->orderByDesc('date')
            ->orderByDesc('id');

        $this->applyVisibilityScope($query, $request->user());

        foreach (['class_id', 'sender_id', 'receiver_id'] as $key) {
            if (isset($filters[$key])) {
                $query->where($key, $filters[$key]);
            }
        }

        if (isset($filters['date_from'])) {
            $query->where('date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('date', '<=', $filters['date_to'].' 23:59:59');
        }

        $paginator = $query->paginate($filters['per_page'] ?? 20);
        $this->markMessagesAsSeen($request->user(), $paginator->getCollection());
        $this->appendReadMeta($paginator, $request->user());

        return response()->json($paginator);
    }

    public function show(Request $request, Message $message): JsonResponse
    {
        $this->authorize('view', $message);

        $message->load(['sender', 'receiver', 'class']);
        $this->markMessagesAsSeen($request->user(), new EloquentCollection([$message]));
        $this->appendReadMeta($message, $request->user());

        return response()->json([
            'data' => $message,
        ]);
    }

    public function store(MessageStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Message::class);

        $user = $request->user();

        $payload = $request->validated();

        $this->assertSingleMessageTarget($payload['receiver_id'] ?? null, $payload['class_id'] ?? null);

        if (($payload['class_id'] ?? null) && ! $this->canSendToClass($user, (int) $payload['class_id'])) {
            return response()->json(['message' => 'Forbidden to send to this class.'], 403);
        }

        if (($payload['receiver_id'] ?? null) && ! $this->canSendToUser($user, (int) $payload['receiver_id'])) {
            return response()->json(['message' => 'Forbidden to send to this user.'], 403);
        }

        $message = Message::query()->create([
            'sender_id' => $user->id,
            'receiver_id' => $payload['receiver_id'] ?? null,
            'class_id' => $payload['class_id'] ?? null,
            'content' => $payload['content'],
            'date' => $payload['date'] ?? now(),
        ]);

        MessageRead::query()->updateOrCreate(
            [
                'message_id' => (int) $message->id,
                'user_id' => (int) $user->id,
            ],
            [
                'seen_at' => now(),
            ]
        );

        $this->notifyMessageRecipients($message);

        $message->load(['sender', 'receiver', 'class']);
        $this->appendReadMeta($message, $user);

        return response()->json([
            'message' => 'Message sent.',
            'data' => $message,
        ], 201);
    }

    public function update(Request $request, Message $message): JsonResponse
    {
        $this->authorize('update', $message);

        $user = $request->user();
        $payload = $request->validate([
            'receiver_id' => ['nullable', 'integer', 'exists:users,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'content' => ['required', 'string'],
            'date' => ['nullable', 'date'],
        ]);

        $this->assertSingleMessageTarget($payload['receiver_id'] ?? null, $payload['class_id'] ?? null);

        if (($payload['class_id'] ?? null) && ! $this->canSendToClass($user, (int) $payload['class_id'])) {
            return response()->json(['message' => 'Forbidden to send to this class.'], 403);
        }

        if (($payload['receiver_id'] ?? null) && ! $this->canSendToUser($user, (int) $payload['receiver_id'])) {
            return response()->json(['message' => 'Forbidden to send to this user.'], 403);
        }

        $message->fill([
            'receiver_id' => $payload['receiver_id'] ?? null,
            'class_id' => $payload['class_id'] ?? null,
            'content' => $payload['content'],
            'date' => $payload['date'] ?? $message->date,
        ])->save();

        return response()->json([
            'message' => 'Message updated.',
            'data' => $message->fresh()->load(['sender', 'receiver', 'class']),
        ]);
    }

    public function destroy(Request $request, Message $message): JsonResponse
    {
        $this->authorize('delete', $message);

        $message->delete();

        return response()->json([
            'message' => 'Message deleted.',
        ]);
    }

    private function applyVisibilityScope(Builder $query, User $user): void
    {
        if ($user->role === 'super-admin') {
            return;
        }

        if ($user->role === 'admin') {
            if (! $user->school_id) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where(function (Builder $scope) use ($user): void {
                $scope->whereHas('class', fn (Builder $classQuery) => $classQuery->where('school_id', $user->school_id))
                    ->orWhereHas('sender', fn (Builder $senderQuery) => $senderQuery->where('school_id', $user->school_id))
                    ->orWhereHas('receiver', fn (Builder $receiverQuery) => $receiverQuery->where('school_id', $user->school_id));
            });

            return;
        }

        if ($user->role === 'teacher') {
            $classIds = $user->teachingClasses()->pluck('classes.id')->all();
            $query->where(function (Builder $scope) use ($user, $classIds): void {
                $scope->where('sender_id', $user->id)
                    ->orWhere('receiver_id', $user->id);
                if ($classIds !== []) {
                    $scope->orWhereIn('class_id', $classIds);
                }
            });

            return;
        }

        if ($user->role === 'student') {
            $classId = $user->studentProfile?->class_id;
            $query->where(function (Builder $scope) use ($user, $classId): void {
                $scope->where('sender_id', $user->id)
                    ->orWhere('receiver_id', $user->id);
                if ($classId) {
                    $scope->orWhere('class_id', $classId);
                }
            });

            return;
        }

        if ($user->role === 'parent') {
            $classIds = $user->children()->pluck('students.class_id')->filter()->unique()->values()->all();
            $query->where(function (Builder $scope) use ($user, $classIds): void {
                $scope->where('sender_id', $user->id)
                    ->orWhere('receiver_id', $user->id);
                if ($classIds !== []) {
                    $scope->orWhereIn('class_id', $classIds);
                }
            });

            return;
        }

        $query->whereRaw('1 = 0');
    }

    private function canViewMessage(User $user, Message $message): bool
    {
        $query = Message::query()->whereKey($message->id);
        $this->applyVisibilityScope($query, $user);

        return $query->exists();
    }

    private function canSendToClass(User $user, int $classId): bool
    {
        if ($user->role === 'super-admin') {
            return SchoolClass::query()->whereKey($classId)->exists();
        }

        $class = SchoolClass::query()->find($classId);
        if (! $class || ! $user->school_id || (int) $class->school_id !== (int) $user->school_id) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'teacher') {
            return $user->teachingClasses()->where('classes.id', $classId)->exists();
        }

        return false;
    }

    private function canSendToUser(User $user, int $targetUserId): bool
    {
        $target = User::query()->find($targetUserId);
        if (! $target) {
            return false;
        }

        $targetRole = $this->normalizeRole((string) $target->role);
        $actorRole = $this->normalizeRole((string) $user->role);

        if ($user->role === 'super-admin') {
            return true;
        }

        if (! $user->school_id || (int) $target->school_id !== (int) $user->school_id) {
            return false;
        }

        if ($actorRole === 'admin') {
            return in_array($targetRole, ['teacher', 'student', 'parent'], true);
        }

        if ($actorRole === 'teacher') {
            $teacherClassIds = $user->teachingClasses()->pluck('classes.id')->all();
            if ($teacherClassIds === []) {
                return false;
            }

            if ($targetRole === 'student') {
                $studentClassId = $this->studentClassIdByUser($targetUserId);

                return $studentClassId !== null && in_array($studentClassId, $teacherClassIds, true);
            }

            if ($targetRole === 'parent') {
                return $this->parentHasChildInClasses($targetUserId, $teacherClassIds);
            }

            return false;
        }

        if ($actorRole === 'student') {
            if ($targetRole !== 'teacher') {
                return false;
            }

            return true;
        }

        return false;
    }

    private function canModerateMessage(User $user, Message $message): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        if ($user->role !== 'admin' || ! $user->school_id) {
            return false;
        }

        if ($message->class_id) {
            $class = SchoolClass::query()->find($message->class_id);

            return $class && (int) $class->school_id === (int) $user->school_id;
        }

        $sender = User::query()->find($message->sender_id);

        return $sender && (int) $sender->school_id === (int) $user->school_id;
    }

    /**
     * @param  EloquentCollection<int, Message>  $messages
     */
    private function markMessagesAsSeen(User $viewer, EloquentCollection $messages): void
    {
        $now = now();

        foreach ($messages as $message) {
            if ((int) $message->sender_id === (int) $viewer->id) {
                continue;
            }

            $recipientIds = $this->resolveMessageRecipientIds($message);
            if (! in_array((int) $viewer->id, $recipientIds, true)) {
                continue;
            }

            MessageRead::query()->updateOrCreate(
                [
                    'message_id' => (int) $message->id,
                    'user_id' => (int) $viewer->id,
                ],
                [
                    'seen_at' => $now,
                ]
            );
        }
    }

    private function appendReadMeta(LengthAwarePaginator|Message $target, User $actor): void
    {
        $messages = $target instanceof Message
            ? new EloquentCollection([$target])
            : $target->getCollection();

        $messageIds = $messages->pluck('id')->map(fn ($id): int => (int) $id)->all();
        if ($messageIds === []) {
            return;
        }

        $recipientMap = [];
        foreach ($messages as $message) {
            $recipientMap[(int) $message->id] = $this->resolveMessageRecipientIds($message);
        }

        $reads = MessageRead::query()
            ->whereIn('message_id', $messageIds)
            ->orderByDesc('seen_at')
            ->get(['message_id', 'user_id', 'seen_at']);

        $readsByMessage = [];
        $readUserIds = [];
        foreach ($reads as $read) {
            $messageId = (int) $read->message_id;
            $readsByMessage[$messageId] ??= [];
            $readsByMessage[$messageId][] = [
                'user_id' => (int) $read->user_id,
                'seen_at' => optional($read->seen_at)->toDateTimeString(),
            ];
            $readUserIds[] = (int) $read->user_id;
        }

        $userNames = User::query()
            ->whereIn('id', array_values(array_unique(array_filter($readUserIds))))
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id): array => [(int) $id => (string) $name])
            ->all();

        foreach ($messages as $message) {
            $messageId = (int) $message->id;
            $recipientIds = $recipientMap[$messageId] ?? [];
            $recipientLookup = array_flip($recipientIds);
            $seenRecords = array_values(array_filter(
                $readsByMessage[$messageId] ?? [],
                fn (array $record): bool => isset($recipientLookup[(int) ($record['user_id'] ?? 0)])
            ));

            $directSeenAt = null;
            if ($message->receiver_id) {
                foreach ($seenRecords as $record) {
                    if ((int) ($record['user_id'] ?? 0) === (int) $message->receiver_id) {
                        $directSeenAt = $record['seen_at'] ?? null;
                        break;
                    }
                }
            }

            $currentUserSeenAt = null;
            foreach ($seenRecords as $record) {
                if ((int) ($record['user_id'] ?? 0) === (int) $actor->id) {
                    $currentUserSeenAt = $record['seen_at'] ?? null;
                    break;
                }
            }

            $lastSeenAt = $seenRecords[0]['seen_at'] ?? null;
            $seenBy = array_map(function (array $record) use ($userNames): array {
                $userId = (int) ($record['user_id'] ?? 0);

                return [
                    'user_id' => $userId,
                    'name' => (string) ($userNames[$userId] ?? ('User '.$userId)),
                    'seen_at' => $record['seen_at'] ?? null,
                ];
            }, $seenRecords);

            $message->setAttribute('read_meta', [
                'recipient_count' => count($recipientIds),
                'seen_count' => count($seenRecords),
                'unseen_count' => max(count($recipientIds) - count($seenRecords), 0),
                'direct_recipient_seen_at' => $directSeenAt,
                'current_user_seen_at' => $currentUserSeenAt,
                'last_seen_at' => $lastSeenAt,
                'seen_by' => $seenBy,
            ]);
        }
    }

    /**
     * @return array<int, int>
     */
    private function resolveMessageRecipientIds(Message $message): array
    {
        if ($message->receiver_id) {
            return [(int) $message->receiver_id];
        }

        $classId = (int) ($message->class_id ?? 0);
        if ($classId <= 0) {
            return [];
        }

        if (isset($this->classRecipientCache[$classId])) {
            return $this->classRecipientCache[$classId];
        }

        $studentUserIds = Student::query()
            ->where('class_id', $classId)
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->all();

        $parentIds = DB::table('parent_child')
            ->join('students', 'students.id', '=', 'parent_child.student_id')
            ->where('students.class_id', $classId)
            ->pluck('parent_child.parent_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $recipientIds = array_values(array_unique(array_filter([...$studentUserIds, ...$parentIds])));
        $this->classRecipientCache[$classId] = $recipientIds;

        return $recipientIds;
    }

    private function notifyMessageRecipients(Message $message): void
    {
        $recipientIds = [];

        if ($message->receiver_id) {
            $recipientIds[] = (int) $message->receiver_id;
        }

        if ($message->class_id) {
            $studentUserIds = Student::query()
                ->where('class_id', $message->class_id)
                ->pluck('user_id')
                ->filter()
                ->all();

            $parentIds = DB::table('parent_child')
                ->join('students', 'students.id', '=', 'parent_child.student_id')
                ->where('students.class_id', $message->class_id)
                ->pluck('parent_child.parent_id')
                ->all();

            $recipientIds = [...$recipientIds, ...$studentUserIds, ...$parentIds];
        }

        $recipientIds = array_values(array_unique(array_filter($recipientIds)));
        $recipientIds = array_values(array_filter($recipientIds, fn (int $userId): bool => $userId !== (int) $message->sender_id));

        if ($recipientIds === []) {
            return;
        }

        $rows = [];
        $now = now();
        foreach ($recipientIds as $userId) {
            $rows[] = [
                'user_id' => $userId,
                'title' => 'New message',
                'content' => mb_strimwidth($message->content, 0, 120, '...'),
                'date' => $now,
                'read_status' => false,
            ];
        }

        UserNotification::query()->insert($rows);

        event(new RealtimeNotificationBroadcasted(
            recipientIds: $recipientIds,
            type: 'message',
            title: 'New message',
            content: mb_strimwidth($message->content, 0, 120, '...'),
            meta: [
                'message_id' => (int) $message->id,
                'sender_id' => (int) $message->sender_id,
                'class_id' => $message->class_id ? (int) $message->class_id : null,
                'receiver_id' => $message->receiver_id ? (int) $message->receiver_id : null,
            ]
        ));
    }

    private function normalizeRole(string $role): string
    {
        $value = strtolower(trim($role));

        return $value === 'guardian' ? 'parent' : $value;
    }

    private function studentClassIdByUser(int $userId): ?int
    {
        $classId = Student::query()
            ->where('user_id', $userId)
            ->value('class_id');

        return $classId ? (int) $classId : null;
    }

    /**
     * @param  array<int, int>  $classIds
     */
    private function parentHasChildInClasses(int $parentUserId, array $classIds): bool
    {
        if ($classIds === []) {
            return false;
        }

        return DB::table('parent_child')
            ->join('students', 'students.id', '=', 'parent_child.student_id')
            ->where('parent_child.parent_id', $parentUserId)
            ->whereIn('students.class_id', $classIds)
            ->exists();
    }

    private function assertSingleMessageTarget(mixed $receiverId, mixed $classId): void
    {
        $hasReceiver = $receiverId !== null && $receiverId !== '';
        $hasClass = $classId !== null && $classId !== '';

        if (! $hasReceiver && ! $hasClass) {
            throw ValidationException::withMessages([
                'receiver_id' => ['Please select a receiver or class.'],
                'class_id' => ['Please select a receiver or class.'],
            ]);
        }

        if ($hasReceiver && $hasClass) {
            throw ValidationException::withMessages([
                'receiver_id' => ['Use one target only: direct receiver or class.'],
                'class_id' => ['Use one target only: direct receiver or class.'],
            ]);
        }
    }
}
