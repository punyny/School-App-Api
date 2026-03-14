<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuardianController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Guardian::query()->with(['user', 'students.user'])->orderByDesc('guardian_id');
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($scope) use ($search): void {
                $scope->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        return response()->json($query->paginate($filters['per_page'] ?? 20));
    }

    public function show(Guardian $guardian): JsonResponse
    {
        return response()->json(['data' => $guardian->load(['user', 'students.user'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'relationship_to_student' => ['required', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer', 'exists:students,id'],
            'primary_student_id' => ['nullable', 'integer', 'exists:students,id'],
        ]);

        $studentIds = array_values(array_unique($payload['student_ids'] ?? []));
        $guardian = Guardian::query()->create($payload);
        if ($studentIds !== []) {
            $sync = [];
            foreach ($studentIds as $studentId) {
                $sync[(int) $studentId] = [
                    'is_primary' => isset($payload['primary_student_id']) && (int) $payload['primary_student_id'] === (int) $studentId,
                ];
            }
            $guardian->students()->sync($sync);
        }

        return response()->json(['message' => 'Guardian created.', 'data' => $guardian->load(['students.user'])], 201);
    }

    public function update(Request $request, Guardian $guardian): JsonResponse
    {
        $payload = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'first_name' => ['nullable', 'string', 'max:50'],
            'last_name' => ['nullable', 'string', 'max:50'],
            'relationship_to_student' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer', 'exists:students,id'],
            'primary_student_id' => ['nullable', 'integer', 'exists:students,id'],
        ]);

        $guardian->fill($payload)->save();

        if (array_key_exists('student_ids', $payload)) {
            $sync = [];
            foreach (array_values(array_unique($payload['student_ids'] ?? [])) as $studentId) {
                $sync[(int) $studentId] = [
                    'is_primary' => isset($payload['primary_student_id']) && (int) $payload['primary_student_id'] === (int) $studentId,
                ];
            }
            $guardian->students()->sync($sync);
        }

        return response()->json(['message' => 'Guardian updated.', 'data' => $guardian->fresh()->load(['students.user'])]);
    }

    public function destroy(Guardian $guardian): JsonResponse
    {
        $guardian->delete();

        return response()->json(['message' => 'Guardian deleted.']);
    }
}

