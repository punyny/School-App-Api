<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherProfileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', 'in:Active,On Leave,Inactive'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Teacher::query()->with('user')->orderByDesc('teacher_id');
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($scope) use ($search): void {
                $scope->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhere('employee_no', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        return response()->json($query->paginate($filters['per_page'] ?? 20));
    }

    public function show(Teacher $teacher): JsonResponse
    {
        return response()->json(['data' => $teacher->load(['user', 'assignments.subject', 'assignments.section'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'employee_no' => ['required', 'string', 'max:30', 'unique:teachers,employee_no'],
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
            'specialization' => ['nullable', 'string', 'max:100'],
            'hire_date' => ['required', 'date'],
            'status' => ['nullable', 'in:Active,On Leave,Inactive'],
        ]);

        $teacher = Teacher::query()->create($payload);

        return response()->json(['message' => 'Teacher profile created.', 'data' => $teacher], 201);
    }

    public function update(Request $request, Teacher $teacher): JsonResponse
    {
        $payload = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'employee_no' => ['nullable', 'string', 'max:30', 'unique:teachers,employee_no,'.$teacher->teacher_id.',teacher_id'],
            'first_name' => ['nullable', 'string', 'max:50'],
            'last_name' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
            'specialization' => ['nullable', 'string', 'max:100'],
            'hire_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:Active,On Leave,Inactive'],
        ]);

        $teacher->fill($payload)->save();

        return response()->json(['message' => 'Teacher profile updated.', 'data' => $teacher]);
    }

    public function destroy(Teacher $teacher): JsonResponse
    {
        $teacher->delete();

        return response()->json(['message' => 'Teacher profile deleted.']);
    }
}

