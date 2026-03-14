<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,academic_year_id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Section::query()
            ->with(['class', 'academicYear', 'classTeacher.user'])
            ->orderByDesc('section_id');

        if (isset($filters['class_id'])) {
            $query->where('class_id', (int) $filters['class_id']);
        }
        if (isset($filters['academic_year_id'])) {
            $query->where('academic_year_id', (int) $filters['academic_year_id']);
        }

        return response()->json($query->paginate($filters['per_page'] ?? 20));
    }

    public function show(Section $section): JsonResponse
    {
        return response()->json(['data' => $section->load(['class', 'academicYear', 'classTeacher.user'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,academic_year_id'],
            'section_name' => ['required', 'string', 'max:20'],
            'class_teacher_id' => ['nullable', 'integer', 'exists:teachers,teacher_id'],
            'room_no' => ['nullable', 'string', 'max:20'],
        ]);

        $section = Section::query()->create($payload);

        return response()->json(['message' => 'Section created.', 'data' => $section], 201);
    }

    public function update(Request $request, Section $section): JsonResponse
    {
        $payload = $request->validate([
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,academic_year_id'],
            'section_name' => ['nullable', 'string', 'max:20'],
            'class_teacher_id' => ['nullable', 'integer', 'exists:teachers,teacher_id'],
            'room_no' => ['nullable', 'string', 'max:20'],
        ]);

        $section->fill($payload)->save();

        return response()->json(['message' => 'Section updated.', 'data' => $section]);
    }

    public function destroy(Section $section): JsonResponse
    {
        $section->delete();

        return response()->json(['message' => 'Section deleted.']);
    }
}

