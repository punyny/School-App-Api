<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mark;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarkController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'exam_subject_id' => ['nullable', 'integer', 'exists:exam_subjects,exam_subject_id'],
            'enrollment_id' => ['nullable', 'integer', 'exists:enrollments,enrollment_id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Mark::query()
            ->with(['examSubject.exam', 'examSubject.subject', 'enrollment.student.user'])
            ->orderByDesc('mark_id');

        foreach (['exam_subject_id', 'enrollment_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return response()->json($query->paginate($filters['per_page'] ?? 20));
    }

    public function show(Mark $mark): JsonResponse
    {
        return response()->json([
            'data' => $mark->load(['examSubject.exam', 'examSubject.subject', 'enrollment.student.user']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'exam_subject_id' => ['required', 'integer', 'exists:exam_subjects,exam_subject_id'],
            'enrollment_id' => ['required', 'integer', 'exists:enrollments,enrollment_id'],
            'obtained_marks' => ['required', 'numeric', 'min:0'],
            'grade_letter' => ['nullable', 'string', 'max:5'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $mark = Mark::query()->create($payload);

        return response()->json(['message' => 'Mark created.', 'data' => $mark], 201);
    }

    public function update(Request $request, Mark $mark): JsonResponse
    {
        $payload = $request->validate([
            'obtained_marks' => ['nullable', 'numeric', 'min:0'],
            'grade_letter' => ['nullable', 'string', 'max:5'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $mark->fill($payload)->save();

        return response()->json(['message' => 'Mark updated.', 'data' => $mark]);
    }

    public function destroy(Mark $mark): JsonResponse
    {
        $mark->delete();

        return response()->json(['message' => 'Mark deleted.']);
    }
}

