<?php

namespace App\Http\Controllers\Api;

use App\Events\RealtimeNotificationBroadcasted;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AnnouncementStoreRequest;
use App\Http\Requests\Api\AnnouncementUpdateRequest;
use App\Models\Announcement;
use App\Models\Notification as UserNotification;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Support\ProfileImageStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Announcement::class);

        $filters = $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'target_role' => ['nullable', 'in:teacher,student,parent'],
            'target_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Announcement::query()
            ->with(['school', 'class', 'targetUser', 'media'])
            ->orderByDesc('date')
            ->orderByDesc('id');

        $this->applyVisibilityScope($query, $request->user());

        if (isset($filters['school_id'])) {
            $query->where('school_id', $filters['school_id']);
        }

        foreach (['class_id', 'target_role', 'target_user_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        return response()->json($query->paginate($filters['per_page'] ?? 20));
    }

    public function show(Request $request, Announcement $announcement): JsonResponse
    {
        $this->authorize('view', $announcement);

        return response()->json([
            'data' => $announcement->load(['school', 'class', 'media']),
        ]);
    }

    public function store(AnnouncementStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Announcement::class);

        $user = $request->user();
        $payload = $request->validated();

        $payload['school_id'] = $this->resolveSchoolIdForWrite($user, $payload['school_id'] ?? null);
        $payload = $this->prepareAnnouncementTargetPayload($payload);
        $this->validateClassBelongsToSchool($payload['class_id'] ?? null, (int) $payload['school_id']);
        $this->validateTargetRole($payload['target_role'] ?? null);
        $this->validateTargetUser($payload['target_user_id'] ?? null, (int) $payload['school_id'], $payload['target_role'] ?? null);
        $payload['date'] = $payload['date'] ?? now()->toDateString();

        $announcement = Announcement::query()->create($payload);
        $attachmentUrls = $this->storeUploadedAttachments($request, $announcement);
        if ($attachmentUrls !== []) {
            $mergedUrls = collect(array_merge($payload['file_attachments'] ?? [], $attachmentUrls))
                ->filter(fn (?string $url) => is_string($url) && $url !== '')
                ->unique()
                ->values()
                ->all();
            $announcement->forceFill(['file_attachments' => $mergedUrls])->save();
        }
        $this->notifyAnnouncementRecipients($announcement);

        return response()->json([
            'message' => 'Announcement created.',
            'data' => $announcement->load(['school', 'class', 'targetUser', 'media']),
        ], 201);
    }

    public function update(AnnouncementUpdateRequest $request, Announcement $announcement): JsonResponse
    {
        $this->authorize('update', $announcement);

        $user = $request->user();

        $payload = $request->validated();

        if ($user->role !== 'super-admin') {
            unset($payload['school_id']);
        }

        $payload = $this->prepareAnnouncementTargetPayload($payload, $announcement);

        $targetSchoolId = (int) ($payload['school_id'] ?? $announcement->school_id);
        $this->validateClassBelongsToSchool($payload['class_id'] ?? $announcement->class_id, $targetSchoolId);
        $this->validateTargetRole($payload['target_role'] ?? $announcement->target_role);
        $this->validateTargetUser(
            $payload['target_user_id'] ?? $announcement->target_user_id,
            $targetSchoolId,
            $payload['target_role'] ?? $announcement->target_role
        );

        $announcement->fill($payload)->save();
        $attachmentUrls = $this->storeUploadedAttachments($request, $announcement);
        if ($attachmentUrls !== []) {
            $mergedUrls = collect(array_merge($announcement->file_attachments ?? [], $attachmentUrls))
                ->filter(fn (?string $url) => is_string($url) && $url !== '')
                ->unique()
                ->values()
                ->all();
            $announcement->forceFill(['file_attachments' => $mergedUrls])->save();
        }

        return response()->json([
            'message' => 'Announcement updated.',
            'data' => $announcement->fresh()->load(['school', 'class', 'targetUser', 'media']),
        ]);
    }

    public function destroy(Request $request, Announcement $announcement): JsonResponse
    {
        $this->authorize('delete', $announcement);

        $announcement->delete();

        return response()->json([
            'message' => 'Announcement deleted.',
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

            $query->where('school_id', $user->school_id);

            return;
        }

        if (! in_array($user->role, ['teacher', 'student', 'parent'], true)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $classIds = $this->audienceClassIds($user);
        $schoolId = (int) ($user->school_id ?? 0);
        if ($schoolId <= 0 && $classIds !== []) {
            $schoolId = (int) (SchoolClass::query()->whereIn('id', $classIds)->value('school_id') ?? 0);
        }

        if ($schoolId <= 0) {
            $query->whereRaw('1 = 0');

            return;
        }

        $role = $this->normalizeRole((string) $user->role);
        $query->where('school_id', $schoolId)
            ->where(function (Builder $scope) use ($user, $role, $classIds): void {
                // Direct personal announcement.
                $scope->where('target_user_id', $user->id);

                // Role broadcast.
                $scope->orWhere(function (Builder $roleScope) use ($role): void {
                    $roleScope->whereNull('target_user_id')
                        ->whereNotNull('target_role')
                        ->where('target_role', $role);
                });

                // Class broadcast.
                if ($classIds !== []) {
                    $scope->orWhere(function (Builder $classScope) use ($classIds): void {
                        $classScope->whereNull('target_user_id')
                            ->whereNull('target_role')
                            ->whereIn('class_id', $classIds);
                    });
                }

                // General school-wide announcement.
                $scope->orWhere(function (Builder $allScope): void {
                    $allScope->whereNull('target_user_id')
                        ->whereNull('target_role')
                        ->whereNull('class_id');
                });
            });
    }

    private function canViewAnnouncement(User $user, Announcement $announcement): bool
    {
        $query = Announcement::query()->whereKey($announcement->id);
        $this->applyVisibilityScope($query, $user);

        return $query->exists();
    }

    private function canManageAnnouncement(User $user, Announcement $announcement): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        if ($user->role !== 'admin') {
            return false;
        }

        if (! $user->school_id || (int) $user->school_id !== (int) $announcement->school_id) {
            return false;
        }

        return true;
    }

    private function resolveSchoolIdForWrite(User $user, ?int $requestedSchoolId): int
    {
        if ($user->role === 'super-admin') {
            if (! $requestedSchoolId) {
                throw ValidationException::withMessages([
                    'school_id' => ['school_id is required for super-admin.'],
                ]);
            }

            return $requestedSchoolId;
        }

        if ($user->role !== 'admin') {
            throw ValidationException::withMessages([
                'role' => ['Only super-admin or admin can create announcements.'],
            ]);
        }

        if (! $user->school_id) {
            throw ValidationException::withMessages([
                'school_id' => ['This account has no school assigned.'],
            ]);
        }

        return (int) $user->school_id;
    }

    private function validateClassBelongsToSchool(?int $classId, int $schoolId): void
    {
        if (! $classId) {
            return;
        }

        $class = SchoolClass::query()->find($classId);
        if (! $class || (int) $class->school_id !== $schoolId) {
            throw ValidationException::withMessages([
                'class_id' => ['Selected class does not belong to this school.'],
            ]);
        }
    }

    private function validateTargetRole(?string $targetRole): void
    {
        if ($targetRole === null || $targetRole === '') {
            return;
        }

        if (! in_array($targetRole, ['teacher', 'student', 'parent'], true)) {
            throw ValidationException::withMessages([
                'target_role' => ['target_role must be teacher, student, or parent.'],
            ]);
        }
    }

    private function validateTargetUser(?int $targetUserId, int $schoolId, ?string $targetRole): void
    {
        if (! $targetUserId) {
            return;
        }

        $targetUser = User::query()->find($targetUserId);
        if (! $targetUser || (int) $targetUser->school_id !== $schoolId) {
            throw ValidationException::withMessages([
                'target_user_id' => ['Selected user does not belong to this school.'],
            ]);
        }

        $normalizedTargetRole = $this->normalizeRole((string) $targetUser->role);
        if (! in_array($normalizedTargetRole, ['teacher', 'student', 'parent'], true)) {
            throw ValidationException::withMessages([
                'target_user_id' => ['Selected user must be a teacher, student, or parent.'],
            ]);
        }

        if ($targetRole !== null && $targetRole !== '' && $targetRole !== $normalizedTargetRole) {
            throw ValidationException::withMessages([
                'target_user_id' => ['target_user_id role does not match target_role.'],
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function storeUploadedAttachments(Request $request, Announcement $announcement): array
    {
        $files = $request->file('attachments', []);
        if (! is_array($files) || $files === []) {
            return [];
        }

        return ProfileImageStorage::attachManyToModel(
            $files,
            $announcement,
            $request->user(),
            'attachments/announcements',
            'attachment',
            [
                'module' => 'announcement',
                'class_id' => $announcement->class_id,
                'school_id' => $announcement->school_id,
            ]
        );
    }

    private function notifyAnnouncementRecipients(Announcement $announcement): void
    {
        $recipientIds = $this->resolveAnnouncementRecipients($announcement);

        if ($recipientIds === []) {
            return;
        }

        $now = now();
        $rows = [];
        foreach ($recipientIds as $userId) {
            $rows[] = [
                'user_id' => $userId,
                'title' => 'New announcement',
                'content' => $announcement->title,
                'date' => $now,
                'read_status' => false,
            ];
        }

        UserNotification::query()->insert($rows);

        event(new RealtimeNotificationBroadcasted(
            recipientIds: $recipientIds,
            type: 'announcement',
            title: 'New announcement',
            content: $announcement->title,
            meta: [
                'announcement_id' => (int) $announcement->id,
                'school_id' => (int) $announcement->school_id,
                'class_id' => $announcement->class_id ? (int) $announcement->class_id : null,
                'target_role' => $announcement->target_role,
                'target_user_id' => $announcement->target_user_id ? (int) $announcement->target_user_id : null,
            ]
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareAnnouncementTargetPayload(array $payload, ?Announcement $existing = null): array
    {
        $classId = array_key_exists('class_id', $payload)
            ? ($payload['class_id'] !== '' ? (int) $payload['class_id'] : null)
            : ($existing?->class_id ? (int) $existing->class_id : null);
        $targetRole = array_key_exists('target_role', $payload)
            ? trim((string) ($payload['target_role'] ?? ''))
            : (string) ($existing?->target_role ?? '');
        $targetUserId = array_key_exists('target_user_id', $payload)
            ? ($payload['target_user_id'] !== '' ? (int) $payload['target_user_id'] : null)
            : ($existing?->target_user_id ? (int) $existing->target_user_id : null);

        $selectors = 0;
        $selectors += $classId ? 1 : 0;
        $selectors += $targetRole !== '' ? 1 : 0;
        $selectors += $targetUserId ? 1 : 0;

        if ($selectors > 1) {
            throw ValidationException::withMessages([
                'class_id' => ['Please use one target mode only: class OR target_role OR target_user_id.'],
                'target_role' => ['Please use one target mode only: class OR target_role OR target_user_id.'],
                'target_user_id' => ['Please use one target mode only: class OR target_role OR target_user_id.'],
            ]);
        }

        $payload['class_id'] = $classId;
        $payload['target_role'] = $targetRole !== '' ? $this->normalizeRole($targetRole) : null;
        $payload['target_user_id'] = $targetUserId;

        return $payload;
    }

    /**
     * @return array<int, int>
     */
    private function resolveAnnouncementRecipients(Announcement $announcement): array
    {
        if ($announcement->target_user_id) {
            return [(int) $announcement->target_user_id];
        }

        if ($announcement->target_role) {
            $query = User::query()
                ->where('school_id', $announcement->school_id)
                ->where('active', true);

            if ($announcement->target_role === 'parent') {
                $query->whereRaw('LOWER(role) in (?, ?)', ['parent', 'guardian']);
            } else {
                $query->where('role', $announcement->target_role);
            }

            return $query->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->values()
                ->all();
        }

        if (! $announcement->class_id) {
            return User::query()
                ->where('school_id', $announcement->school_id)
                ->where('active', true)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->values()
                ->all();
        }

        $studentUserIds = Student::query()
            ->where('class_id', $announcement->class_id)
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $parentIds = DB::table('parent_child')
            ->join('students', 'students.id', '=', 'parent_child.student_id')
            ->where('students.class_id', $announcement->class_id)
            ->pluck('parent_child.parent_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $teacherIds = DB::table('teacher_class')
            ->where('class_id', $announcement->class_id)
            ->pluck('teacher_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        return array_values(array_unique([
            ...$studentUserIds,
            ...$parentIds,
            ...$teacherIds,
        ]));
    }

    /**
     * @return array<int, int>
     */
    private function audienceClassIds(User $user): array
    {
        $role = $this->normalizeRole((string) $user->role);

        if ($role === 'teacher') {
            return $user->teachingClasses()->pluck('classes.id')
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->values()
                ->all();
        }

        if ($role === 'student') {
            $classId = (int) ($user->studentProfile?->class_id ?? 0);

            return $classId > 0 ? [$classId] : [];
        }

        if ($role === 'parent') {
            return $user->children()->pluck('students.class_id')
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        return [];
    }

    private function normalizeRole(string $role): string
    {
        $value = strtolower(trim($role));

        return $value === 'guardian' ? 'parent' : $value;
    }
}
