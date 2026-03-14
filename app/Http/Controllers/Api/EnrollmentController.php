<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,academic_year_id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,section_id'],
            'status' => ['nullable', 'in:Enrolled,Promoted,Completed,Dropped'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Enrollment::query()
            ->with(['student.user', 'academicYear', 'class', 'section'])
            ->orderByDesc('enrollment_id');

        foreach (['student_id', 'academic_year_id', 'section_id', 'status'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return response()->json($query->paginate($filters['per_page'] ?? 20));
    }

    public function show(Enrollment $enrollment): JsonResponse
    {
        return response()->json([
            'data' => $enrollment->load(['student.user', 'academicYear', 'class', 'section']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,academic_year_id'],
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'section_id' => ['required', 'integer', 'exists:sections,section_id'],
            'roll_no' => ['nullable', 'string', 'max:20'],
            'enrollment_date' => ['required', 'date'],
            'status' => ['nullable', 'in:Enrolled,Promoted,Completed,Dropped'],
        ]);

        $enrollment = Enrollment::query()->create($payload);

        return response()->json(['message' => 'Enrollment created.', 'data' => $enrollment], 201);
    }

    public function update(Request $request, Enrollment $enrollment): JsonResponse
    {
        $payload = $request->validate([
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,section_id'],
            'roll_no' => ['nullable', 'string', 'max:20'],
            'enrollment_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:Enrolled,Promoted,Completed,Dropped'],
        ]);

        $enrollment->fill($payload)->save();

        return response()->json(['message' => 'Enrollment updated.', 'data' => $enrollment]);
    }

    public function destroy(Enrollment $enrollment): JsonResponse
    {
        $enrollment->delete();

        return response()->json(['message' => 'Enrollment deleted.']);
    }
}

