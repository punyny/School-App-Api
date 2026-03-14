<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'term_id' => ['nullable', 'integer', 'exists:terms,term_id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Exam::query()->with(['term', 'examSubjects.subject'])->orderByDesc('exam_id');
        if (isset($filters['term_id'])) {
            $query->where('term_id', (int) $filters['term_id']);
        }

        return response()->json($query->paginate($filters['per_page'] ?? 20));
    }

    public function show(Exam $exam): JsonResponse
    {
        return response()->json(['data' => $exam->load(['term', 'examSubjects.subject'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'term_id' => ['required', 'integer', 'exists:terms,term_id'],
            'exam_name' => ['required', 'string', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $exam = Exam::query()->create($payload);

        return response()->json(['message' => 'Exam created.', 'data' => $exam], 201);
    }

    public function update(Request $request, Exam $exam): JsonResponse
    {
        $payload = $request->validate([
            'term_id' => ['nullable', 'integer', 'exists:terms,term_id'],
            'exam_name' => ['nullable', 'string', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $exam->fill($payload)->save();

        return response()->json(['message' => 'Exam updated.', 'data' => $exam]);
    }

    public function destroy(Exam $exam): JsonResponse
    {
        $exam->delete();

        return response()->json(['message' => 'Exam deleted.']);
    }
}

