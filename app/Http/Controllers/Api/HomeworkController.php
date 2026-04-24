<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\HomeworkSubmissionStoreRequest;
use App\Http\Requests\Api\HomeworkStatusUpdateRequest;
use App\Http\Requests\Api\HomeworkStoreRequest;
use App\Http\Requests\Api\HomeworkUpdateRequest;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\HomeworkStatus;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Support\ProfileImageStorage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HomeworkController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Homework::class);

        $filters = $request->validate([
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'due_from' => ['nullable', 'date'],
            'due_to' => ['nullable', 'date', 'after_or_equal:due_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Homework::query()
            ->with(['class', 'subject', 'statuses.student.user', 'media'])
            ->withCount('submissions')
            ->orderByDesc('due_date')
            ->orderByDesc('due_time')
            ->orderByDesc('id');

        if ($request->user()->role === 'student') {
            $studentId = (int) ($request->user()->studentProfile?->id ?? 0);
            $query->with([
                'submissions' => fn ($submissionQuery) => $submissionQuery
                    ->where('student_id', $studentId > 0 ? $studentId : -1)
                    ->with('media')
                    ->latest('submitted_at'),
            ]);
        }

        $this->applyVisibilityScope($query, $request->user());

        if (isset($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (isset($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (isset($filters['due_from'])) {
            $query->whereDate('due_date', '>=', $filters['due_from']);
        }

        if (isset($filters['due_to'])) {
            $query->whereDate('due_date', '<=', $filters['due_to']);
        }

        return response()->json($query->paginate($filters['per_page'] ?? 20));
    }

    public function show(Request $request, Homework $homework): JsonResponse
    {
        $this->authorize('view', $homework);

        $homework->load(['class', 'subject', 'statuses.student.user', 'media']);
        $this->loadVisibleSubmissions($homework, $request->user());

        return response()->json([
            'data' => $homework,
        ]);
    }

    public function store(HomeworkStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Homework::class);

        $payload = $request->validated();
        $payload['due_time'] = $this->normalizeDueTimeValue($payload['due_time'] ?? null);

        if (! $this->canManageHomeworkTarget(
            $request->user(),
            (int) $payload['class_id'],
            (int) $payload['subject_id']
        )) {
            return response()->json([
                'message' => 'You do not have permission to manage homework for this class and subject.',
            ], 403);
        }

        $homework = Homework::query()->create($payload);
        $attachmentUrls = $this->storeUploadedAttachments($request, $homework);
        if ($attachmentUrls !== []) {
            $mergedUrls = collect(array_merge($payload['file_attachments'] ?? [], $attachmentUrls))
                ->filter(fn (?string $url) => is_string($url) && $url !== '')
                ->unique()
                ->values()
                ->all();
            $homework->forceFill(['file_attachments' => $mergedUrls])->save();
        }

        return response()->json([
            'message' => 'Homework created.',
            'data' => $homework->load(['class', 'subject', 'media']),
        ], 201);
    }

    public function update(HomeworkUpdateRequest $request, Homework $homework): JsonResponse
    {
        $this->authorize('update', $homework);

        $payload = $request->validated();
        $targetClassId = (int) ($payload['class_id'] ?? $homework->class_id);
        $targetSubjectId = (int) ($payload['subject_id'] ?? $homework->subject_id);
        $payload['due_time'] = array_key_exists('due_time', $payload)
            ? $this->normalizeDueTimeValue($payload['due_time'])
            : $homework->due_time;

        if (! $this->canManageHomeworkTarget($request->user(), $targetClassId, $targetSubjectId)) {
            return response()->json([
                'message' => 'You do not have permission to manage homework for this class and subject.',
            ], 403);
        }

        $homework->fill($payload)->save();
        $attachmentUrls = $this->storeUploadedAttachments($request, $homework);
        if ($attachmentUrls !== []) {
            $mergedUrls = collect(array_merge($homework->file_attachments ?? [], $attachmentUrls))
                ->filter(fn (?string $url) => is_string($url) && $url !== '')
                ->unique()
                ->values()
                ->all();
            $homework->forceFill(['file_attachments' => $mergedUrls])->save();
        }

        return response()->json([
            'message' => 'Homework updated.',
            'data' => $homework->fresh()->load(['class', 'subject', 'media']),
        ]);
    }

    public function destroy(Request $request, Homework $homework): JsonResponse
    {
        $this->authorize('delete', $homework);

        if (! $this->canManageHomeworkTarget($request->user(), (int) $homework->class_id, (int) $homework->subject_id)) {
            return response()->json([
                'message' => 'You do not have permission to manage homework for this class and subject.',
            ], 403);
        }

        $homework->delete();

        return response()->json([
            'message' => 'Homework deleted.',
        ]);
    }

    public function updateStatus(HomeworkStatusUpdateRequest $request, Homework $homework): JsonResponse
    {
        $this->authorize('updateStatus', $homework);

        $user = $request->user();
        $payload = $request->validated();
        $studentId = $this->resolveStudentForStatusUpdate($user, $payload['student_id'] ?? null);
        $student = Student::query()->findOrFail($studentId);

        if ((int) $student->class_id !== (int) $homework->class_id) {
            throw ValidationException::withMessages([
                'student_id' => ['This student is not in the homework class.'],
            ]);
        }

        $status = HomeworkStatus::query()->updateOrCreate(
            [
                'homework_id' => $homework->id,
                'student_id' => $studentId,
            ],
            [
                'status' => $payload['status'],
                'completion_date' => $payload['status'] === 'Done' ? now()->toDateString() : null,
            ]
        );

        return response()->json([
            'message' => 'Homework status updated.',
            'data' => $status->load(['homework', 'student.user']),
        ]);
    }

    public function submit(HomeworkSubmissionStoreRequest $request, Homework $homework): JsonResponse
    {
        $this->authorize('submit', $homework);

        $payload = $request->validated();
        $studentId = (int) ($request->user()->studentProfile?->id ?? 0);
        $student = Student::query()->findOrFail($studentId);

        if ((int) $student->class_id !== (int) $homework->class_id) {
            throw ValidationException::withMessages([
                'student_id' => ['This homework is not in your class.'],
            ]);
        }

        $answerText = trim((string) ($payload['answer_text'] ?? ''));
        $fileAttachmentUrls = collect($payload['file_attachments'] ?? [])
            ->map(fn ($url): string => trim((string) $url))
            ->filter(fn (string $url): bool => $url !== '')
            ->values()
            ->all();
        $uploadedFiles = $request->file('attachments', []);
        $hasUploadedFiles = is_array($uploadedFiles) && $uploadedFiles !== [];

        if ($answerText === '' && $fileAttachmentUrls === [] && ! $hasUploadedFiles) {
            throw ValidationException::withMessages([
                'answer_text' => ['Please provide answer text or at least one attachment.'],
            ]);
        }

        $submission = HomeworkSubmission::query()->updateOrCreate(
            [
                'homework_id' => $homework->id,
                'student_id' => $student->id,
            ],
            [
                'answer_text' => $answerText !== '' ? $answerText : null,
                'file_attachments' => $fileAttachmentUrls,
                'submitted_at' => now(),
            ],
        );

        $uploadedAttachmentUrls = $this->storeSubmissionUploadedAttachments($request, $submission);
        if ($uploadedAttachmentUrls !== []) {
            $mergedUrls = collect(array_merge($submission->file_attachments ?? [], $uploadedAttachmentUrls))
                ->map(fn ($url): string => trim((string) $url))
                ->filter(fn (string $url): bool => $url !== '')
                ->unique()
                ->values()
                ->all();
            $submission->forceFill(['file_attachments' => $mergedUrls])->save();
        }

        HomeworkStatus::query()->updateOrCreate(
            [
                'homework_id' => $homework->id,
                'student_id' => $student->id,
            ],
            [
                'status' => 'Done',
                'completion_date' => now()->toDateString(),
            ],
        );

        return response()->json([
            'message' => 'បានដាក់កិច្ចការជោគជ័យ។',
            'data' => $submission->fresh()->load(['student.user', 'homework', 'media']),
        ]);
    }

    public function exportPdf(Request $request)
    {
        $this->authorize('export', Homework::class);

        $filters = $request->validate([
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'due_from' => ['nullable', 'date'],
            'due_to' => ['nullable', 'date', 'after_or_equal:due_from'],
        ]);

        $query = Homework::query()
            ->with(['class', 'subject', 'statuses.student.user', 'media'])
            ->orderByDesc('due_date')
            ->orderByDesc('due_time')
            ->orderByDesc('id');

        $this->applyVisibilityScope($query, $request->user());

        if (isset($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (isset($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (isset($filters['due_from'])) {
            $query->whereDate('due_date', '>=', $filters['due_from']);
        }

        if (isset($filters['due_to'])) {
            $query->whereDate('due_date', '<=', $filters['due_to']);
        }

        $rows = $query->get();
        $fileName = 'homeworks_export_'.now()->format('Ymd_His').'.pdf';

        return Pdf::loadView('pdf.homeworks', [
            'rows' => $rows,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape')
            ->download($fileName);
    }

    /**
     * @return array<int, string>
     */
    private function storeUploadedAttachments(Request $request, Homework $homework): array
    {
        $files = $request->file('attachments', []);
        if (! is_array($files) || $files === []) {
            return [];
        }

        return ProfileImageStorage::attachManyToModel(
            $files,
            $homework,
            $request->user(),
            'attachments/homeworks',
            'attachment',
            [
                'module' => 'homework',
                'class_id' => $homework->class_id,
                'subject_id' => $homework->subject_id,
            ]
        );
    }

    /**
     * @return array<int, string>
     */
    private function storeSubmissionUploadedAttachments(Request $request, HomeworkSubmission $submission): array
    {
        $files = $request->file('attachments', []);
        if (! is_array($files) || $files === []) {
            return [];
        }

        return ProfileImageStorage::attachManyToModel(
            $files,
            $submission,
            $request->user(),
            'attachments/homework-submissions',
            'attachment',
            [
                'module' => 'homework_submission',
                'homework_id' => $submission->homework_id,
                'student_id' => $submission->student_id,
            ]
        );
    }

    private function loadVisibleSubmissions(Homework $homework, User $user): void
    {
        if (! $user->can('viewSubmissions', $homework)) {
            return;
        }

        if ($user->role === 'student') {
            $studentId = (int) ($user->studentProfile?->id ?? 0);
            $homework->load([
                'submissions' => fn ($query) => $query
                    ->where('student_id', $studentId > 0 ? $studentId : -1)
                    ->with(['student.user', 'media'])
                    ->latest('submitted_at'),
            ]);

            return;
        }

        if ($user->role === 'parent') {
            $studentIds = $user->children()->pluck('students.id')->map(fn ($id): int => (int) $id)->all();
            $homework->load([
                'submissions' => fn ($query) => $query
                    ->whereIn('student_id', $studentIds === [] ? [-1] : $studentIds)
                    ->with(['student.user', 'media'])
                    ->latest('submitted_at'),
            ]);

            return;
        }

        $homework->load([
            'submissions' => fn ($query) => $query
                ->with(['student.user', 'media'])
                ->latest('submitted_at'),
        ]);
    }

    private function resolveStudentForStatusUpdate(User $user, ?int $requestedStudentId): int
    {
        if ($user->role === 'student') {
            $studentId = $user->studentProfile?->id;
            if (! $studentId) {
                throw ValidationException::withMessages([
                    'student_id' => ['Student profile not found for this user.'],
                ]);
            }

            return $studentId;
        }

        if ($user->role === 'parent') {
            if (! $requestedStudentId) {
                throw ValidationException::withMessages([
                    'student_id' => ['student_id is required for parent role.'],
                ]);
            }

            $allowed = $user->children()->where('students.id', $requestedStudentId)->exists();
            if (! $allowed) {
                throw ValidationException::withMessages([
                    'student_id' => ['You can only update status for your own child.'],
                ]);
            }

            return $requestedStudentId;
        }

        throw ValidationException::withMessages([
            'role' => ['Only student or parent can update homework status.'],
        ]);
    }

    private function applyVisibilityScope(Builder $query, User $user): void
    {
        if ($user->role === 'super-admin') {
            return;
        }

        if ($user->role === 'student') {
            $classId = $user->studentProfile?->class_id;
            $query->whereIn('class_id', $classId ? [$classId] : [-1]);

            return;
        }

        if ($user->role === 'parent') {
            $classIds = $user->children()->pluck('students.class_id')->unique()->values()->all();
            $query->whereIn('class_id', $classIds === [] ? [-1] : $classIds);

            return;
        }

        if (! $user->school_id) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('class', fn (Builder $classQuery) => $classQuery->where('school_id', $user->school_id));

        if ($user->role === 'teacher') {
            $query->whereExists(function ($subQuery) use ($user): void {
                $subQuery->selectRaw('1')
                    ->from('teacher_class')
                    ->whereColumn('teacher_class.class_id', 'homeworks.class_id')
                    ->whereColumn('teacher_class.subject_id', 'homeworks.subject_id')
                    ->where('teacher_class.teacher_id', $user->id);
            });
        }
    }

    private function canViewHomework(User $user, Homework $homework): bool
    {
        $query = Homework::query()->whereKey($homework->id);
        $this->applyVisibilityScope($query, $user);

        return $query->exists();
    }

    private function canManageHomeworkTarget(User $user, int $classId, int $subjectId): bool
    {
        if (! in_array($user->role, ['super-admin', 'admin', 'teacher'], true)) {
            return false;
        }

        $class = SchoolClass::query()->find($classId);
        if (! $class) {
            return false;
        }

        $subject = Subject::query()->find($subjectId);
        if (! $subject) {
            return false;
        }

        if ((int) $class->school_id !== (int) $subject->school_id) {
            return false;
        }

        if ($user->role === 'super-admin') {
            return true;
        }

        if (! $user->school_id || (int) $class->school_id !== (int) $user->school_id) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        return DB::table('teacher_class')
            ->where('teacher_id', $user->id)
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->exists();
    }

    private function normalizeDueTimeValue(mixed $value): ?string
    {
        $time = trim((string) $value);
        if ($time === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
            return $time.':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time) === 1) {
            return $time;
        }

        return null;
    }
}
