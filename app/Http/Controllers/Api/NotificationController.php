<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\NotificationReadStatusUpdateRequest;
use App\Http\Requests\Api\NotificationStoreRequest;
use App\Jobs\SendBroadcastNotificationJob;
use App\Models\Notification as UserNotification;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', UserNotification::class);

        $filters = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'read_status' => ['nullable', 'boolean'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $authUser = $request->user();
        $requestedUserId = $filters['user_id'] ?? null;

        if ($requestedUserId && ! in_array($authUser->role, ['super-admin', 'admin'], true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $query = UserNotification::query()->with('user')->orderByDesc('date')->orderByDesc('id');

        if ($requestedUserId) {
            $targetUser = User::query()->findOrFail($requestedUserId);
            if ($authUser->role === 'admin' && (int) $targetUser->school_id !== (int) $authUser->school_id) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
            $query->where('user_id', $targetUser->id);
        } else {
            $query->where('user_id', $authUser->id);
        }

        if (array_key_exists('read_status', $filters)) {
            $query->where('read_status', $filters['read_status']);
        }

        if (isset($filters['date_from'])) {
            $query->where('date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('date', '<=', $filters['date_to'].' 23:59:59');
        }

        return response()->json($query->paginate($filters['per_page'] ?? 20));
    }

    public function show(Request $request, UserNotification $notification): JsonResponse
    {
        $this->authorize('view', $notification);

        return response()->json([
            'data' => $notification->load('user'),
        ]);
    }

    public function store(NotificationStoreRequest $request): JsonResponse
    {
        $this->authorize('create', UserNotification::class);

        $user = $request->user();
        $payload = $request->validated();
        $target = User::query()->findOrFail($payload['user_id']);

        if ($user->role !== 'super-admin') {
            if (! $user->school_id || (int) $target->school_id !== (int) $user->school_id) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
        }

        $notification = UserNotification::query()->create([
            'user_id' => $target->id,
            'title' => $payload['title'],
            'content' => $payload['content'],
            'date' => $payload['date'] ?? now(),
            'read_status' => $payload['read_status'] ?? false,
        ]);

        return response()->json([
            'message' => 'Notification created.',
            'data' => $notification->load('user'),
        ], 201);
    }

    public function update(Request $request, UserNotification $notification): JsonResponse
    {
        $this->authorize('update', $notification);

        $user = $request->user();
        $payload = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'date' => ['nullable', 'date'],
            'read_status' => ['nullable', 'boolean'],
        ]);

        if (isset($payload['user_id'])) {
            $target = User::query()->findOrFail((int) $payload['user_id']);

            if ($user->role !== 'super-admin') {
                if (! $user->school_id || (int) $target->school_id !== (int) $user->school_id) {
                    return response()->json(['message' => 'Forbidden.'], 403);
                }
            }

            $notification->user_id = $target->id;
        }

        foreach (['title', 'content', 'date', 'read_status'] as $field) {
            if (array_key_exists($field, $payload)) {
                $notification->{$field} = $payload[$field];
            }
        }

        $notification->save();

        return response()->json([
            'message' => 'Notification updated.',
            'data' => $notification->fresh()->load('user'),
        ]);
    }

    public function updateReadStatus(NotificationReadStatusUpdateRequest $request, UserNotification $notification): JsonResponse
    {
        $this->authorize('updateReadStatus', $notification);

        $notification->read_status = $request->validated()['read_status'];
        $notification->save();

        return response()->json([
            'message' => 'Notification status updated.',
            'data' => $notification->fresh()->load('user'),
        ]);
    }

    public function destroy(Request $request, UserNotification $notification): JsonResponse
    {
        $this->authorize('delete', $notification);

        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted.',
        ]);
    }

    public function broadcast(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (! in_array($actor->role, ['super-admin', 'admin', 'teacher'], true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'type' => ['nullable', 'string', 'max:50'],
            'send_at' => ['nullable', 'date', 'after:now'],
            'audience' => ['nullable', 'in:teacher,all_teacher,student,all_student,class'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'role' => ['nullable', 'in:super-admin,admin,teacher,student,parent'],
        ]);

        if (isset($payload['audience'])) {
            if ($payload['audience'] === 'teacher') {
                if (! isset($payload['user_id'])) {
                    throw ValidationException::withMessages([
                        'user_id' => ['user_id is required when audience=teacher.'],
                    ]);
                }
                $payload['user_ids'] = [(int) $payload['user_id']];
                $payload['role'] = 'teacher';
            } elseif ($payload['audience'] === 'all_teacher') {
                $payload['role'] = 'teacher';
            } elseif ($payload['audience'] === 'student') {
                if (! isset($payload['user_id'])) {
                    throw ValidationException::withMessages([
                        'user_id' => ['user_id is required when audience=student.'],
                    ]);
                }
                $payload['user_ids'] = [(int) $payload['user_id']];
                $payload['role'] = 'student';
            } elseif ($payload['audience'] === 'all_student') {
                $payload['role'] = 'student';
            } elseif ($payload['audience'] === 'class' && ! isset($payload['class_id'])) {
                throw ValidationException::withMessages([
                    'class_id' => ['class_id is required when audience=class.'],
                ]);
            }
        }

        if (
            empty($payload['user_ids'])
            && ! isset($payload['school_id'])
            && ! isset($payload['class_id'])
            && ! isset($payload['role'])
        ) {
            throw ValidationException::withMessages([
                'user_ids' => ['Provide at least one target filter: user_ids, school_id, class_id, or role.'],
            ]);
        }

        $query = User::query()->where('active', true);

        if (! empty($payload['user_ids'])) {
            $query->whereIn('id', array_values(array_unique(array_map('intval', $payload['user_ids']))));
        }

        if (isset($payload['school_id'])) {
            $query->where('school_id', (int) $payload['school_id']);
        }

        if (isset($payload['role'])) {
            $query->where('role', (string) $payload['role']);
        }

        if (isset($payload['class_id'])) {
            $class = SchoolClass::query()->findOrFail((int) $payload['class_id']);
            if ($actor->role !== 'super-admin' && (! $actor->school_id || (int) $actor->school_id !== (int) $class->school_id)) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            if ($actor->role === 'teacher' && ! $actor->teachingClasses()->where('classes.id', $class->id)->exists()) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            $teacherIds = $class->teachers()->pluck('users.id')->all();
            $query->where(function ($scope) use ($class, $teacherIds): void {
                $scope->whereIn('id', $teacherIds)
                    ->orWhereHas('studentProfile', fn ($studentQuery) => $studentQuery->where('class_id', $class->id))
                    ->orWhereHas('children', fn ($childQuery) => $childQuery->where('class_id', $class->id));
            });
        }

        if ($actor->role !== 'super-admin' && $actor->school_id) {
            $query->where('school_id', (int) $actor->school_id);
        }

        $recipientIds = $query->pluck('id')->all();
        if ($recipientIds === []) {
            return response()->json([
                'message' => 'No recipients matched the target filters.',
                'data' => ['recipients_count' => 0],
            ], 422);
        }

        $job = SendBroadcastNotificationJob::dispatch(
            recipientIds: $recipientIds,
            title: (string) $payload['title'],
            content: (string) $payload['content'],
            type: (string) ($payload['type'] ?? 'broadcast')
        );

        if (isset($payload['send_at'])) {
            $job->delay(Carbon::parse((string) $payload['send_at']));
        }

        return response()->json([
            'message' => isset($payload['send_at'])
                ? 'Broadcast scheduled successfully.'
                : 'Broadcast queued successfully.',
            'data' => [
                'recipients_count' => count($recipientIds),
                'send_at' => $payload['send_at'] ?? now()->toDateTimeString(),
            ],
        ], 202);
    }

    private function canViewNotification(User $user, UserNotification $notification): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        if ((int) $notification->user_id === (int) $user->id) {
            return true;
        }

        return $user->role === 'admin'
            && $user->school_id
            && (int) $notification->user?->school_id === (int) $user->school_id;
    }
}
