<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademicYearController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'is_current' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = AcademicYear::query()->orderByDesc('academic_year_id');
        if (array_key_exists('is_current', $filters)) {
            $query->where('is_current', $filters['is_current']);
        }

        return response()->json($query->paginate($filters['per_page'] ?? 20));
    }

    public function show(AcademicYear $academicYear): JsonResponse
    {
        return response()->json(['data' => $academicYear->load('terms')]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'year_name' => ['required', 'string', 'max:20', 'unique:academic_years,year_name'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_current' => ['nullable', 'boolean'],
        ]);

        if (($payload['is_current'] ?? false) === true) {
            AcademicYear::query()->update(['is_current' => false]);
        }

        $academicYear = AcademicYear::query()->create($payload);

        return response()->json(['message' => 'Academic year created.', 'data' => $academicYear], 201);
    }

    public function update(Request $request, AcademicYear $academicYear): JsonResponse
    {
        $payload = $request->validate([
            'year_name' => ['nullable', 'string', 'max:20', 'unique:academic_years,year_name,'.$academicYear->academic_year_id.',academic_year_id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'is_current' => ['nullable', 'boolean'],
        ]);

        if (($payload['is_current'] ?? false) === true) {
            AcademicYear::query()
                ->whereKeyNot($academicYear->academic_year_id)
                ->update(['is_current' => false]);
        }

        $academicYear->fill($payload)->save();

        return response()->json(['message' => 'Academic year updated.', 'data' => $academicYear]);
    }

    public function destroy(AcademicYear $academicYear): JsonResponse
    {
        $academicYear->delete();

        return response()->json(['message' => 'Academic year deleted.']);
    }
}

