<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TermController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,academic_year_id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Term::query()->with('academicYear')->orderByDesc('term_id');
        if (isset($filters['academic_year_id'])) {
            $query->where('academic_year_id', (int) $filters['academic_year_id']);
        }

        return response()->json($query->paginate($filters['per_page'] ?? 20));
    }

    public function show(Term $term): JsonResponse
    {
        return response()->json(['data' => $term->load('academicYear')]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,academic_year_id'],
            'term_name' => ['required', 'string', 'max:30'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ]);

        $term = Term::query()->create($payload);

        return response()->json(['message' => 'Term created.', 'data' => $term], 201);
    }

    public function update(Request $request, Term $term): JsonResponse
    {
        $payload = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,academic_year_id'],
            'term_name' => ['nullable', 'string', 'max:30'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $term->fill($payload)->save();

        return response()->json(['message' => 'Term updated.', 'data' => $term]);
    }

    public function destroy(Term $term): JsonResponse
    {
        $term->delete();

        return response()->json(['message' => 'Term deleted.']);
    }
}

