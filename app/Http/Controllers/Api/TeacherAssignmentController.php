<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TeacherAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherAssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,teacher_id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,section_id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = TeacherAssignment::query()
            ->with(['teacher.user', 'subject', 'section.class', 'section.academicYear'])
            ->orderByDesc('assignment_id');

        foreach (['teacher_id', 'subject_id', 'section_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return response()->json($query->paginate($filters['per_page'] ?? 20));
    }

    public function show(TeacherAssignment $teacherAssignment): JsonResponse
    {
        return response()->json([
            'data' => $teacherAssignment->load(['teacher.user', 'subject', 'section.class', 'section.academicYear']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'teacher_id' => ['required', 'integer', 'exists:teachers,teacher_id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'section_id' => ['required', 'integer', 'exists:sections,section_id'],
        ]);

        $assignment = TeacherAssignment::query()->create($payload);

        return response()->json([
            'message' => 'Teacher assignment created.',
            'data' => $assignment->load(['teacher.user', 'subject', 'section.class', 'section.academicYear']),
        ], 201);
    }

    public function update(Request $request, TeacherAssignment $teacherAssignment): JsonResponse
    {
        $payload = $request->validate([
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,teacher_id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,section_id'],
        ]);

        $teacherAssignment->fill($payload)->save();

        return response()->json([
            'message' => 'Teacher assignment updated.',
            'data' => $teacherAssignment->fresh()->load(['teacher.user', 'subject', 'section.class', 'section.academicYear']),
        ]);
    }

    public function destroy(TeacherAssignment $teacherAssignment): JsonResponse
    {
        $teacherAssignment->delete();

        return response()->json([
            'message' => 'Teacher assignment deleted.',
        ]);
    }
}
