<?php

namespace App\Http\Controllers\Api;

use App\Events\RealtimeNotificationBroadcasted;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IncidentReportAcknowledgmentRequest;
use App\Http\Requests\Api\IncidentReportStoreRequest;
use App\Http\Requests\Api\IncidentReportUpdateRequest;
use App\Models\IncidentReport;
use App\Models\Notification as UserNotification;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\IncidentReportTelegramNotifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IncidentReportController extends Controller
{
    public function __construct(
        private readonly IncidentReportTelegramNotifier $incidentReportTelegramNotifier,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', IncidentReport::class);

        $filters = $request->validate([
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'type' => ['nullable', 'string', 'max:100'],
            'acknowledged' => ['nullable', 'boolean'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = IncidentReport::query()
            ->with(['student.user', 'student.class', 'reporter'])
            ->orderByDesc('date')
            ->orderByDesc('id');

        $this->applyVisibilityScope($query, $request->user());

        if (isset($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (array_key_exists('acknowledged', $filters)) {
            $query->where('acknowledged', $filters['acknowledged']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        return response()->json($query->paginate($filters['per_page'] ?? 20));
    }

    public function show(Request $request, IncidentReport $incidentReport): JsonResponse
    {
        $this->authorize('view', $incidentReport);

        return response()->json([
            'data' => $incidentReport->load(['student.user', 'student.class', 'reporter']),
        ]);
    }

    public function store(IncidentReportStoreRequest $request): JsonResponse
    {
        $this->authorize('create', IncidentReport::class);

        $user = $request->user();
        $payload = $request->validated();

        if (! $this->canManageStudent($user, (int) $payload['student_id'])) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $incident = IncidentReport::query()->create([
            'student_id' => $payload['student_id'],
            'description' => $payload['description'],
            'date' => $payload['date'] ?? now()->toDateString(),
            'type' => $payload['type'] ?? null,
            'acknowledged' => $payload['acknowledged'] ?? false,
            'reporter_id' => $user->id,
        ]);

        $this->notifyIncidentRecipients($incident);

        return response()->json([
            'message' => 'Incident report created.',
            'data' => $incident->load(['student.user', 'student.class', 'reporter']),
        ], 201);
    }

    public function update(IncidentReportUpdateRequest $request, IncidentReport $incidentReport): JsonResponse
    {
        $this->authorize('update', $incidentReport);

        $user = $request->user();
        $payload = $request->validated();
        $targetStudentId = (int) ($payload['student_id'] ?? $incidentReport->student_id);

        if (! $this->canManageStudent($user, $targetStudentId)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $incidentReport->fill($payload)->save();

        return response()->json([
            'message' => 'Incident report updated.',
            'data' => $incidentReport->fresh()->load(['student.user', 'student.class', 'reporter']),
        ]);
    }

    public function destroy(Request $request, IncidentReport $incidentReport): JsonResponse
    {
        $this->authorize('delete', $incidentReport);

        $incidentReport->delete();

        return response()->json([
            'message' => 'Incident report deleted.',
        ]);
    }

    public function updateAcknowledgment(
        IncidentReportAcknowledgmentRequest $request,
        IncidentReport $incidentReport
    ): JsonResponse {
        $this->authorize('updateAcknowledgment', $incidentReport);

        $incidentReport->acknowledged = $request->validated()['acknowledged'];
        $incidentReport->save();

        return response()->json([
            'message' => 'Incident acknowledgment updated.',
            'data' => $incidentReport->fresh()->load(['student.user', 'student.class', 'reporter']),
        ]);
    }

    private function applyVisibilityScope(Builder $query, User $user): void
    {
        if ($user->role === 'super-admin') {
            return;
        }

        if (in_array($user->role, ['admin', 'teacher'], true)) {
            if (! $user->school_id) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->whereHas('student.class', fn (Builder $classQuery) => $classQuery->where('school_id', $user->school_id));

            if ($user->role === 'teacher') {
                $classIds = $user->teachingClasses()->pluck('classes.id')->all();
                $query->whereHas('student', fn (Builder $studentQuery) => $studentQuery->whereIn('class_id', $classIds === [] ? [-1] : $classIds));
            }

            return;
        }

        if ($user->role === 'student') {
            $studentId = $user->studentProfile?->id;
            $query->where('student_id', $studentId ?: -1);

            return;
        }

        if ($user->role === 'parent') {
            $studentIds = $user->children()->pluck('students.id')->all();
            $query->whereIn('student_id', $studentIds === [] ? [-1] : $studentIds);

            return;
        }

        $query->whereRaw('1 = 0');
    }

    private function canViewIncident(User $user, IncidentReport $incidentReport): bool
    {
        $query = IncidentReport::query()->whereKey($incidentReport->id);
        $this->applyVisibilityScope($query, $user);

        return $query->exists();
    }

    private function canManageStudent(User $user, int $studentId): bool
    {
        if ($user->role === 'super-admin') {
            return true;
        }

        if (! in_array($user->role, ['admin', 'teacher'], true) || ! $user->school_id) {
            return false;
        }

        $student = Student::query()->find($studentId);
        if (! $student) {
            return false;
        }

        $class = SchoolClass::query()->find($student->class_id);
        if (! $class || (int) $class->school_id !== (int) $user->school_id) {
            return false;
        }

        if ($user->role === 'teacher') {
            return $user->teachingClasses()->where('classes.id', $class->id)->exists();
        }

        return true;
    }

    private function notifyIncidentRecipients(IncidentReport $incidentReport): void
    {
        $student = Student::query()->find($incidentReport->student_id);
        if (! $student) {
            return;
        }

        $recipientIds = [];
        if ($student->user_id) {
            $recipientIds[] = (int) $student->user_id;
        }

        $parentIds = DB::table('parent_child')
            ->where('student_id', $student->id)
            ->pluck('parent_id')
            ->all();

        $recipientIds = array_values(array_unique(array_filter([...$recipientIds, ...$parentIds])));
        if ($recipientIds === []) {
            return;
        }

        $rows = [];
        $now = now();
        foreach ($recipientIds as $userId) {
            $rows[] = [
                'user_id' => $userId,
                'title' => 'New incident report',
                'content' => mb_strimwidth($incidentReport->description, 0, 120, '...'),
                'date' => $now,
                'read_status' => false,
            ];
        }

        UserNotification::query()->insert($rows);

        event(new RealtimeNotificationBroadcasted(
            recipientIds: $recipientIds,
            type: 'incident',
            title: 'New incident report',
            content: mb_strimwidth($incidentReport->description, 0, 120, '...'),
            meta: [
                'incident_id' => (int) $incidentReport->id,
                'student_id' => (int) $incidentReport->student_id,
                'acknowledged' => (bool) $incidentReport->acknowledged,
            ]
        ));

        $this->incidentReportTelegramNotifier->sendToStudentParents($incidentReport);
    }
}
