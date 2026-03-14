<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamSubject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamSubjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'exam_id' => ['nullable', 'integer', 'exists:exams,exam_id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = ExamSubject::query()->with(['exam', 'subject'])->orderByDesc('exam_subject_id');
        foreach (['exam_id', 'subject_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return response()->json($query->paginate($filters['per_page'] ?? 20));
    }

    public function show(ExamSubject $examSubject): JsonResponse
    {
        return response()->json(['data' => $examSubject->load(['exam', 'subject'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'exam_id' => ['required', 'integer', 'exists:exams,exam_id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'max_marks' => ['nullable', 'numeric', 'min:0'],
            'pass_marks' => ['nullable', 'numeric', 'min:0'],
        ]);

        $examSubject = ExamSubject::query()->create($payload);

        return response()->json(['message' => 'Exam subject created.', 'data' => $examSubject], 201);
    }

    public function update(Request $request, ExamSubject $examSubject): JsonResponse
    {
        $payload = $request->validate([
            'max_marks' => ['nullable', 'numeric', 'min:0'],
            'pass_marks' => ['nullable', 'numeric', 'min:0'],
        ]);

        $examSubject->fill($payload)->save();

        return response()->json(['message' => 'Exam subject updated.', 'data' => $examSubject]);
    }

    public function destroy(ExamSubject $examSubject): JsonResponse
    {
        $examSubject->delete();

        return response()->json(['message' => 'Exam subject deleted.']);
    }
}

