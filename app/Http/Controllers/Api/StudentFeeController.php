<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentFee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentFeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'enrollment_id' => ['nullable', 'integer', 'exists:enrollments,enrollment_id'],
            'fee_type_id' => ['nullable', 'integer', 'exists:fee_types,fee_type_id'],
            'status' => ['nullable', 'in:Unpaid,Partially Paid,Paid,Waived'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = StudentFee::query()
            ->with(['enrollment.student.user', 'feeType', 'payments'])
            ->orderByDesc('student_fee_id');

        foreach (['enrollment_id', 'fee_type_id', 'status'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return response()->json($query->paginate($filters['per_page'] ?? 20));
    }

    public function show(StudentFee $studentFee): JsonResponse
    {
        return response()->json(['data' => $studentFee->load(['enrollment.student.user', 'feeType', 'payments'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'enrollment_id' => ['required', 'integer', 'exists:enrollments,enrollment_id'],
            'fee_type_id' => ['required', 'integer', 'exists:fee_types,fee_type_id'],
            'amount_due' => ['required', 'numeric', 'min:0'],
            'due_date' => ['required', 'date'],
            'status' => ['nullable', 'in:Unpaid,Partially Paid,Paid,Waived'],
        ]);

        $studentFee = StudentFee::query()->create($payload);

        return response()->json(['message' => 'Student fee created.', 'data' => $studentFee], 201);
    }

    public function update(Request $request, StudentFee $studentFee): JsonResponse
    {
        $payload = $request->validate([
            'amount_due' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:Unpaid,Partially Paid,Paid,Waived'],
        ]);

        $studentFee->fill($payload)->save();

        return response()->json(['message' => 'Student fee updated.', 'data' => $studentFee]);
    }

    public function destroy(StudentFee $studentFee): JsonResponse
    {
        $studentFee->delete();

        return response()->json(['message' => 'Student fee deleted.']);
    }
}

